import pandas as pd
import pytest
from fastapi.testclient import TestClient

from app import main
from app.tile_jobs import TileJobs

TOKEN = "test-token"
AUTH = {"X-Analytics-Token": TOKEN}


@pytest.fixture
def client(monkeypatch, index, recommender, clean_tables, tour_events, tour_meta, scene_events, tmp_path):
    monkeypatch.setenv("ANALYTICS_TOKEN", TOKEN)
    monkeypatch.setitem(main.STATE, "current", main.EngineState(index=index, recommender=recommender))
    monkeypatch.setattr(main.db, "load_quality_tables", lambda: clean_tables)
    monkeypatch.setattr(main.db, "load_stats_tables", lambda: clean_tables)
    monkeypatch.setattr(main.db, "load_event_summary",
                        lambda days: (pd.DataFrame([{"event_type": "scene_enter", "events": 3}]), 2))
    monkeypatch.setattr(main.db, "load_tour_events", lambda days: (tour_events, tour_meta))
    monkeypatch.setattr(main.db, "load_scene_events", lambda days: scene_events)
    monkeypatch.setattr(main.db, "load_session_events", lambda key: pd.DataFrame([
        {"event_type": "scene_enter", "scene_id": 3, "artifact_id": None, "tour_id": None}]))
    (tmp_path / "panoramas").mkdir()
    from tests.test_tiles import _panorama
    _panorama(512, 256).save(tmp_path / "panoramas" / "hall.jpg")
    monkeypatch.setattr(main, "TILE_JOBS", TileJobs(tmp_path))
    # Không dùng "with" → lifespan không chạy, không cần MySQL.
    return TestClient(main.app)


def test_healthz_needs_no_token(client):
    res = client.get("/healthz")
    assert res.status_code == 200
    body = res.json()
    assert body["status"] == "ok" and body["index_size"] > 0 and body["tours_indexed"] == 6


PROTECTED = [
    ("post", "/search", {"q": "trix", "locale": "vi"}),
    ("post", "/recommend/tours", {"tour_id": 101}),
    ("get", "/analytics/dropoff", None),
    ("get", "/analytics/scene-flow", None),
    ("post", "/tiles/generate", {"scene_id": 1, "panorama_path": "panoramas/hall.jpg"}),
    ("get", "/tiles/status/1", None),
    ("get", "/quality-report", None),
    ("post", "/reindex", None),
    ("get", "/stats/overview", None),
]


@pytest.mark.parametrize("method, url, body", PROTECTED)
@pytest.mark.parametrize("headers", [{}, {"X-Analytics-Token": "wrong"}])
def test_protected_endpoints_reject_missing_or_wrong_token(client, method, url, body, headers):
    kwargs = {"headers": headers, **({"json": body} if body else {})}
    assert getattr(client, method)(url, **kwargs).status_code == 401


def test_unset_server_token_fails_closed(client, monkeypatch):
    monkeypatch.delenv("ANALYTICS_TOKEN")
    assert client.post("/search", json={"q": "trix"}, headers={"X-Analytics-Token": ""}).status_code == 401


def test_search_contract(client):
    res = client.post("/search", json={"q": "khung long", "locale": "vi", "limit": 5,
                                       "types": ["artifact", "tour"]}, headers=AUTH)
    data = res.json()
    assert res.status_code == 200 and data["engine"] == "tfidf" and isinstance(data["took_ms"], int)
    assert set(data["results"][0]) == {"subject_type", "subject_id", "slug", "score", "matched_terms"}
    assert {r["subject_type"] for r in data["results"]} <= {"artifact", "tour"}


def test_search_validation(client):
    assert client.post("/search", json={"q": "", "locale": "vi"}, headers=AUTH).status_code == 422
    assert client.post("/search", json={"q": "a", "locale": "fr"}, headers=AUTH).status_code == 422
    assert client.post("/search", json={"q": "a", "types": ["site"]}, headers=AUTH).status_code == 422


def test_recommend_by_tour_and_session(client):
    by_tour = client.post("/recommend/tours", json={"tour_id": 101, "limit": 3}, headers=AUTH).json()
    assert by_tour["mode"] == "tour" and len(by_tour["results"]) == 3
    assert set(by_tour["results"][0]) >= {"tour_id", "slug", "score", "why"}
    by_session = client.post("/recommend/tours", json={"session_key": "abc-123"}, headers=AUTH).json()
    assert by_session["mode"] == "session" and by_session["results"][0]["tour_id"] == 103


@pytest.mark.parametrize("body", [{}, {"tour_id": 1, "session_key": "x"}, {"session_key": "bad key;"},
                                  {"tour_id": 0}, {"tour_id": 1, "limit": 50}])
def test_recommend_validation(client, body):
    assert client.post("/recommend/tours", json=body, headers=AUTH).status_code == 422


def test_analytics_contracts(client):
    drop = client.get("/analytics/dropoff?days=30", headers=AUTH).json()
    t = drop["tours"][0]
    assert drop["days"] == 30 and t["tour_id"] == 101 and t["worst_step"] == 2
    assert set(t["steps"][0]) == {"order", "reached", "dropped", "drop_rate"}
    flow = client.get("/analytics/scene-flow", headers=AUTH).json()
    assert flow["transitions"][0] == {"from_scene_id": 1, "to_scene_id": 2, "count": 2}
    assert {"scene_id", "enters", "avg_dwell_ms", "exit_rate"} <= set(flow["scenes"][0])
    assert client.get("/analytics/dropoff?days=0", headers=AUTH).status_code == 422
    assert client.get("/analytics/scene-flow?days=999", headers=AUTH).status_code == 422


def test_tiles_generate_runs_in_background_and_reports_status(client):
    res = client.post("/tiles/generate", json={"scene_id": 3, "panorama_path": "panoramas/hall.jpg"}, headers=AUTH)
    assert res.status_code == 202 and res.json()["state"] == "pending" and res.json()["queued"] is True
    status = client.get("/tiles/status/3", headers=AUTH).json()   # TestClient chạy BackgroundTasks xong mới trả
    assert status["state"] == "done" and status["max_level"] == 1 and status["cube_resolution"] == 160
    assert status["tiles_path"] == "tiles/3"
    assert client.get("/tiles/status/42", headers=AUTH).status_code == 404


def test_tiles_generate_validation(client):
    bad = client.post("/tiles/generate", json={"scene_id": 1, "panorama_path": "../x.jpg"}, headers=AUTH)
    missing = client.post("/tiles/generate", json={"scene_id": 1, "panorama_path": "panoramas/no.jpg"}, headers=AUTH)
    assert bad.status_code == 422 and missing.status_code == 404
    assert client.post("/tiles/generate", json={"scene_id": 0, "panorama_path": "a.jpg"}, headers=AUTH).status_code == 422


def test_quality_and_stats_contracts(client):
    report = client.get("/quality-report", headers=AUTH).json()
    assert report["summary"]["errors"] == 0 and len(report["checks"]) == 14
    stats = client.get("/stats/overview", headers=AUTH).json()
    assert set(stats) == {"scenes", "artifacts", "tours", "posts", "events", "generated_at"}
    assert stats["events"]["sessions"] == 2


def test_db_failures_return_503(client, monkeypatch):
    def boom(*_):
        raise ConnectionError("db down")
    monkeypatch.setattr(main.db, "load_tour_events", boom)
    monkeypatch.setattr(main.db, "load_session_events", boom)
    assert client.get("/analytics/dropoff", headers=AUTH).status_code == 503
    assert client.post("/recommend/tours", json={"session_key": "new-one"}, headers=AUTH).status_code == 503


def test_reindex_returns_503_when_db_down(client, monkeypatch):
    def boom():
        raise ConnectionError("db down")
    monkeypatch.setattr(main, "build_state", boom)
    assert client.post("/reindex", headers=AUTH).status_code == 503
    assert client.get("/healthz").json()["index_size"] > 0  # chỉ mục cũ được giữ


def test_reindex_swaps_state(client, monkeypatch, index, recommender):
    monkeypatch.setattr(main, "build_state", lambda: main.EngineState(index=index, recommender=recommender))
    res = client.post("/reindex", headers=AUTH)
    assert res.status_code == 200 and res.json() == {**res.json(), "indexed": index.size, "tours": 6}


def test_build_state_from_corpus(monkeypatch, corpus):
    monkeypatch.setattr(main.db, "load_corpus", lambda: corpus)
    state = main.build_state()
    assert state.index.size > 0 and state.recommender.size == 6


def test_lifespan_survives_db_failure(monkeypatch):
    def boom():
        raise ConnectionError("db down")
    monkeypatch.setattr(main, "build_state", boom)
    monkeypatch.setitem(main.STATE, "current", main.empty_state())
    with TestClient(main.app) as c:
        assert c.get("/healthz").json()["index_size"] == 0
