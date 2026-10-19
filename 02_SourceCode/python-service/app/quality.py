"""Kiểm định chất lượng dữ liệu BaoTang360 trên các DataFrame (không phụ thuộc MySQL)."""
from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Callable

import pandas as pd

MAX_SAMPLES = 10
PITCH_RANGE = (-90.0, 90.0)
YAW_RANGE = (-180.0, 180.0)
Tables = dict[str, pd.DataFrame]


@dataclass(frozen=True)
class CheckResult:
    code: str
    severity: str
    title: str
    count: int
    samples: list[str]

    def to_dict(self) -> dict:
        return {"code": self.code, "severity": self.severity, "title": self.title,
                "count": self.count, "samples": self.samples}


@dataclass(frozen=True)
class CheckSpec:
    code: str
    severity: str
    title: str
    run: Callable[[Tables], list[str]]


def _table(tables: Tables, name: str) -> pd.DataFrame:
    df = tables.get(name)
    return df if df is not None else pd.DataFrame()


def _num(series: pd.Series) -> pd.Series:
    return pd.to_numeric(series, errors="coerce")


def _blank(series: pd.Series) -> pd.Series:
    return series.isna() | (series.astype(str).str.strip().isin(["", "null", "None"]))


def _published(df: pd.DataFrame) -> pd.DataFrame:
    if df.empty or "status" not in df.columns:
        return df
    return df[df["status"] == "published"]


def _published_ids(tables: Tables, name: str) -> set[int]:
    pub = _published(_table(tables, name))
    return set() if pub.empty else {int(i) for i in _num(pub["id"]).dropna()}


# ---------- Không gian 360 ----------

def check_scene_tiles(tables: Tables) -> list[str]:
    scenes = _published(_table(tables, "scenes"))
    if scenes.empty:
        return []
    bad = scenes[_blank(scenes["tiles_path"]) | _blank(scenes["tile_config"])]
    return [f"scene:{s}" for s in bad["slug"]]


def check_scene_preview(tables: Tables) -> list[str]:
    scenes = _published(_table(tables, "scenes"))
    return [] if scenes.empty else [f"scene:{s}" for s in scenes[_blank(scenes["preview_path"])]["slug"]]


def _scene_audio(locale: str) -> Callable[[Tables], list[str]]:
    def run(tables: Tables) -> list[str]:
        scenes = _published(_table(tables, "scenes"))
        media = _table(tables, "media_assets")
        if scenes.empty:
            return []
        have: set[int] = set()
        if not media.empty:
            ok = media[(media["subject_type"] == "scene") & (media["media_type"] == "audio")
                       & (media["locale"] == locale)]
            have = {int(i) for i in _num(ok["subject_id"]).dropna()}
        return [f"scene:{s}" for i, s in zip(_num(scenes["id"]), scenes["slug"]) if int(i) not in have]
    return run


def check_hotspot_range(tables: Tables) -> list[str]:
    hs = _table(tables, "hotspots")
    if hs.empty:
        return []
    pitch, yaw = _num(hs["pitch"]), _num(hs["yaw"])
    bad = (pitch.isna() | yaw.isna() | (pitch < PITCH_RANGE[0]) | (pitch > PITCH_RANGE[1])
           | (yaw < YAW_RANGE[0]) | (yaw > YAW_RANGE[1]))
    return [f"hotspot#{i} (scene#{s}): pitch={p}, yaw={y}"
            for i, s, p, y in zip(hs["id"][bad], hs["scene_id"][bad], pitch[bad], yaw[bad])]


def check_hotspot_target_draft(tables: Tables) -> list[str]:
    """Hotspot kiểu scene (đang hiển thị trong scene đã công bố) trỏ tới scene chưa công bố/không tồn tại."""
    hs = _published(_table(tables, "hotspots"))
    if hs.empty:
        return []
    live = _published_ids(tables, "scenes")
    links = hs[(hs["type"] == "scene") & _num(hs["scene_id"]).isin(live)]
    bad = links[~_num(links["target_scene_id"]).isin(live)]
    return [f"hotspot#{i} (scene#{s}) → scene#{t}"
            for i, s, t in zip(bad["id"], bad["scene_id"], bad["target_scene_id"])]


# ---------- Tour, hiện vật, media ----------

def check_tour_step_draft(tables: Tables) -> list[str]:
    tours, steps = _published(_table(tables, "tours")), _table(tables, "tour_steps")
    if tours.empty or steps.empty:
        return []
    live = _published_ids(tables, "scenes")
    merged = steps.merge(tours[["id", "slug"]].rename(columns={"id": "tour_id", "slug": "tour"}), on="tour_id")
    bad = merged[~_num(merged["scene_id"]).isin(live)]
    return [f"tour:{t} #{o} → scene#{s}" for t, o, s in zip(bad["tour"], bad["step_order"], bad["scene_id"])]


def _missing_depth1(locale: str) -> Callable[[Tables], list[str]]:
    """Hiện vật và không gian đã công bố thiếu diễn giải depth 1 theo locale."""
    def run(tables: Tables) -> list[str]:
        interp = _table(tables, "interpretations")
        have: set[tuple[str, int]] = set()
        if not interp.empty:
            ok = interp[(interp["locale"] == locale) & (_num(interp["depth_level"]) == 1) & ~_blank(interp["title"])]
            have = {(str(t), int(i)) for t, i in zip(ok["subject_type"], _num(ok["subject_id"]))}
        out = []
        for alias, name in (("artifact", "artifacts"), ("scene", "scenes")):
            pub = _published(_table(tables, name))
            if not pub.empty:
                out += [f"{alias}:{s}" for i, s in zip(_num(pub["id"]), pub["slug"]) if (alias, int(i)) not in have]
        return out
    return run


def _media_label(media: pd.DataFrame) -> list[str]:
    return [f"media#{i} ({t}:{s})" for i, t, s in zip(media["id"], media["subject_type"], media["subject_id"])]


def check_media_license(tables: Tables) -> list[str]:
    media = _table(tables, "media_assets")
    return [] if media.empty else _media_label(media[_blank(media["license"])])


def check_media_source(tables: Tables) -> list[str]:
    media = _table(tables, "media_assets")
    if media.empty:
        return []
    return _media_label(media[_blank(media["source_name"]) & _blank(media["source_url"])])


def check_machine_translation(tables: Tables) -> list[str]:
    interp = _table(tables, "interpretations")
    if interp.empty:
        return []
    mt = _num(interp["is_machine_translated"]).fillna(0).astype(int) == 1
    bad = interp[mt & interp["reviewed_by"].isna()]
    return [f"{t}#{s} {loc} depth {d}" for t, s, loc, d in
            zip(bad["subject_type"], bad["subject_id"], bad["locale"], bad["depth_level"])]


# ---------- Vé, đơn hàng ----------

def check_visit_day_oversold(tables: Tables) -> list[str]:
    days = _table(tables, "visit_days")
    if days.empty:
        return []
    bad = days[_num(days["sold"]) > _num(days["capacity"])]
    return [f"{d}: {s}/{c}" for d, s, c in zip(bad["visit_date"], bad["sold"], bad["capacity"])]


def check_paid_order_without_ticket(tables: Tables) -> list[str]:
    orders = _table(tables, "orders")
    if orders.empty:
        return []
    paid = orders[orders["status"] == "paid"]
    items, tickets = _table(tables, "order_items"), _table(tables, "tickets")
    with_ticket: set[int] = set()
    if not items.empty and not tickets.empty:
        joined = items.merge(tickets, left_on="id", right_on="order_item_id", suffixes=("", "_t"))
        with_ticket = {int(o) for o in _num(joined["order_id"]).dropna()}
    return [f"order:{c}" for i, c in zip(_num(paid["id"]), paid["code"]) if int(i) not in with_ticket]


CHECKS: tuple[CheckSpec, ...] = (
    CheckSpec("Q01_SCENE_MISSING_TILES", "warning", "Không gian công bố chưa có multires tiles (tiles_path/tile_config)", check_scene_tiles),
    CheckSpec("Q02_SCENE_MISSING_PREVIEW", "warning", "Không gian công bố thiếu ảnh preview", check_scene_preview),
    CheckSpec("Q03_SCENE_MISSING_AUDIO_VI", "warning", "Không gian công bố thiếu audio guide tiếng Việt", _scene_audio("vi")),
    CheckSpec("Q04_SCENE_MISSING_AUDIO_EN", "warning", "Không gian công bố thiếu audio guide tiếng Anh", _scene_audio("en")),
    CheckSpec("Q05_HOTSPOT_OUT_OF_RANGE", "error", "Hotspot có pitch ngoài [-90, 90] hoặc yaw ngoài [-180, 180]", check_hotspot_range),
    CheckSpec("Q06_HOTSPOT_TARGET_UNPUBLISHED", "error", "Hotspot chuyển cảnh trỏ tới không gian chưa công bố", check_hotspot_target_draft),
    CheckSpec("Q07_TOUR_STEP_UNPUBLISHED_SCENE", "error", "Tour công bố có bước trỏ không gian chưa công bố", check_tour_step_draft),
    CheckSpec("Q08_MISSING_DEPTH1_VI", "error", "Hiện vật/không gian công bố thiếu diễn giải depth 1 tiếng Việt", _missing_depth1("vi")),
    CheckSpec("Q09_MISSING_DEPTH1_EN", "error", "Hiện vật/không gian công bố thiếu diễn giải depth 1 tiếng Anh", _missing_depth1("en")),
    CheckSpec("Q10_MEDIA_MISSING_LICENSE", "error", "Media thiếu giấy phép (license)", check_media_license),
    CheckSpec("Q11_MEDIA_MISSING_SOURCE", "warning", "Media thiếu nguồn (source_name và source_url)", check_media_source),
    CheckSpec("Q12_VISIT_DAY_OVERSOLD", "error", "Ngày tham quan có sold > capacity", check_visit_day_oversold),
    CheckSpec("Q13_PAID_ORDER_NO_TICKET", "error", "Đơn hàng đã thanh toán nhưng chưa sinh vé", check_paid_order_without_ticket),
    CheckSpec("Q14_MT_UNREVIEWED", "warning", "Bản dịch máy chưa được duyệt", check_machine_translation),
)


def run_checks(tables: Tables) -> list[CheckResult]:
    results = []
    for spec in CHECKS:
        items = spec.run(tables)
        results.append(CheckResult(spec.code, spec.severity, spec.title, len(items), items[:MAX_SAMPLES]))
    return results


def build_report(tables: Tables) -> dict:
    """errors/warnings = tổng số bản ghi vi phạm theo mức độ."""
    results = run_checks(tables)
    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "summary": {
            "errors": sum(r.count for r in results if r.severity == "error"),
            "warnings": sum(r.count for r in results if r.severity == "warning"),
            "checks": len(results),
        },
        "checks": [r.to_dict() for r in results],
    }
