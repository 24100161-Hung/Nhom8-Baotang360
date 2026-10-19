"""Hàng đợi sinh tiles: chạy nền, tối đa 1 job đồng thời, trạng thái trong bộ nhớ + tệp JSON."""
from __future__ import annotations

import json
import logging
import os
import re
import threading
import time
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath

from .tiles import generate_tiles

logger = logging.getLogger("baotang360.analytics.tiles")

ALLOWED_SUFFIXES = (".jpg", ".jpeg", ".png", ".webp")
_PATH_RE = re.compile(r"^[A-Za-z0-9_\-./]+$")
ACTIVE_STATES = ("pending", "running")
PUBLIC_URL_PREFIX = os.getenv("TILES_PUBLIC_PREFIX", "/storage")


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def public_root() -> Path:
    """Gốc `storage/app/public`: STORAGE_ROOT trỏ storage/app (có thư mục public) hoặc trỏ thẳng public."""
    root = Path(os.getenv("STORAGE_ROOT", "/srv/storage"))
    return root / "public" if (root / "public").is_dir() else root


def resolve_panorama(root: Path, panorama_path: str) -> Path:
    """Kiểm tra đường dẫn tương đối (chống path traversal) và trả đường dẫn tuyệt đối của ảnh."""
    rel = panorama_path.strip()
    for prefix in ("public/", "storage/"):
        if rel.startswith(prefix):
            rel = rel[len(prefix):]
    parts = PurePosixPath(rel).parts
    if not rel or not _PATH_RE.match(rel) or rel.startswith("/") or ".." in parts or "." in parts:
        raise ValueError("panorama_path không hợp lệ")
    if not rel.lower().endswith(ALLOWED_SUFFIXES):
        raise ValueError("panorama_path phải là ảnh jpg/png/webp")
    base = root.resolve()
    target = (base / rel).resolve()
    if not target.is_relative_to(base):
        raise ValueError("panorama_path nằm ngoài thư mục lưu trữ")
    if not target.is_file():
        raise FileNotFoundError("Không tìm thấy ảnh panorama")
    return target



def _generate_worker(source: str, out_dir: str, base_path: str, conn) -> None:
    """Chạy trong tiến trình con: bộ nhớ numpy/PIL được trả lại hệ điều hành khi tiến trình kết thúc."""
    try:
        r = generate_tiles(Path(source), Path(out_dir), base_path=base_path)
        conn.send(("ok", {"max_level": r.max_level, "cube_resolution": r.cube_resolution,
                          "tile_resolution": r.tile_resolution, "tile_count": r.tile_count,
                          "multi_res": r.config["multiRes"]}))
    except BaseException as exc:  # noqa: BLE001 – báo mọi lỗi về tiến trình cha
        conn.send(("error", str(exc)[:300]))
    finally:
        conn.close()


def _generate_isolated(source: Path, out_dir: Path, base_path: str) -> dict:
    """Sinh tiles trong tiến trình con (fork) để mỗi job dùng RAM độc lập; TILES_IN_PROCESS=1 chạy trực tiếp (test)."""
    if os.getenv("TILES_IN_PROCESS") == "1":
        r = generate_tiles(source, out_dir, base_path=base_path)
        return {"max_level": r.max_level, "cube_resolution": r.cube_resolution, "tile_resolution": r.tile_resolution,
                "tile_count": r.tile_count, "multi_res": r.config["multiRes"]}
    import multiprocessing as mp
    ctx = mp.get_context("fork")
    parent, child = ctx.Pipe(duplex=False)
    proc = ctx.Process(target=_generate_worker, args=(str(source), str(out_dir), base_path, child), daemon=True)
    proc.start()
    child.close()
    try:
        kind, payload = parent.recv()
    except EOFError:  # tiến trình con bị kill (OOM) trước khi gửi kết quả
        proc.join()
        raise RuntimeError(f"tiến trình sinh tiles kết thúc bất thường (exit {proc.exitcode}); thử giảm TILES_MAX_INPUT_WIDTH")
    proc.join()
    if kind != "ok":
        raise RuntimeError(payload)
    return payload

class TileJobs:
    """Quản lý job theo scene_id. `submit` ghi trạng thái pending; `run` (BackgroundTasks) chờ khóa rồi chạy."""

    def __init__(self, root: Path | None = None) -> None:
        self._root = root
        self._jobs: dict[int, dict] = {}
        self._state_lock = threading.Lock()
        self._run_lock = threading.Lock()  # giới hạn 1 job đồng thời; job sau xếp hàng chờ

    @property
    def root(self) -> Path:
        return self._root or public_root()

    def tiles_dir(self, scene_id: int) -> Path:
        return self.root / "tiles" / str(scene_id)

    def _status_file(self, scene_id: int) -> Path:
        return self.root / "tiles" / ".status" / f"{scene_id}.json"

    def _set(self, scene_id: int, **fields) -> dict:
        with self._state_lock:
            job = self._merge(scene_id, fields)
        self._persist(job)
        return job

    def _merge(self, scene_id: int, fields: dict) -> dict:
        """Gọi khi đang giữ _state_lock."""
        job = {**self._jobs.get(scene_id, {}), **fields, "scene_id": scene_id, "updated_at": _now()}
        self._jobs[scene_id] = job
        return job

    def _persist(self, job: dict) -> None:
        try:
            path = self._status_file(job["scene_id"])
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(json.dumps(job, ensure_ascii=False), encoding="utf-8")
        except OSError as exc:  # không ghi được tệp trạng thái thì vẫn giữ trong bộ nhớ
            logger.warning("Không ghi được trạng thái tiles: %s", exc)

    def submit(self, scene_id: int, panorama_path: str) -> tuple[dict, Path | None]:
        """Trả (trạng thái, ảnh nguồn) – ảnh nguồn None nghĩa là đã có job đang chờ/chạy cho scene này."""
        source = resolve_panorama(self.root, panorama_path)
        with self._state_lock:
            current = self._jobs.get(scene_id)
            if current and current.get("state") in ACTIVE_STATES:
                return current, None
            job = self._merge(scene_id, {"state": "pending", "panorama_path": panorama_path, "queued_at": _now(),
                                         "started_at": None, "finished_at": None, "error": None})
        self._persist(job)
        return job, source

    def run(self, scene_id: int, source: Path) -> None:
        with self._run_lock:
            self._set(scene_id, state="running", started_at=_now())
            start = time.perf_counter()
            try:
                result = _generate_isolated(source, self.tiles_dir(scene_id), f"{PUBLIC_URL_PREFIX}/tiles/{scene_id}")
            except Exception as exc:  # lỗi ảnh/IO/hết RAM: ghi trạng thái error, không làm sập worker
                logger.exception("Sinh tiles thất bại cho scene %s", scene_id)
                self._set(scene_id, state="error", error=str(exc)[:300], finished_at=_now())
                return
            self._set(
                scene_id, state="done", finished_at=_now(), duration_ms=int((time.perf_counter() - start) * 1000),
                max_level=result["max_level"], cube_resolution=result["cube_resolution"],
                tile_resolution=result["tile_resolution"], tile_count=result["tile_count"],
                tiles_path=f"tiles/{scene_id}", config_path=f"tiles/{scene_id}/config.json",
                preview_path=f"tiles/{scene_id}/preview.jpg", tile_config=result["multi_res"],
            )

    def status(self, scene_id: int) -> dict | None:
        with self._state_lock:
            job = self._jobs.get(scene_id)
        if job:
            return job
        try:
            saved = json.loads(self._status_file(scene_id).read_text(encoding="utf-8"))
        except (OSError, ValueError):
            return self._status_from_disk(scene_id)
        if saved.get("state") in ACTIVE_STATES:  # job bị ngắt do dịch vụ khởi động lại
            return self._status_from_disk(scene_id) or {**saved, "state": "error", "error": "interrupted"}
        if saved.get("state") == "error":
            return self._status_from_disk(scene_id) or saved
        return saved

    def _status_from_disk(self, scene_id: int) -> dict | None:
        """Tiles có sẵn (ví dụ sinh từ trước khi dịch vụ khởi động lại) → done."""
        try:
            config = json.loads((self.tiles_dir(scene_id) / "config.json").read_text(encoding="utf-8"))
        except (OSError, ValueError):
            return None
        multi = config.get("multiRes", {})
        return {"scene_id": scene_id, "state": "done", "max_level": multi.get("maxLevel"),
                "cube_resolution": multi.get("cubeResolution"), "tile_resolution": multi.get("tileResolution"),
                "tiles_path": f"tiles/{scene_id}", "tile_config": multi}
