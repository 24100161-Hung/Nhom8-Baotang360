import pandas as pd

from app.recommend import TTLCache, TourRecommender, session_profile


def _ids(results):
    return [r["tour_id"] for r in results]


def test_similar_tour_excludes_itself_and_ranks_dinosaur_tour_first(recommender):
    results = recommender.similar_to_tour(101, "vi", 4)
    assert 101 not in _ids(results) and len(results) == 4
    assert results[0]["tour_id"] == 102
    assert results[0]["slug"] == "ky-phan-trang"
    assert 2 in results[0]["shared_scene_ids"]
    assert any(w.startswith("Cùng không gian: Kỷ nguyên khủng long") for w in results[0]["why"])
    assert any("khủng" in t or "khung" in t for t in results[0]["matched_terms"])
    scores = [r["score"] for r in results]
    assert scores == sorted(scores, reverse=True)


def test_similar_tour_english_and_unknown_tour(recommender):
    results = recommender.similar_to_tour(103, "en", 3)
    assert results[0]["why"] and results[0]["why"][0].startswith("Shared scene: ")
    assert recommender.similar_to_tour(999, "vi") == []
    assert TourRecommender().similar_to_tour(101, "vi") == []


def test_session_profile_ranks_ice_age_tour_and_skips_completed(recommender):
    events = pd.DataFrame([
        {"event_type": "scene_enter", "scene_id": 3, "artifact_id": None, "tour_id": None},
        {"event_type": "scene_enter", "scene_id": 3, "artifact_id": None, "tour_id": None},
        {"event_type": "artifact_view", "scene_id": None, "artifact_id": 11, "tour_id": None},
        {"event_type": "tour_complete", "scene_id": None, "artifact_id": None, "tour_id": 106},
    ])
    results = recommender.for_session("sess-ice", events, "vi", 3)
    assert results[0]["tour_id"] == 103
    assert 106 not in _ids(results)
    assert any(w.startswith("Bạn đã xem: Kỷ băng hà") for w in results[0]["why"])


def test_session_without_events_returns_empty(recommender):
    assert recommender.for_session("empty", pd.DataFrame(), "vi") == []


def test_session_profile_counts():
    events = pd.DataFrame([{"event_type": "artifact_view", "scene_id": None, "artifact_id": 10, "tour_id": None}])
    profile = session_profile(events, {10: 2})
    assert profile["artifacts"][10] == 1 and 2 in profile["scenes"]


def test_cache_hits_and_new_model_invalidates(recommender, corpus):
    first = recommender.similar_to_tour(101, "vi", 2)
    assert recommender.cache.get(("tour", 101, "vi", 2)) is first
    assert recommender.similar_to_tour(101, "vi", 2) is first
    rebuilt = TourRecommender.build(corpus.tours, corpus.steps, corpus.interpretations, corpus.artifact_scenes)
    assert rebuilt.cache.get(("tour", 101, "vi", 2)) is None


def test_ttl_cache_expires_and_evicts():
    cache = TTLCache(ttl=0, max_entries=2)
    cache.set(("a",), 1)
    assert cache.get(("a",)) is None
    cache = TTLCache(ttl=60, max_entries=2)
    for k in "abc":
        cache.set((k,), k)
    assert cache.get(("a",)) is None and cache.get(("c",)) == "c"
