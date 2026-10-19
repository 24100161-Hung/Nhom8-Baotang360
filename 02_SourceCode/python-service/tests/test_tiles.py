import json
import time

import numpy as np
import pytest
from PIL import Image

from app.tile_jobs import TileJobs, resolve_panorama
from app.tiles import cube_resolution_for, generate_tiles, level_count, project_face


def _panorama(width: int, height: int) -> Image.Image:
    """Ảnh thử: R theo kinh độ, G theo vĩ độ, B dải sọc – liên tục trừ đường nối kinh tuyến 180°."""
    x = np.linspace(0, 1, width, dtype=np.float32)[None, :].repeat(height, 0)
    y = np.linspace(0, 1, height, dtype=np.float32)[:, None].repeat(width, 1)
    rgb = np.stack([np.sin(np.pi * x) * 255, y * 255, (np.cos(6 * np.pi * x) * 0.5 + 0.5) * 255], axis=-1)
    return Image.fromarray(rgb.astype(np.uint8))


@pytest.fixture
def storage(tmp_path):
    (tmp_path / "public" / "panoramas").mkdir(parents=True)
    _panorama(512, 256).save(tmp_path / "public" / "panoramas" / "test.jpg", quality=92)
    return tmp_path / "public"


def test_cube_resolution_and_levels_follow_generate_py():
    assert cube_resolution_for(4096) == 1296
    assert level_count(1296, 512) == 3
    assert level_count(1024, 512) == 2
    assert level_count(160, 512) == 1          # ảnh nhỏ: 1 level
    assert level_count(160, 64) == 3


def test_generate_single_level_tiles_and_config(storage):
    out = storage / "tiles" / "7"
    result = generate_tiles(storage / "panoramas" / "test.jpg", out, base_path="/storage/tiles/7")
    assert (result.cube_resolution, result.max_level, result.tile_count) == (160, 1, 6)
    for face in "frblud":
        assert Image.open(out / "1" / face / "0_0.jpg").size == (160, 160)
        assert (out / "fallback" / f"{face}.jpg").is_file()
    assert Image.open(out / "preview.jpg").size == (512, 256)
    config = json.loads((out / "config.json").read_text())
    assert config["type"] == "multires"
    assert config["multiRes"] == {"basePath": "/storage/tiles/7", "path": "/%l/%s/%y_%x",
                                  "fallbackPath": "/fallback/%s", "extension": "jpg",
                                  "tileResolution": 512, "maxLevel": 1, "cubeResolution": 160}
    assert not list(storage.joinpath("tiles").glob(".*tmp"))


def test_generate_multi_level_tile_counts(storage):
    out = storage / "tiles" / "8"
    result = generate_tiles(storage / "panoramas" / "test.jpg", out, tile_resolution=64)
    assert result.max_level == 3 and result.tile_count == 6 * (9 + 4 + 1)
    assert len(list(out.glob("*/*/*.jpg"))) == 84
    assert Image.open(out / "3" / "f" / "2_2.jpg").size == (32, 32)    # ô mép: 160 − 2·64
    assert Image.open(out / "1" / "u" / "0_0.jpg").size == (40, 40)


def test_regenerate_replaces_previous_output(storage):
    out = storage / "tiles" / "9"
    generate_tiles(storage / "panoramas" / "test.jpg", out, tile_resolution=64)
    generate_tiles(storage / "panoramas" / "test.jpg", out)
    assert not (out / "3").exists() and (out / "1" / "f" / "0_0.jpg").is_file()


def test_cube_faces_are_continuous_at_seams():
    equi = np.asarray(_panorama(1024, 512))
    n = 128
    faces = {f: project_face(equi, f, n).astype(int) for f in "frblud"}
    def diff(a, b):
        return float(np.abs(a - b).mean())
    assert diff(faces["f"][:, -1], faces["r"][:, 0]) < 4       # f phải ↔ r trái
    assert diff(faces["l"][:, -1], faces["f"][:, 0]) < 4       # l phải ↔ f trái
    assert diff(faces["u"][-1], faces["f"][0]) < 4             # u dưới ↔ f trên
    assert diff(faces["d"][0], faces["f"][-1]) < 4             # d trên ↔ f dưới
    assert diff(faces["f"][:, -1], faces["l"][:, 0]) > 10      # cạnh không kề phải khác rõ
    centre = equi[256, 512].astype(int)                         # yaw 0, pitch 0 = tâm ảnh
    assert np.abs(faces["f"][n // 2, n // 2] - centre).max() < 6


def test_rejects_non_equirectangular(tmp_path):
    Image.new("RGB", (300, 300)).save(tmp_path / "square.jpg")
    with pytest.raises(ValueError):
        generate_tiles(tmp_path / "square.jpg", tmp_path / "out")


@pytest.mark.parametrize("bad", ["../secret.jpg", "/etc/passwd.jpg", "panoramas/../../x.jpg",
                                 "panoramas/test.txt", "pano ramas/a.jpg", ""])
def test_resolve_panorama_blocks_traversal(storage, bad):
    with pytest.raises(ValueError):
        resolve_panorama(storage, bad)


def test_resolve_panorama_accepts_public_prefix_and_missing_file(storage):
    assert resolve_panorama(storage, "public/panoramas/test.jpg").name == "test.jpg"
    with pytest.raises(FileNotFoundError):
        resolve_panorama(storage, "panoramas/none.jpg")


def test_job_lifecycle_and_status_file(storage):
    jobs = TileJobs(storage)
    job, source = jobs.submit(5, "panoramas/test.jpg")
    assert job["state"] == "pending" and source is not None
    again, dup = jobs.submit(5, "panoramas/test.jpg")
    assert dup is None and again["state"] == "pending"          # không xếp hàng trùng
    jobs.run(5, source)
    status = jobs.status(5)
    assert status["state"] == "done" and status["max_level"] == 1 and status["cube_resolution"] == 160
    assert TileJobs(storage).status(5)["state"] == "done"       # đọc lại từ tệp JSON sau khởi động lại
    assert TileJobs(storage).status(77) is None


def test_job_error_state(storage, tmp_path):
    Image.new("RGB", (100, 100)).save(storage / "panoramas" / "square.jpg")
    jobs = TileJobs(storage)
    _, source = jobs.submit(6, "panoramas/square.jpg")
    jobs.run(6, source)
    assert jobs.status(6)["state"] == "error" and "2:1" in jobs.status(6)["error"]


def test_interrupted_job_reported_as_error(storage):
    jobs = TileJobs(storage)
    jobs.submit(11, "panoramas/test.jpg")
    assert TileJobs(storage).status(11)["state"] == "error"


def test_4096_panorama_under_60_seconds(tmp_path):
    src = tmp_path / "big.jpg"
    _panorama(4096, 2048).save(src, quality=90)
    start = time.perf_counter()
    result = generate_tiles(src, tmp_path / "out")
    elapsed = time.perf_counter() - start
    assert result.cube_resolution == 1296 and result.max_level == 3
    assert result.tile_count == 6 * (9 + 4 + 1)
    assert elapsed < 60, f"sinh tiles mất {elapsed:.1f}s"
