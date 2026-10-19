import pandas as pd

from app.flow import scene_flow, tour_dropoff


def test_dropoff_funnel_and_worst_step(tour_events, tour_meta):
    tours = {t["tour_id"]: t for t in tour_dropoff(tour_events, tour_meta)["tours"]}
    t = tours[101]
    assert t["starts"] == 10 and t["completes"] == 2 and t["completion_rate"] == 0.2
    assert [s["reached"] for s in t["steps"]] == [10, 8, 3, 3]
    assert [s["dropped"] for s in t["steps"]] == [2, 5, 0, 1]
    assert [s["drop_rate"] for s in t["steps"]] == [0.2, 0.625, 0.0, 0.3333]
    assert t["worst_step"] == 2 and t["slug"] == "hanh-trinh-khung-long"


def test_dropoff_keeps_tours_without_events(tour_events, tour_meta):
    result = tour_dropoff(tour_events, tour_meta)["tours"]
    assert [t["tour_id"] for t in result] == [101, 102]
    empty = result[1]
    assert empty["starts"] == 0 and empty["worst_step"] is None
    assert [s["reached"] for s in empty["steps"]] == [0, 0]


def test_dropoff_handles_empty_input_and_unknown_tour():
    assert tour_dropoff(pd.DataFrame(), None) == {"tours": []}
    ev = pd.DataFrame([{"session_key": "x", "event_type": "tour_start", "tour_id": 7, "step_order": None,
                        "created_at": pd.Timestamp("2026-10-01")}])
    t = tour_dropoff(ev, None)["tours"][0]
    assert t["tour_id"] == 7 and t["slug"] is None and t["steps"][0]["drop_rate"] == 1.0 and t["worst_step"] == 1


def test_scene_flow_transitions(scene_events):
    flow = scene_flow(scene_events)
    assert flow["transitions"] == [
        {"from_scene_id": 1, "to_scene_id": 2, "count": 2},
        {"from_scene_id": 2, "to_scene_id": 3, "count": 1},
    ]


def test_scene_flow_enters_dwell_and_exit_rate(scene_events):
    scenes = {s["scene_id"]: s for s in scene_flow(scene_events)["scenes"]}
    assert {k: v["enters"] for k, v in scenes.items()} == {1: 3, 2: 2, 3: 1}
    assert scenes[1]["avg_dwell_ms"] == 15000      # trung bình scene_leave.duration_ms
    assert scenes[2]["avg_dwell_ms"] == 30000      # hiệu thời gian tới scene kế tiếp
    assert scenes[3]["avg_dwell_ms"] is None
    assert scenes[1]["exit_rate"] == 0.3333 and scenes[2]["exit_rate"] == 0.5 and scenes[3]["exit_rate"] == 1.0


def test_scene_flow_ignores_reload_of_same_scene_and_empty():
    t = pd.Timestamp("2026-10-01")
    ev = pd.DataFrame([{"id": i, "session_key": "z", "event_type": "scene_enter", "scene_id": 4, "duration_ms": None,
                        "created_at": t + pd.Timedelta(seconds=i)} for i in range(2)])
    assert scene_flow(ev)["transitions"] == []
    assert scene_flow(pd.DataFrame()) == {"transitions": [], "scenes": []}
