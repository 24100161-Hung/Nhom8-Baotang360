"""Gợi ý tour: TF-IDF (word + char n-gram, có bản không dấu) + cosine trên văn bản tour.

Văn bản tour = tên ×2 + mô tả + highlights + tên các không gian (scene) của các bước
+ tên hiện vật đặt trong các scene đó. Hai chế độ:
- theo tour: tour tương tự một tour cho trước (loại chính nó);
- theo phiên: hồ sơ quan tâm dựng từ scene_enter/artifact_view của một session_key.
"""
from __future__ import annotations

import threading
import time
from collections import Counter
from dataclasses import dataclass, field

import numpy as np
import pandas as pd

from .text import STOPWORDS, dual_form, fold, plain_text
from .vectorize import HybridMatrix, HybridVectorizer, similarity

LOCALES = ("vi", "en")
NAME_WEIGHT = 2
# Chế độ phiên: trộn độ giống văn bản với tỷ lệ scene của tour mà khách đã ghé.
TEXT_WEIGHT = 0.7
OVERLAP_WEIGHT = 0.3
MAX_EVENT_REPEAT = 3
MAX_TERMS = 5
MAX_SHARED = 3
MIN_SCORE = 0.01
CACHE_TTL_SECONDS = 1800
CACHE_MAX_ENTRIES = 512
WHY_LABELS = {
    "vi": {"scene": "Cùng không gian: ", "seen": "Bạn đã xem: ", "terms": "Từ khớp: "},
    "en": {"scene": "Shared scene: ", "seen": "You visited: ", "terms": "Matching terms: "},
}


class TTLCache:
    """Cache nội bộ có hạn dùng; đối tượng mới được tạo mỗi lần reindex nên tự invalidate."""

    def __init__(self, ttl: float = CACHE_TTL_SECONDS, max_entries: int = CACHE_MAX_ENTRIES) -> None:
        self.ttl, self.max_entries = ttl, max_entries
        self._data: dict[tuple, tuple[float, object]] = {}
        self._lock = threading.Lock()

    def get(self, key: tuple) -> object | None:
        with self._lock:
            hit = self._data.get(key)
            if hit is None or time.monotonic() - hit[0] > self.ttl:
                self._data.pop(key, None)
                return None
            return hit[1]

    def set(self, key: tuple, value: object) -> None:
        with self._lock:
            if len(self._data) >= self.max_entries:
                self._data.pop(next(iter(self._data)))
            self._data[key] = (time.monotonic(), value)

    def clear(self) -> None:
        with self._lock:
            self._data.clear()


@dataclass
class LocaleModel:
    tour_ids: list[int]
    vectorizer: HybridVectorizer
    matrix: HybridMatrix


@dataclass
class TourRecommender:
    """Mô hình bất biến sau khi build (kèm cache riêng)."""

    models: dict[str, LocaleModel] = field(default_factory=dict)
    slugs: dict[int, str] = field(default_factory=dict)
    tour_scenes: dict[int, list[int]] = field(default_factory=dict)
    scene_names: dict[tuple[int, str], str] = field(default_factory=dict)
    artifact_names: dict[tuple[int, str], str] = field(default_factory=dict)
    artifact_scene: dict[int, int] = field(default_factory=dict)
    cache: TTLCache = field(default_factory=TTLCache)

    @property
    def size(self) -> int:
        return len(self.slugs)

    @classmethod
    def build(cls, tours: pd.DataFrame, steps: pd.DataFrame, interpretations: pd.DataFrame,
              artifact_scenes: pd.DataFrame) -> "TourRecommender":
        """tours: (subject_id, slug, locale, title, summary, body); steps: (tour_id, step_order, scene_id);
        interpretations: (subject_type, subject_id, locale, depth, title); artifact_scenes: (artifact_id, scene_id)."""
        rec = cls(
            slugs=_slugs(tours), tour_scenes=_tour_scenes(steps),
            scene_names=_names(interpretations, "scene"), artifact_names=_names(interpretations, "artifact"),
            artifact_scene=_artifact_scene(artifact_scenes),
        )
        if not rec.slugs:
            return rec
        for locale in LOCALES:
            ids = sorted(rec.slugs)
            texts = [dual_form(rec._tour_text(tours, tid, locale)) for tid in ids]
            if any(t.strip() for t in texts):
                vectorizer = HybridVectorizer()
                rec.models[locale] = LocaleModel(ids, vectorizer, vectorizer.fit_transform(texts))
        return rec

    # ---------- dựng văn bản ----------

    def _name(self, names: dict[tuple[int, str], str], key: int, locale: str) -> str:
        """Tên theo locale, thiếu thì lấy locale còn lại."""
        for loc in (locale, *[x for x in LOCALES if x != locale]):
            if (key, loc) in names:
                return names[(key, loc)]
        return ""

    def _scene_artifacts(self, scene_id: int) -> list[int]:
        return [a for a, s in self.artifact_scene.items() if s == scene_id]

    def _tour_text(self, tours: pd.DataFrame, tour_id: int, locale: str) -> str:
        rows = tours[tours["subject_id"] == tour_id]
        row = rows[rows["locale"] == locale]
        row = row.iloc[0] if not row.empty else rows.iloc[0]
        parts = [plain_text(row.get("title"))] * NAME_WEIGHT
        parts += [plain_text(row.get("summary")), plain_text(row.get("body"))]
        for scene_id in self.tour_scenes.get(tour_id, []):
            parts.append(self._name(self.scene_names, scene_id, locale))
            parts += [self._name(self.artifact_names, a, locale) for a in self._scene_artifacts(scene_id)]
        return " ".join(p for p in parts if p)

    # ---------- gợi ý ----------

    def similar_to_tour(self, tour_id: int, locale: str = "vi", limit: int = 4) -> list[dict]:
        key = ("tour", int(tour_id), locale, int(limit))
        cached = self.cache.get(key)
        if cached is not None:
            return cached  # type: ignore[return-value]
        model = self.models.get(locale)
        if model is None or tour_id not in model.tour_ids:
            return []
        row = model.tour_ids.index(tour_id)
        query = HybridMatrix(word=model.matrix.word[row], char=model.matrix.char[row])
        scores = similarity(query, model.matrix)[0][0]
        base_scenes = set(self.tour_scenes.get(tour_id, []))
        results = []
        for i in np.argsort(-scores):
            other = model.tour_ids[int(i)]
            if other == tour_id or scores[i] < MIN_SCORE:
                continue
            shared = [s for s in self.tour_scenes.get(other, []) if s in base_scenes]
            terms = _matched_terms(model, query.word, int(i))
            results.append(self._result(other, float(scores[i]), shared, terms, locale, "scene"))
            if len(results) >= limit:
                break
        self.cache.set(key, results)
        return results

    def for_session(self, session_key: str, events: pd.DataFrame, locale: str = "vi", limit: int = 4) -> list[dict]:
        key = ("session", session_key, locale, int(limit))
        cached = self.cache.get(key)
        if cached is not None:
            return cached  # type: ignore[return-value]
        results = self._rank_session(events, locale, limit)
        self.cache.set(key, results)
        return results

    def cached_session(self, session_key: str, locale: str, limit: int) -> list[dict] | None:
        return self.cache.get(("session", session_key, locale, int(limit)))  # type: ignore[return-value]

    def _rank_session(self, events: pd.DataFrame, locale: str, limit: int) -> list[dict]:
        model = self.models.get(locale)
        profile = session_profile(events, self.artifact_scene)
        if model is None or not profile["scenes"] and not profile["artifacts"]:
            return []
        text = self._profile_text(profile, locale)
        if not text.strip():
            return []
        query = model.vectorizer.transform([dual_form(text)])
        text_scores = similarity(query, model.matrix)[0][0]
        visited = set(profile["scenes"])
        results = []
        for i, tid in enumerate(model.tour_ids):
            if tid in profile["completed"]:
                continue
            scenes = self.tour_scenes.get(tid, [])
            shared = [s for s in scenes if s in visited]
            overlap = len(set(shared)) / len(set(scenes)) if scenes else 0.0
            score = TEXT_WEIGHT * float(text_scores[i]) + OVERLAP_WEIGHT * overlap
            if score >= MIN_SCORE:
                results.append((score, tid, shared, i))
        results.sort(key=lambda r: (-r[0], r[1]))
        return [self._result(tid, score, shared, _matched_terms(model, query.word, i), locale, "seen")
                for score, tid, shared, i in results[:limit]]

    def _profile_text(self, profile: dict, locale: str) -> str:
        parts = []
        for scene_id, n in profile["scenes"].items():
            parts += [self._name(self.scene_names, scene_id, locale)] * min(n, MAX_EVENT_REPEAT)
        for artifact_id, n in profile["artifacts"].items():
            parts += [self._name(self.artifact_names, artifact_id, locale)] * min(n, MAX_EVENT_REPEAT)
        return " ".join(p for p in parts if p)

    def _result(self, tour_id: int, score: float, shared: list[int], terms: list[str],
                locale: str, kind: str) -> dict:
        labels = WHY_LABELS.get(locale, WHY_LABELS["en"])
        unique_shared = list(dict.fromkeys(shared))[:MAX_SHARED]
        why = [labels[kind] + self._name(self.scene_names, s, locale) for s in unique_shared
               if self._name(self.scene_names, s, locale)]
        if terms:
            why.append(labels["terms"] + ", ".join(terms))
        return {"tour_id": int(tour_id), "slug": self.slugs.get(tour_id, ""), "score": round(score, 4),
                "why": why, "shared_scene_ids": [int(s) for s in unique_shared], "matched_terms": terms}


def session_profile(events: pd.DataFrame, artifact_scene: dict[int, int]) -> dict:
    """Đếm scene đã vào (scene_enter + scene của hiện vật đã xem), hiện vật đã xem, tour đã hoàn thành."""
    profile: dict = {"scenes": Counter(), "artifacts": Counter(), "completed": set()}
    if events is None or events.empty:
        return profile
    for row in events.itertuples(index=False):
        etype = getattr(row, "event_type", None)
        scene_id, artifact_id = _int(getattr(row, "scene_id", None)), _int(getattr(row, "artifact_id", None))
        if etype == "scene_enter" and scene_id is not None:
            profile["scenes"][scene_id] += 1
        elif etype == "artifact_view" and artifact_id is not None:
            profile["artifacts"][artifact_id] += 1
            home = scene_id if scene_id is not None else artifact_scene.get(artifact_id)
            if home is not None:
                profile["scenes"][home] += 0  # ghi nhận scene liên quan mà không tăng trọng số văn bản
        elif etype == "tour_complete" and _int(getattr(row, "tour_id", None)) is not None:
            profile["completed"].add(_int(row.tour_id))
    return profile


def _matched_terms(model: LocaleModel, query_word, row: int) -> list[str]:
    """Từ đơn đóng góp nhiều nhất vào cosine (tích trọng số TF-IDF), gộp dạng có/không dấu."""
    product = query_word.multiply(model.matrix.word[row]).tocoo()
    if product.nnz == 0:
        return []
    names = model.vectorizer.word.get_feature_names_out()
    chosen: dict[str, tuple[float, str]] = {}
    for col, val in zip(product.col, product.data):
        term = str(names[col])
        if " " in term or len(term) < 3 or term in STOPWORDS:
            continue
        key = fold(term)
        best = chosen.get(key)
        # Ưu tiên dạng có dấu để hiển thị
        if best is None or val > best[0] + 1e-9 or (abs(val - best[0]) <= 1e-9 and term != key):
            chosen[key] = (float(val), term)
    ranked = sorted(chosen.values(), key=lambda x: -x[0])
    return [t for _, t in ranked[:MAX_TERMS]]


def _int(value: object) -> int | None:
    try:
        if value is None or (isinstance(value, float) and np.isnan(value)):
            return None
        return int(value)  # type: ignore[arg-type]
    except (TypeError, ValueError):
        return None


def _slugs(tours: pd.DataFrame) -> dict[int, str]:
    if tours is None or tours.empty:
        return {}
    first = tours.drop_duplicates("subject_id")
    return {int(i): str(s) for i, s in zip(first["subject_id"], first["slug"])}


def _tour_scenes(steps: pd.DataFrame) -> dict[int, list[int]]:
    if steps is None or steps.empty:
        return {}
    ordered = steps.sort_values(["tour_id", "step_order"])
    return {int(t): list(dict.fromkeys(int(s) for s in g["scene_id"].dropna()))
            for t, g in ordered.groupby("tour_id")}


def _names(interpretations: pd.DataFrame, alias: str) -> dict[tuple[int, str], str]:
    """Tên (title depth thấp nhất) của scene/artifact theo locale."""
    if interpretations is None or interpretations.empty:
        return {}
    df = interpretations[interpretations["subject_type"] == alias]
    if "depth" in df.columns:
        df = df.sort_values("depth")
    df = df.drop_duplicates(["subject_id", "locale"])
    return {(int(i), str(loc)): plain_text(t) for i, loc, t in zip(df["subject_id"], df["locale"], df["title"])}


def _artifact_scene(artifact_scenes: pd.DataFrame) -> dict[int, int]:
    if artifact_scenes is None or artifact_scenes.empty:
        return {}
    df = artifact_scenes.dropna(subset=["scene_id"])
    return {int(a): int(s) for a, s in zip(df["artifact_id"], df["scene_id"])}
