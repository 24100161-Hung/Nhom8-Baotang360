"""Phân tích luồng tham quan từ visit_events (pandas): phễu rơi rụng của tour và luồng chuyển cảnh."""
from __future__ import annotations

import numpy as np
import pandas as pd

TOUR_EVENT_COLUMNS = ["session_key", "event_type", "tour_id", "step_order", "created_at"]
SCENE_EVENT_COLUMNS = ["id", "session_key", "event_type", "scene_id", "duration_ms", "created_at"]


def _rate(num: float, den: float) -> float:
    return round(float(num) / float(den), 4) if den else 0.0


# ---------- Phễu rơi rụng ----------

def tour_dropoff(events: pd.DataFrame, tours: pd.DataFrame | None = None) -> dict:
    """Phễu theo tour.

    starts = số phiên có tour_start; reached[k] = số phiên đạt bước có step_order ≥ k
    (phiên có tour_start coi như đạt bước 1, phiên tour_complete coi như đạt bước cuối);
    completes = số phiên có tour_complete; drop_rate[k] = (reached[k] − reached[k+1]) / reached[k],
    với bước cuối reached[n+1] := completes. worst_step = bước có drop_rate lớn nhất.
    `tours`: (tour_id, slug, step_count) để biết số bước và giữ cả tour chưa có sự kiện.
    """
    ev = _prepare_tour_events(events)
    meta = _tour_meta(tours)
    tour_ids = sorted(set(meta) | set(int(t) for t in ev["tour_id"].unique()))
    result = [_one_tour(tid, ev[ev["tour_id"] == tid], meta.get(tid, {})) for tid in tour_ids]
    result.sort(key=lambda t: (-t["starts"], t["tour_id"]))
    return {"tours": result}


def _prepare_tour_events(events: pd.DataFrame | None) -> pd.DataFrame:
    if events is None or events.empty:
        return pd.DataFrame(columns=TOUR_EVENT_COLUMNS)
    ev = events.dropna(subset=["tour_id", "session_key"])
    return ev.assign(tour_id=ev["tour_id"].astype(int),
                     step_order=pd.to_numeric(ev["step_order"], errors="coerce"))


def _tour_meta(tours: pd.DataFrame | None) -> dict[int, dict]:
    if tours is None or tours.empty:
        return {}
    return {int(r.tour_id): {"slug": r.slug, "step_count": int(r.step_count or 0)}
            for r in tours.itertuples(index=False)}


def _sessions(ev: pd.DataFrame, event_type: str) -> set:
    return set(ev.loc[ev["event_type"] == event_type, "session_key"])


def _one_tour(tour_id: int, ev: pd.DataFrame, meta: dict) -> dict:
    started, completed = _sessions(ev, "tour_start"), _sessions(ev, "tour_complete")
    steps = ev[ev["event_type"] == "tour_step"].dropna(subset=["step_order"])
    max_step = steps.groupby("session_key")["step_order"].max().astype(int).to_dict()
    observed = max(max_step.values(), default=0)
    n_steps = max(int(meta.get("step_count") or 0), observed, 1 if started or completed else 0)
    for session in started:
        max_step[session] = max(max_step.get(session, 0), 1)
    for session in completed:
        max_step[session] = n_steps
    reach = np.array(list(max_step.values()), dtype=int)
    reached = [int((reach >= k).sum()) for k in range(1, n_steps + 1)]
    step_rows = []
    for k in range(n_steps):
        nxt = reached[k + 1] if k + 1 < n_steps else len(completed)
        dropped = max(reached[k] - nxt, 0)
        step_rows.append({"order": k + 1, "reached": reached[k], "dropped": dropped,
                          "drop_rate": _rate(dropped, reached[k])})
    return {
        "tour_id": int(tour_id), "slug": meta.get("slug"),
        "starts": len(started), "completes": len(completed),
        "completion_rate": _rate(len(completed), len(started)),
        "steps": step_rows, "worst_step": _worst(step_rows),
    }


def _worst(steps: list[dict]) -> int | None:
    candidates = [s for s in steps if s["reached"] > 0 and s["dropped"] > 0]
    if not candidates:
        return None
    return max(candidates, key=lambda s: (s["drop_rate"], s["dropped"], -s["order"]))["order"]


# ---------- Luồng chuyển cảnh ----------

def scene_flow(events: pd.DataFrame) -> dict:
    """Ma trận chuyển from→to theo thứ tự scene_enter trong cùng session_key,
    số lần vào, thời gian dừng trung bình, tỷ lệ thoát (số phiên kết thúc tại scene / số lần vào)."""
    if events is None or events.empty:
        return {"transitions": [], "scenes": []}
    ev = events.dropna(subset=["scene_id", "session_key"]).assign(
        scene_id=lambda d: d["scene_id"].astype(int),
        created_at=lambda d: pd.to_datetime(d["created_at"]),
    )
    sort_cols = ["session_key", "created_at"] + (["id"] if "id" in ev.columns else [])
    enters = ev[ev["event_type"] == "scene_enter"].sort_values(sort_cols)
    if enters.empty:
        return {"transitions": [], "scenes": []}
    enters = enters.assign(
        next_scene=enters.groupby("session_key")["scene_id"].shift(-1),
        next_at=enters.groupby("session_key")["created_at"].shift(-1),
    )
    return {"transitions": _transitions(enters), "scenes": _scene_stats(enters, ev)}


def _transitions(enters: pd.DataFrame) -> list[dict]:
    moves = enters.dropna(subset=["next_scene"])
    moves = moves[moves["scene_id"] != moves["next_scene"].astype(int)]
    if moves.empty:
        return []
    counts = moves.groupby(["scene_id", moves["next_scene"].astype(int)]).size().reset_index(name="count")
    counts.columns = ["from_scene_id", "to_scene_id", "count"]
    counts = counts.sort_values(["count", "from_scene_id", "to_scene_id"], ascending=[False, True, True])
    return [{k: int(v) for k, v in row.items()} for row in counts.to_dict("records")]


def _scene_stats(enters: pd.DataFrame, ev: pd.DataFrame) -> list[dict]:
    enter_counts = enters.groupby("scene_id").size()
    exits = enters[enters["next_scene"].isna()].groupby("scene_id").size()
    leaves = ev[(ev["event_type"] == "scene_leave")]
    leave_dwell = pd.to_numeric(leaves["duration_ms"], errors="coerce").groupby(leaves["scene_id"]).mean() \
        if not leaves.empty else pd.Series(dtype=float)
    gaps = (enters["next_at"] - enters["created_at"]).dt.total_seconds() * 1000
    gap_dwell = gaps.groupby(enters["scene_id"]).mean()
    rows = []
    for scene_id, n in enter_counts.items():
        dwell = leave_dwell.get(scene_id, np.nan)
        if pd.isna(dwell):
            dwell = gap_dwell.get(scene_id, np.nan)
        rows.append({
            "scene_id": int(scene_id), "enters": int(n),
            "avg_dwell_ms": None if pd.isna(dwell) else int(round(float(dwell))),
            "exits": int(exits.get(scene_id, 0)),
            "exit_rate": _rate(exits.get(scene_id, 0), n),
        })
    rows.sort(key=lambda r: (-r["enters"], r["scene_id"]))
    return rows
