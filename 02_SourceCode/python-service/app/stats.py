"""Thống kê tổng quan cho dashboard quản trị (nội dung đã công bố + sự kiện 30 ngày)."""
from __future__ import annotations

from datetime import datetime, timezone

import pandas as pd

CONTENT_TABLES = {"scenes": "scenes", "artifacts": "artifacts", "tours": "tours", "posts": "blog_posts"}


def _count(df: pd.DataFrame | None) -> dict:
    if df is None or df.empty:
        return {"total": 0, "published": 0}
    published = int((df["status"] == "published").sum()) if "status" in df.columns else len(df)
    return {"total": int(len(df)), "published": published}


def compute_overview(tables: dict[str, pd.DataFrame], events_by_type: pd.DataFrame | None,
                     sessions: int, days: int = 30) -> dict:
    """`events_by_type`: (event_type, events) đã lọc trong `days` ngày; `sessions`: số phiên khác nhau."""
    by_type: dict[str, int] = {}
    if events_by_type is not None and not events_by_type.empty:
        by_type = {str(t): int(n) for t, n in zip(events_by_type["event_type"], events_by_type["events"])}
    overview = {key: _count(tables.get(name)) for key, name in CONTENT_TABLES.items()}
    return {
        **overview,
        "events": {
            "days": days,
            "total": sum(by_type.values()),
            "sessions": int(sessions),
            "by_type": dict(sorted(by_type.items())),
        },
        "generated_at": datetime.now(timezone.utc).isoformat(),
    }
