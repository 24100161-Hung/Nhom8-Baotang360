"""Ứng dụng FastAPI nội bộ của BaoTang360 (Laravel gọi qua AnalyticsClient, header X-Analytics-Token)."""
from __future__ import annotations

import asyncio
import logging
import os
import secrets
import time
from contextlib import asynccontextmanager, suppress
from dataclasses import dataclass
from typing import Literal

from fastapi import BackgroundTasks, Depends, FastAPI, Header, HTTPException, Query, status
from pydantic import BaseModel, Field, model_validator

from . import db
from .flow import scene_flow, tour_dropoff
from .quality import build_report
from .recommend import TourRecommender
from .search import SearchIndex
from .stats import compute_overview
from .tile_jobs import TileJobs

logger = logging.getLogger("baotang360.analytics")
logging.basicConfig(level=os.getenv("LOG_LEVEL", "INFO").upper())

REINDEX_INTERVAL_SECONDS = int(os.getenv("REINDEX_INTERVAL_SECONDS", "600"))
# Khi chỉ mục còn rỗng (DB chưa seed xong lúc khởi động) thử lại nhanh hơn
EMPTY_RETRY_SECONDS = int(os.getenv("EMPTY_RETRY_SECONDS", "30"))
Locale = Literal["vi", "en"]
SubjectType = Literal["artifact", "tour", "scene", "post"]
DaysQuery = Query(default=30, ge=1, le=365)


@dataclass(frozen=True)
class EngineState:
    index: SearchIndex
    recommender: TourRecommender


def empty_state() -> EngineState:
    return EngineState(index=SearchIndex.empty(), recommender=TourRecommender())


# Trạng thái được thay nguyên khối khi reindex: request đang chạy không thấy chỉ mục dở dang,
# cache gợi ý nằm trong đối tượng TourRecommender nên tự bị loại bỏ.
STATE: dict[str, EngineState] = {"current": empty_state()}
TILE_JOBS = TileJobs()
_reindex_lock = asyncio.Lock()


def build_state() -> EngineState:
    """Đọc DB và dựng chỉ mục + bộ gợi ý mới (chạy trong thread vì tốn CPU/IO)."""
    corpus = db.load_corpus()
    interp = corpus.interpretations
    if not interp.empty:
        interp = interp.assign(subject_type=db.normalize_morph(interp["subject_type"]))
    return EngineState(
        index=SearchIndex.build(db.build_documents(corpus)),
        recommender=TourRecommender.build(corpus.tours, corpus.steps, interp, corpus.artifact_scenes),
    )


async def refresh_index() -> EngineState:
    async with _reindex_lock:
        state = await asyncio.to_thread(build_state)
        STATE["current"] = state
        logger.info("Đã đánh chỉ mục %d tài liệu, %d tour", state.index.size, state.recommender.size)
        return state


async def _safe_refresh() -> None:
    try:
        await refresh_index()
    except Exception:  # DB chưa sẵn sàng: giữ chỉ mục cũ/rỗng, không làm sập dịch vụ
        logger.exception("Không dựng được chỉ mục, giữ chỉ mục hiện tại")


async def _periodic_reindex() -> None:
    while True:
        interval = REINDEX_INTERVAL_SECONDS if STATE["current"].index.size > 0 else EMPTY_RETRY_SECONDS
        await asyncio.sleep(interval)
        await _safe_refresh()


async def _ensure_index() -> EngineState:
    """Chỉ mục rỗng (ví dụ seed xong sau khi dịch vụ khởi động) thì dựng lại ngay trước khi phục vụ."""
    if STATE["current"].index.size == 0:
        await _safe_refresh()
    return STATE["current"]


@asynccontextmanager
async def lifespan(_: FastAPI):
    if not os.getenv("ANALYTICS_TOKEN"):
        logger.warning("ANALYTICS_TOKEN chưa đặt: mọi endpoint có bảo vệ sẽ trả 401")
    await _safe_refresh()
    task = asyncio.create_task(_periodic_reindex())
    try:
        yield
    finally:
        task.cancel()
        with suppress(asyncio.CancelledError):
            await task


# Tắt trang tài liệu tương tác khi chạy production (chỉ bật ở môi trường phát triển)
_IS_PROD = os.getenv("APP_ENV", "production") == "production"
app = FastAPI(
    title="BaoTang360 Analytics",
    version="2.0.0",
    lifespan=lifespan,
    docs_url=None if _IS_PROD else "/docs",
    redoc_url=None if _IS_PROD else "/redoc",
    openapi_url=None if _IS_PROD else "/openapi.json",
)


def require_token(x_analytics_token: str | None = Header(default=None)) -> None:
    """So sánh hằng thời gian; thiếu cấu hình token thì từ chối tất cả (fail closed)."""
    expected = os.getenv("ANALYTICS_TOKEN", "")
    if not expected or not x_analytics_token or not secrets.compare_digest(
        x_analytics_token.encode(), expected.encode()
    ):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid analytics token")


class SearchRequest(BaseModel):
    q: str = Field(min_length=1, max_length=500)
    locale: Locale = "vi"
    limit: int = Field(default=20, ge=1, le=100)
    types: list[SubjectType] | None = None


class RecommendRequest(BaseModel):
    tour_id: int | None = Field(default=None, ge=1)
    session_key: str | None = Field(default=None, min_length=1, max_length=255, pattern=r"^[A-Za-z0-9_\-.:]+$")
    locale: Locale = "vi"
    limit: int = Field(default=4, ge=1, le=20)

    @model_validator(mode="after")
    def _one_input(self) -> "RecommendRequest":
        if (self.tour_id is None) == (self.session_key is None):
            raise ValueError("Cần đúng một trong hai trường tour_id hoặc session_key")
        return self


class TileRequest(BaseModel):
    scene_id: int = Field(ge=1)
    panorama_path: str = Field(min_length=1, max_length=500)


def _ms(start: float) -> int:
    return int((time.perf_counter() - start) * 1000)


def _db_unavailable(exc: Exception) -> HTTPException:
    logger.error("Lỗi truy vấn DB: %s", type(exc).__name__)
    return HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail="Database unavailable")


@app.get("/healthz")
def healthz() -> dict:
    state = STATE["current"]
    return {
        "status": "ok",
        "index_size": state.index.size,
        "tours_indexed": state.recommender.size,
        "indexed_at": state.index.indexed_at.isoformat() if state.index.indexed_at else None,
    }


@app.post("/search", dependencies=[Depends(require_token)])
async def search(body: SearchRequest) -> dict:
    start = time.perf_counter()
    state = await _ensure_index()
    results = state.index.search(body.q, body.locale, body.limit, body.types)
    return {"engine": "tfidf", "took_ms": _ms(start), "results": results}


@app.post("/recommend/tours", dependencies=[Depends(require_token)])
async def recommend_tours(body: RecommendRequest) -> dict:
    start = time.perf_counter()
    rec = (await _ensure_index()).recommender
    if body.tour_id is not None:
        results = rec.similar_to_tour(body.tour_id, body.locale, body.limit)
        return {"mode": "tour", "took_ms": _ms(start), "results": results}
    cached = rec.cached_session(body.session_key, body.locale, body.limit)
    if cached is None:
        try:
            events = await asyncio.to_thread(db.load_session_events, body.session_key)
        except Exception as exc:
            raise _db_unavailable(exc) from exc
        cached = rec.for_session(body.session_key, events, body.locale, body.limit)
    return {"mode": "session", "took_ms": _ms(start), "results": cached}


@app.get("/analytics/dropoff", dependencies=[Depends(require_token)])
def analytics_dropoff(days: int = DaysQuery) -> dict:
    try:
        events, tours = db.load_tour_events(days)
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return {"days": days, **tour_dropoff(events, tours)}


@app.get("/analytics/scene-flow", dependencies=[Depends(require_token)])
def analytics_scene_flow(days: int = DaysQuery) -> dict:
    try:
        events = db.load_scene_events(days)
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return {"days": days, **scene_flow(events)}


@app.post("/tiles/generate", status_code=status.HTTP_202_ACCEPTED, dependencies=[Depends(require_token)])
def tiles_generate(body: TileRequest, background: BackgroundTasks) -> dict:
    try:
        job, source = TILE_JOBS.submit(body.scene_id, body.panorama_path)
    except FileNotFoundError as exc:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=str(exc)) from exc
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    if source is not None:
        background.add_task(TILE_JOBS.run, body.scene_id, source)
    return {**job, "queued": source is not None}


@app.get("/tiles/status/{scene_id}", dependencies=[Depends(require_token)])
def tiles_status(scene_id: int) -> dict:
    job = TILE_JOBS.status(scene_id)
    if job is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="No tile job for this scene")
    return job


@app.get("/quality-report", dependencies=[Depends(require_token)])
def quality_report() -> dict:
    try:
        tables = db.load_quality_tables()
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return build_report(tables)


@app.post("/reindex", dependencies=[Depends(require_token)])
async def reindex() -> dict:
    start = time.perf_counter()
    try:
        state = await refresh_index()
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return {"indexed": state.index.size, "tours": state.recommender.size, "took_ms": _ms(start)}


@app.get("/stats/overview", dependencies=[Depends(require_token)])
def stats_overview(days: int = DaysQuery) -> dict:
    try:
        tables = db.load_stats_tables()
        by_type, sessions = db.load_event_summary(days)
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return compute_overview(tables, by_type, sessions, days)
