"""Kết nối MySQL (SQLAlchemy + PyMySQL) và nạp dữ liệu BaoTang360 thành DataFrame."""
from __future__ import annotations

import logging
import os
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from functools import lru_cache

import pandas as pd
from sqlalchemy import URL, create_engine, text
from sqlalchemy.engine import Engine
from sqlalchemy.exc import DBAPIError

from .search import DOCUMENT_COLUMNS

logger = logging.getLogger("baotang360.analytics.db")

# Dữ liệu có thể lưu tên lớp thay vì alias morph; quy về alias để thống nhất.
MORPH_ALIASES = {
    "App\\Models\\Artifact": "artifact",
    "App\\Models\\Scene": "scene",
    "App\\Models\\Tour": "tour",
    "App\\Models\\BlogPost": "post",
    "App\\Models\\Hotspot": "hotspot",
    "App\\Models\\Museum": "museum",
}
# Mã lỗi MySQL: 1146 bảng không tồn tại, 1054 cột không tồn tại (lược đồ đang được dựng dở).
SCHEMA_ERROR_CODES = frozenset({1146, 1054})
SOFT_DELETE_TABLES = frozenset({"artifacts", "scenes", "tours", "blog_posts", "ticket_packages"})

# Danh sách trắng bảng/cột: tên định danh không bao giờ lấy từ đầu vào người dùng.
TABLE_COLUMNS: dict[str, tuple[str, ...]] = {
    "scenes": ("id", "slug", "status", "panorama_path", "tiles_path", "tile_config", "preview_path"),
    "hotspots": ("id", "scene_id", "type", "pitch", "yaw", "target_scene_id", "artifact_id", "status"),
    "artifacts": ("id", "slug", "status", "scene_id"),
    "interpretations": ("id", "subject_type", "subject_id", "locale", "depth_level", "title",
                        "is_machine_translated", "reviewed_by"),
    "media_assets": ("id", "subject_type", "subject_id", "media_type", "locale", "license",
                     "source_name", "source_url"),
    "tours": ("id", "slug", "status"),
    "tour_steps": ("id", "tour_id", "step_order", "scene_id"),
    "blog_posts": ("id", "slug", "status"),
    "visit_days": ("id", "visit_date", "capacity", "sold", "status"),
    "orders": ("id", "code", "status"),
    "order_items": ("id", "order_id"),
    "tickets": ("id", "order_item_id"),
}
QUALITY_TABLES = (
    "scenes", "hotspots", "artifacts", "interpretations", "media_assets", "tours", "tour_steps",
    "visit_days", "orders", "order_items", "tickets",
)
STATS_TABLES = ("scenes", "artifacts", "tours", "blog_posts")

PUBLISHED_ENTITIES_SQL = """
SELECT 'artifact' AS subject_type, id AS subject_id, slug FROM artifacts
 WHERE status = 'published' AND deleted_at IS NULL
UNION ALL
SELECT 'scene', id, slug FROM scenes WHERE status = 'published' AND deleted_at IS NULL
"""
INTERPRETATIONS_SQL = """
SELECT subject_type, subject_id, locale, depth_level AS depth, title, summary, body
  FROM interpretations
"""
TOURS_SQL = """
SELECT t.id AS subject_id, t.slug, tt.locale, tt.name AS title, tt.description AS summary,
       tt.highlights AS body
  FROM tours t JOIN tour_translations tt ON tt.tour_id = t.id
 WHERE t.status = 'published' AND t.deleted_at IS NULL
"""
POSTS_SQL = """
SELECT p.id AS subject_id, p.slug, pt.locale, pt.title, pt.excerpt AS summary, pt.body
  FROM blog_posts p JOIN blog_post_translations pt ON pt.post_id = p.id
 WHERE p.status = 'published' AND p.deleted_at IS NULL
   AND (p.published_at IS NULL OR p.published_at <= NOW())
"""
STEPS_SQL = """
SELECT s.tour_id, s.step_order, s.scene_id
  FROM tour_steps s JOIN tours t ON t.id = s.tour_id
 WHERE t.status = 'published' AND t.deleted_at IS NULL
 ORDER BY s.tour_id, s.step_order
"""
ARTIFACT_SCENES_SQL = """
SELECT id AS artifact_id, scene_id FROM artifacts
 WHERE status = 'published' AND deleted_at IS NULL AND scene_id IS NOT NULL
"""
TAGS_SQL = """
SELECT t.id AS tag_id, t.slug, tt.locale, tt.name
  FROM tags t JOIN tag_translations tt ON tt.tag_id = t.id
"""
TAGGABLES_SQL = "SELECT tag_id, taggable_type, taggable_id FROM taggables"
SESSION_EVENTS_SQL = """
SELECT event_type, scene_id, artifact_id, tour_id, created_at FROM visit_events
 WHERE session_key = :session_key ORDER BY created_at, id LIMIT 2000
"""
TOUR_EVENTS_SQL = """
SELECT session_key, event_type, tour_id, step_order, created_at FROM visit_events
 WHERE event_type IN ('tour_start', 'tour_step', 'tour_complete')
   AND tour_id IS NOT NULL AND created_at >= :since
"""
TOUR_STEP_COUNTS_SQL = """
SELECT t.id AS tour_id, t.slug, COUNT(s.id) AS step_count
  FROM tours t LEFT JOIN tour_steps s ON s.tour_id = t.id
 WHERE t.deleted_at IS NULL
 GROUP BY t.id, t.slug
"""
SCENE_EVENTS_SQL = """
SELECT id, session_key, event_type, scene_id, duration_ms, created_at FROM visit_events
 WHERE event_type IN ('scene_enter', 'scene_leave') AND scene_id IS NOT NULL AND created_at >= :since
"""
EVENTS_SUMMARY_SQL = """
SELECT event_type, COUNT(*) AS events FROM visit_events WHERE created_at >= :since GROUP BY event_type
"""
SESSIONS_SQL = "SELECT COUNT(DISTINCT session_key) AS sessions FROM visit_events WHERE created_at >= :since"


@dataclass(frozen=True)
class CorpusFrames:
    """Dữ liệu nguồn cho chỉ mục tìm kiếm và bộ gợi ý tour (nạp một lần mỗi lần reindex)."""

    entities: pd.DataFrame
    interpretations: pd.DataFrame
    tours: pd.DataFrame
    posts: pd.DataFrame
    steps: pd.DataFrame
    artifact_scenes: pd.DataFrame
    taggables: pd.DataFrame
    tags: pd.DataFrame


def database_url() -> URL:
    return URL.create(
        "mysql+pymysql",
        username=os.getenv("DB_USERNAME", "baotang360"),
        password=os.getenv("DB_PASSWORD", ""),
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        database=os.getenv("DB_DATABASE", "baotang360"),
        query={"charset": "utf8mb4"},
    )


@lru_cache(maxsize=1)
def get_engine() -> Engine:
    return create_engine(
        database_url(), pool_pre_ping=True, pool_recycle=1800,
        connect_args={"connect_timeout": 5},
    )


def _read(engine: Engine, sql: str, params: dict | None = None) -> pd.DataFrame:
    with engine.connect() as conn:
        return pd.read_sql(text(sql), conn, params=params or {})


def _read_optional(engine: Engine, sql: str, columns: list[str], params: dict | None = None) -> pd.DataFrame:
    """Bảng/cột chưa tồn tại (migration chưa xong) → DataFrame rỗng; lỗi kết nối vẫn ném ra."""
    try:
        return _read(engine, sql, params)
    except DBAPIError as exc:
        code = exc.orig.args[0] if exc.orig is not None and exc.orig.args else None
        if code not in SCHEMA_ERROR_CODES:
            raise
        logger.warning("Bỏ qua truy vấn vì lược đồ chưa đủ: %s", exc.orig)
        return pd.DataFrame(columns=columns)


def normalize_morph(series: pd.Series) -> pd.Series:
    return series.astype(str).map(lambda v: MORPH_ALIASES.get(v, v))


def since(days: int) -> datetime:
    """Mốc thời gian UTC (naive, khớp cột TIMESTAMP của Laravel) cách hiện tại `days` ngày."""
    return (datetime.now(timezone.utc) - timedelta(days=days)).replace(tzinfo=None)


# ---------- Tài liệu tìm kiếm ----------

def build_documents(corpus: CorpusFrames) -> pd.DataFrame:
    """Ghép artifact/scene (interpretations) + tour (tour_translations) + post (blog_post_translations)."""
    frames = [_interpretation_docs(corpus.entities, corpus.interpretations),
              _translation_docs(corpus.tours, "tour"), _translation_docs(corpus.posts, "post")]
    frames = [f[DOCUMENT_COLUMNS[:-1]] for f in frames if not f.empty]
    if not frames:
        return pd.DataFrame(columns=DOCUMENT_COLUMNS + ["tag_ids"])
    docs = pd.concat(frames, ignore_index=True)
    docs = docs.assign(subject_id=docs["subject_id"].astype(int))
    docs = docs.merge(_tag_rows(corpus.taggables, corpus.tags), on=["subject_type", "subject_id", "locale"], how="left")
    docs = docs.assign(
        tags_text=docs["tags_text"].fillna(""),
        tag_ids=docs["tag_ids"].map(lambda v: v if isinstance(v, list) else []),
        summary=docs["summary"].fillna(""),
        body=docs["body"].fillna(""),
    )
    return docs[DOCUMENT_COLUMNS + ["tag_ids"]].reset_index(drop=True)


def _interpretation_docs(entities: pd.DataFrame, interpretations: pd.DataFrame) -> pd.DataFrame:
    if entities.empty or interpretations.empty:
        return pd.DataFrame()
    interp = interpretations.assign(subject_type=normalize_morph(interpretations["subject_type"]))
    return interp.merge(entities, on=["subject_type", "subject_id"], how="inner")


def _translation_docs(rows: pd.DataFrame, alias: str) -> pd.DataFrame:
    return pd.DataFrame() if rows.empty else rows.assign(subject_type=alias, depth=1)


def _tag_rows(taggables: pd.DataFrame, tags: pd.DataFrame) -> pd.DataFrame:
    """Mỗi (subject, locale) → chuỗi tên thẻ + danh sách tag_id."""
    cols = ["subject_type", "subject_id", "locale", "tags_text", "tag_ids"]
    if taggables.empty or tags.empty:
        return pd.DataFrame({c: pd.Series(dtype="int64" if c == "subject_id" else "object") for c in cols})
    tg = taggables.assign(subject_type=normalize_morph(taggables["taggable_type"]),
                          subject_id=taggables["taggable_id"].astype(int))
    joined = tg.merge(tags[["tag_id", "locale", "name"]], on="tag_id", how="inner")
    grouped = joined.groupby(["subject_type", "subject_id", "locale"])
    return grouped.agg(tags_text=("name", " ".join), tag_ids=("tag_id", lambda s: sorted(int(x) for x in s))
                       ).reset_index()[cols]


def load_corpus(engine: Engine | None = None) -> CorpusFrames:
    eng = engine or get_engine()
    doc_cols = ["subject_id", "slug", "locale", "title", "summary", "body"]
    return CorpusFrames(
        entities=_read(eng, PUBLISHED_ENTITIES_SQL),
        interpretations=_read(eng, INTERPRETATIONS_SQL),
        tours=_read_optional(eng, TOURS_SQL, doc_cols),
        posts=_read_optional(eng, POSTS_SQL, doc_cols),
        steps=_read_optional(eng, STEPS_SQL, ["tour_id", "step_order", "scene_id"]),
        artifact_scenes=_read_optional(eng, ARTIFACT_SCENES_SQL, ["artifact_id", "scene_id"]),
        taggables=_read_optional(eng, TAGGABLES_SQL, ["tag_id", "taggable_type", "taggable_id"]),
        tags=_read_optional(eng, TAGS_SQL, ["tag_id", "slug", "locale", "name"]),
    )


# ---------- Sự kiện hành vi (truy vấn có tham số, không ghép chuỗi) ----------

def load_session_events(session_key: str, engine: Engine | None = None) -> pd.DataFrame:
    return _read(engine or get_engine(), SESSION_EVENTS_SQL, {"session_key": session_key})


def load_tour_events(days: int, engine: Engine | None = None) -> tuple[pd.DataFrame, pd.DataFrame]:
    eng = engine or get_engine()
    return _read(eng, TOUR_EVENTS_SQL, {"since": since(days)}), _read(eng, TOUR_STEP_COUNTS_SQL)


def load_scene_events(days: int, engine: Engine | None = None) -> pd.DataFrame:
    return _read(engine or get_engine(), SCENE_EVENTS_SQL, {"since": since(days)})


def load_event_summary(days: int, engine: Engine | None = None) -> tuple[pd.DataFrame, int]:
    """(số sự kiện theo event_type, số phiên khác nhau) trong `days` ngày gần nhất."""
    eng = engine or get_engine()
    params = {"since": since(days)}
    by_type = _read_optional(eng, EVENTS_SUMMARY_SQL, ["event_type", "events"], params)
    sessions = _read_optional(eng, SESSIONS_SQL, ["sessions"], params)
    total = int(sessions["sessions"].iloc[0] or 0) if not sessions.empty else 0
    return by_type, total


# ---------- Bảng cho kiểm định / thống kê ----------

def load_tables(names: tuple[str, ...], engine: Engine | None = None) -> dict[str, pd.DataFrame]:
    """Nạp các bảng trong danh sách trắng, bỏ bản ghi đã xóa mềm."""
    eng = engine or get_engine()
    out: dict[str, pd.DataFrame] = {}
    for name in names:
        columns = TABLE_COLUMNS[name]
        where = " WHERE deleted_at IS NULL" if name in SOFT_DELETE_TABLES else ""
        df = _read_optional(eng, f"SELECT {', '.join(columns)} FROM {name}{where}", list(columns))
        if "subject_type" in df.columns:
            df = df.assign(subject_type=normalize_morph(df["subject_type"]))
        out[name] = df
    return out


def load_quality_tables(engine: Engine | None = None) -> dict[str, pd.DataFrame]:
    return load_tables(QUALITY_TABLES, engine)


def load_stats_tables(engine: Engine | None = None) -> dict[str, pd.DataFrame]:
    return load_tables(STATS_TABLES, engine)
