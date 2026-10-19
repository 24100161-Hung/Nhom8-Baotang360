"""Sinh multi-resolution tiles cho Pannellum từ ảnh equirectangular (PIL + numpy).

Thuật toán viết lại gọn từ utils/multires/generate.py của Pannellum
(https://github.com/mpetroff/pannellum, Copyright (c) 2014-2025 Matthew Petroff, giấy phép MIT):
- cubeResolution = 8 · ⌊(rộng / π) / 8⌋ (giữ độ phân giải góc của ảnh gốc);
- số level = ⌈log2(cube / tile)⌉ + 1, bỏ bớt 1 level nếu level thấp thứ hai đúng bằng tile;
- mỗi level giảm nửa kích thước mặt, cắt thành ô tileResolution, đặt tên %l/%s/%y_%x.
Khác bản gốc: mặt cube được chiếu trực tiếp bằng numpy (nội suy song tuyến) thay vì gọi Hugin nona,
preview là ảnh equirectangular thu nhỏ thay vì SHT hash.
"""
from __future__ import annotations

import json
import math
import os
import shutil
from dataclasses import dataclass
from pathlib import Path

import numpy as np
from PIL import Image

FACES = ("f", "r", "b", "l", "u", "d")
TILE_RESOLUTION = 512
FALLBACK_SIZE = 1024
PREVIEW_WIDTH = 1024
JPEG_QUALITY = 80
EXTENSION = "jpg"
MAX_PIXELS = 72_000_000  # ~12000×6000; ảnh lớn hơn dễ làm container hết RAM
ASPECT_TOLERANCE = 0.02
Image.MAX_IMAGE_PIXELS = MAX_PIXELS


@dataclass(frozen=True)
class TileResult:
    cube_resolution: int
    tile_resolution: int
    max_level: int
    tile_count: int
    config: dict


def cube_resolution_for(width: int) -> int:
    return max(8, 8 * int(width / math.pi / 8))


def level_count(cube_resolution: int, tile_resolution: int) -> int:
    levels = int(math.ceil(math.log(float(cube_resolution) / tile_resolution, 2))) + 1
    if levels > 1 and round(cube_resolution / 2 ** (levels - 2)) == tile_resolution:
        levels -= 1  # tránh level thấp nhất quá nhỏ (như generate.py)
    return max(1, levels)


def _face_vectors(face: str, n: int) -> tuple[np.ndarray, np.ndarray, np.ndarray]:
    """Hướng nhìn (x phải, y lên, z trước) cho từng pixel của một mặt cube.

    Quy ước Pannellum: f yaw 0, r yaw 90°, b 180°, l −90°; u nhìn lên (cạnh dưới giáp f),
    d nhìn xuống (cạnh trên giáp f).
    """
    a = (np.arange(n, dtype=np.float32) + 0.5) / n * 2 - 1
    col, row = np.meshgrid(a, a)
    one = np.ones_like(col)
    vectors = {
        "f": (col, -row, one), "r": (one, -row, -col), "b": (-col, -row, -one),
        "l": (-one, -row, col), "u": (col, one, row), "d": (col, -one, -row),
    }
    return vectors[face]


def project_face(equi: np.ndarray, face: str, n: int, chunk_rows: int = 256) -> np.ndarray:
    """Chiếu một mặt cube n×n từ mảng equirectangular H×W×3 (nội suy song tuyến, quấn ngang).
    Xử lý theo từng dải hàng (chunk_rows) để đỉnh bộ nhớ chỉ vài chục MB thay vì ~400 MB với mặt 2000×2000
    (container PaaS 1 GB RAM từng bị OOM-kill khi chiếu cả mặt một lần)."""
    h, w = equi.shape[:2]
    x, y, z = _face_vectors(face, n)
    out = np.empty((n, n, 3), dtype=np.uint8)
    for r0 in range(0, n, chunk_rows):
        r1 = min(n, r0 + chunk_rows)
        xs, ys, zs = x[r0:r1], y[r0:r1], z[r0:r1]
        yaw = np.arctan2(xs, zs)
        pitch = np.arctan2(ys, np.hypot(xs, zs))
        u = (yaw / (2 * np.pi) + 0.5) * w - 0.5
        v = (0.5 - pitch / np.pi) * h - 0.5
        x0 = np.floor(u).astype(np.int32)
        y0 = np.floor(v).astype(np.int32)
        fx = (u - x0)[..., None].astype(np.float32)
        fy = (v - y0)[..., None].astype(np.float32)
        x0w, x1w = x0 % w, (x0 + 1) % w
        y0c, y1c = np.clip(y0, 0, h - 1), np.clip(y0 + 1, 0, h - 1)
        top = equi[y0c, x0w].astype(np.float32) * (1 - fx) + equi[y0c, x1w].astype(np.float32) * fx
        bottom = equi[y1c, x0w].astype(np.float32) * (1 - fx) + equi[y1c, x1w].astype(np.float32) * fx
        out[r0:r1] = np.clip(top * (1 - fy) + bottom * fy + 0.5, 0, 255).astype(np.uint8)
    return out


MAX_INPUT_WIDTH = int(os.getenv("TILES_MAX_INPUT_WIDTH", "8192"))  # thu nhỏ ảnh rộng hơn (PaaS ít RAM: đặt 4096)


def load_equirectangular(path: Path) -> Image.Image:
    with Image.open(path) as img:
        width, height = img.size
        if width * height > MAX_PIXELS:
            raise ValueError(f"Ảnh quá lớn ({width}×{height})")
        if abs(width / height - 2) > ASPECT_TOLERANCE * 2:
            raise ValueError(f"Ảnh không phải equirectangular 2:1 ({width}×{height})")
        rgb = img.convert("RGB")
        if width > MAX_INPUT_WIDTH:
            rgb = rgb.resize((MAX_INPUT_WIDTH, MAX_INPUT_WIDTH // 2), Image.LANCZOS)
        return rgb


def _save_levels(face_img: Image.Image, face: str, out: Path, cube: int, tile: int, levels: int) -> int:
    count, size, img = 0, cube, face_img
    for level in range(levels, 0, -1):
        if level < levels:
            img = img.resize((size, size), Image.LANCZOS)
        tiles = int(math.ceil(size / tile))
        face_dir = out / str(level) / face
        face_dir.mkdir(parents=True, exist_ok=True)
        for y in range(tiles):
            for x in range(tiles):
                box = (x * tile, y * tile, min((x + 1) * tile, size), min((y + 1) * tile, size))
                img.crop(box).save(face_dir / f"{y}_{x}.{EXTENSION}", quality=JPEG_QUALITY)
                count += 1
        size = int(size / 2)
    return count


def build_config(cube: int, tile: int, levels: int, base_path: str) -> dict:
    return {
        "type": "multires",
        "hfov": 100.0,
        "multiRes": {
            "basePath": base_path,
            "path": "/%l/%s/%y_%x",
            "fallbackPath": "/fallback/%s",
            "extension": EXTENSION,
            "tileResolution": tile,
            "maxLevel": levels,
            "cubeResolution": cube,
        },
        "attribution": "Tiles generated with an adaptation of Pannellum generate.py (MIT, Matthew Petroff)",
    }


def generate_tiles(source: Path, out_dir: Path, base_path: str = "",
                   tile_resolution: int = TILE_RESOLUTION) -> TileResult:
    """Sinh toàn bộ tiles vào thư mục tạm rồi thay thế `out_dir` nguyên khối (không để lại bản dở)."""
    image = load_equirectangular(source)
    cube = cube_resolution_for(image.width)
    levels = level_count(cube, tile_resolution)
    tmp = out_dir.with_name(f".{out_dir.name}.tmp")
    shutil.rmtree(tmp, ignore_errors=True)
    tmp.mkdir(parents=True)
    try:
        equi = np.asarray(image)
        fallback_dir = tmp / "fallback"
        fallback_dir.mkdir()
        count = 0
        for face in FACES:
            face_img = Image.fromarray(project_face(equi, face, cube))
            count += _save_levels(face_img, face, tmp, cube, tile_resolution, levels)
            fb = face_img if cube <= FALLBACK_SIZE else face_img.resize((FALLBACK_SIZE,) * 2, Image.LANCZOS)
            fb.save(fallback_dir / f"{face}.{EXTENSION}", quality=JPEG_QUALITY)
        preview_w = min(PREVIEW_WIDTH, image.width)
        image.resize((preview_w, preview_w // 2), Image.LANCZOS).save(tmp / "preview.jpg", quality=JPEG_QUALITY)
        config = build_config(cube, tile_resolution, levels, base_path)
        (tmp / "config.json").write_text(json.dumps(config, indent=2), encoding="utf-8")
        _swap_dir(tmp, out_dir)
    except BaseException:
        shutil.rmtree(tmp, ignore_errors=True)
        raise
    return TileResult(cube, tile_resolution, levels, count, config)


def _swap_dir(tmp: Path, out_dir: Path) -> None:
    old = out_dir.with_name(f".{out_dir.name}.old")
    shutil.rmtree(old, ignore_errors=True)
    if out_dir.exists():
        out_dir.rename(old)
    tmp.rename(out_dir)
    shutil.rmtree(old, ignore_errors=True)
