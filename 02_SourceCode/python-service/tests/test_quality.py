import pandas as pd
import pytest

from app.quality import CHECKS, build_report


def _counts(tables):
    return {c["code"]: c["count"] for c in build_report(tables)["checks"]}


def test_clean_dataset_has_no_findings(clean_tables):
    report = build_report(clean_tables)
    assert report["summary"] == {"errors": 0, "warnings": 0, "checks": len(CHECKS)}


def _with(tables, name, **changes):
    return {**tables, name: tables[name].assign(**changes)}


@pytest.mark.parametrize("code, mutate", [
    ("Q01_SCENE_MISSING_TILES", lambda t: _with(t, "scenes", tile_config=[None, "{}", None])),
    ("Q02_SCENE_MISSING_PREVIEW", lambda t: _with(t, "scenes", preview_path=["", "p.jpg", None])),
    ("Q03_SCENE_MISSING_AUDIO_VI", lambda t: {**t, "media_assets": t["media_assets"][t["media_assets"]["locale"] != "vi"]}),
    ("Q05_HOTSPOT_OUT_OF_RANGE", lambda t: _with(t, "hotspots", pitch=[95.0, 10.0, 0.0])),
    ("Q06_HOTSPOT_TARGET_UNPUBLISHED", lambda t: _with(t, "hotspots", target_scene_id=[9, None, 9])),
    ("Q07_TOUR_STEP_UNPUBLISHED_SCENE", lambda t: _with(t, "tour_steps", scene_id=[1, 9, 9])),
    ("Q08_MISSING_DEPTH1_VI", lambda t: {**t, "interpretations": t["interpretations"].iloc[1:]}),
    ("Q09_MISSING_DEPTH1_EN", lambda t: _with(t, "interpretations", depth_level=[1, 2, 1, 1, 1, 1])),
    ("Q10_MEDIA_MISSING_LICENSE", lambda t: _with(t, "media_assets", license=[None, "cc0", "cc0", " "])),
    ("Q11_MEDIA_MISSING_SOURCE", lambda t: _with(t, "media_assets", source_name=[None, "x", "x", "x"])),
    ("Q12_VISIT_DAY_OVERSOLD", lambda t: _with(t, "visit_days", sold=[201])),
    ("Q13_PAID_ORDER_NO_TICKET", lambda t: {**t, "tickets": pd.DataFrame(columns=["id", "order_item_id"])}),
    ("Q14_MT_UNREVIEWED", lambda t: _with(t, "interpretations", is_machine_translated=[1, 0, 0, 0, 0, 0])),
])
def test_each_check_detects_its_problem(clean_tables, code, mutate):
    counts = _counts(mutate(clean_tables))
    assert counts[code] >= 1
    assert sum(v for k, v in counts.items() if k != code) == 0


def test_samples_are_readable_and_limited(clean_tables):
    many = pd.DataFrame([{"id": i, "visit_date": f"2026-12-{i:02d}", "capacity": 1, "sold": 2, "status": "open"}
                         for i in range(1, 16)])
    check = next(c for c in build_report({**clean_tables, "visit_days": many})["checks"]
                 if c["code"] == "Q12_VISIT_DAY_OVERSOLD")
    assert check["count"] == 15 and len(check["samples"]) == 10 and check["samples"][0] == "2026-12-01: 2/1"


def test_missing_tables_do_not_crash():
    report = build_report({})
    assert report["summary"]["errors"] == 0
