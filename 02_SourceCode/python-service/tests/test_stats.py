import pandas as pd

from app.stats import compute_overview


def test_overview_counts_content_and_events(clean_tables):
    by_type = pd.DataFrame([{"event_type": "scene_enter", "events": 40}, {"event_type": "tour_start", "events": 5}])
    out = compute_overview(clean_tables, by_type, sessions=12, days=30)
    assert out["scenes"] == {"total": 3, "published": 2}
    assert out["artifacts"] == {"total": 2, "published": 1}
    assert out["tours"] == {"total": 2, "published": 1}
    assert out["posts"] == {"total": 1, "published": 1}
    assert out["events"] == {"days": 30, "total": 45, "sessions": 12,
                             "by_type": {"scene_enter": 40, "tour_start": 5}}


def test_overview_with_empty_database():
    out = compute_overview({}, None, 0)
    assert out["scenes"] == {"total": 0, "published": 0} and out["events"]["total"] == 0
