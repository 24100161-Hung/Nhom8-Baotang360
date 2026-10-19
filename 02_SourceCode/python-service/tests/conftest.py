"""Fixture DataFrame mô phỏng dữ liệu BaoTang360 đã nạp từ MySQL (không cần DB)."""
from __future__ import annotations

import pandas as pd
import pytest

from app.db import CorpusFrames, build_documents
from app.recommend import TourRecommender
from app.search import SearchIndex

SCENES = {  # id: (slug, tên vi, tên en)
    1: ("main-hall", "Sảnh chính", "Main Hall"),
    2: ("dinosaur-era", "Kỷ nguyên khủng long", "Dinosaur Era"),
    3: ("ice-age", "Kỷ băng hà", "Ice Age"),
    4: ("life", "Sự sống", "Life"),
    5: ("earth", "Trái Đất", "Earth"),
    6: ("death", "Cái chết", "Death"),
}
ARTIFACTS = {  # id: (slug, scene_id, tên vi, tên en, mô tả vi)
    10: ("trix", 2, "Khủng long bạo chúa Trix", "Tyrannosaurus rex Trix",
         "Bộ xương khủng long bạo chúa gần hoàn chỉnh từ kỷ Phấn Trắng, tìm thấy ở Montana."),
    11: ("woolly-mammoth", 3, "Voi ma mút lông xoăn", "Woolly mammoth",
         "Voi ma mút sống trong kỷ băng hà, có lớp lông dày chịu lạnh."),
    12: ("iron-meteorite", 5, "Thiên thạch sắt", "Iron meteorite",
         "Mảnh thiên thạch sắt niken rơi xuống Trái Đất."),
    13: ("triceratops", 2, "Hóa thạch Triceratops", "Triceratops fossil",
         "Hộp sọ khủng long ba sừng kỷ Phấn Trắng."),
    14: ("morpho-butterfly", 4, "Bướm Morpho xanh", "Blue morpho butterfly",
         "Côn trùng cánh vảy màu xanh ánh kim của rừng nhiệt đới."),
}
TOURS = {  # id: (slug, tên vi, mô tả vi, highlights vi, tên en, mô tả en, highlights en, scenes)
    101: ("hanh-trinh-khung-long", "Hành trình khủng long",
          "Gặp gỡ khủng long bạo chúa và các loài bò sát cổ đại.", '["Khủng long bạo chúa Trix", "Hóa thạch"]',
          "Dinosaur journey", "Meet the tyrannosaurus and ancient reptiles.", "Trix, fossils", [1, 2]),
    102: ("ky-phan-trang", "Kỷ Phấn Trắng và hóa thạch",
          "Hóa thạch khủng long và sự tuyệt chủng cuối kỷ Phấn Trắng.", "Triceratops, thiên thạch",
          "The Cretaceous", "Dinosaur fossils and the end-Cretaceous extinction.", "Triceratops", [2, 5]),
    103: ("ky-bang-ha", "Kỷ băng hà và voi ma mút",
          "Khám phá động vật thích nghi với giá lạnh của kỷ băng hà.", "Voi ma mút lông xoăn",
          "Ice age and mammoths", "Animals adapted to the cold of the ice age.", "Woolly mammoth", [1, 3]),
    104: ("su-song-con-trung", "Sự sống và côn trùng",
          "Đa dạng sinh học: côn trùng, bướm và chiến lược sinh tồn.", "Bướm Morpho",
          "Life and insects", "Biodiversity: insects, butterflies and survival.", "Blue morpho", [4]),
    105: ("trai-dat-thien-thach", "Trái Đất và thiên thạch",
          "Đá, khoáng vật và thiên thạch kể chuyện hành tinh.", "Thiên thạch sắt",
          "Earth and meteorites", "Rocks, minerals and meteorites tell the story of the planet.", "Iron meteorite",
          [5, 1]),
    106: ("tong-quan-bao-tang", "Tổng quan bảo tàng",
          "Một vòng qua tất cả các phòng trưng bày của Naturalis.", "Mọi phòng",
          "Museum overview", "A loop through every gallery of Naturalis.", "All galleries", [1, 2, 3, 4, 5, 6]),
}
POSTS = [
    {"subject_id": 201, "slug": "trix-tro-lai-leiden", "locale": "vi", "title": "Trix trở lại Leiden",
     "summary": "Câu chuyện bộ xương khủng long bạo chúa.",
     "body": "<p>Sau chuyến lưu diễn châu Âu, <b>Trix</b> về nhà.</p>"},
    {"subject_id": 201, "slug": "trix-tro-lai-leiden", "locale": "en", "title": "Trix returns to Leiden",
     "summary": "The story of our T. rex skeleton.", "body": "<p>After a European tour Trix came home.</p>"},
    {"subject_id": 202, "slug": "dem-bao-tang", "locale": "vi", "title": "Đêm bảo tàng 2026",
     "summary": "Sự kiện mở cửa ban đêm.", "body": "Tham quan bằng đèn pin và nghe kể chuyện."},
]


def _interp(stype: str, sid: int, locale: str, title: str, summary: str = "", body: str = "",
            depth: int = 1) -> dict:
    return {"subject_type": stype, "subject_id": sid, "locale": locale, "depth": depth,
            "title": title, "summary": summary, "body": body}


def _interpretations() -> pd.DataFrame:
    rows = []
    for sid, (_, vi, en) in SCENES.items():
        rows += [_interp("scene", sid, "vi", vi, f"Không gian {vi} của Naturalis."),
                 _interp("scene", sid, "en", en, f"The {en} gallery of Naturalis.")]
    for aid, (_, _, vi, en, desc) in ARTIFACTS.items():
        rows += [_interp("artifact", aid, "vi", vi, desc, desc), _interp("artifact", aid, "en", en, en)]
    # depth 2 của Trix: phải được gộp với depth 1 khi tìm kiếm
    rows.append(_interp("artifact", 10, "vi", "Trix – phân tích chuyên sâu", "", "Xương đùi và hộp sọ của Trix.", 2))
    return pd.DataFrame(rows)


def _corpus() -> CorpusFrames:
    entities = pd.DataFrame(
        [{"subject_type": "scene", "subject_id": i, "slug": s[0]} for i, s in SCENES.items()]
        + [{"subject_type": "artifact", "subject_id": i, "slug": a[0]} for i, a in ARTIFACTS.items()])
    tours = pd.DataFrame(
        [{"subject_id": tid, "slug": t[0], "locale": "vi", "title": t[1], "summary": t[2], "body": t[3]}
         for tid, t in TOURS.items()]
        + [{"subject_id": tid, "slug": t[0], "locale": "en", "title": t[4], "summary": t[5], "body": t[6]}
           for tid, t in TOURS.items()])
    steps = pd.DataFrame([{"tour_id": tid, "step_order": n, "scene_id": s}
                          for tid, t in TOURS.items() for n, s in enumerate(t[7], start=1)])
    return CorpusFrames(
        entities=entities, interpretations=_interpretations(), tours=tours, posts=pd.DataFrame(POSTS),
        steps=steps,
        artifact_scenes=pd.DataFrame([{"artifact_id": i, "scene_id": a[1]} for i, a in ARTIFACTS.items()]),
        taggables=pd.DataFrame([{"tag_id": 1, "taggable_type": "App\\Models\\Artifact", "taggable_id": 11},
                                {"tag_id": 1, "taggable_type": "tour", "taggable_id": 103}]),
        tags=pd.DataFrame([{"tag_id": 1, "slug": "dong-vat-co-vu", "locale": "vi", "name": "Động vật có vú"},
                           {"tag_id": 1, "slug": "dong-vat-co-vu", "locale": "en", "name": "Mammals"}]),
    )


@pytest.fixture(scope="session")
def corpus() -> CorpusFrames:
    return _corpus()


@pytest.fixture(scope="session")
def documents(corpus) -> pd.DataFrame:
    return build_documents(corpus)


@pytest.fixture(scope="session")
def index(documents) -> SearchIndex:
    return SearchIndex.build(documents)


@pytest.fixture
def recommender(corpus) -> TourRecommender:
    return TourRecommender.build(corpus.tours, corpus.steps, corpus.interpretations, corpus.artifact_scenes)


def _clean_tables() -> dict[str, pd.DataFrame]:
    """Bộ dữ liệu sạch: mọi phép kiểm phải ra 0."""
    scene_rows = [{"id": i, "slug": s[0], "status": "published", "panorama_path": f"panoramas/{s[0]}.jpg",
                   "tiles_path": f"tiles/{i}", "tile_config": '{"cubeResolution": 1296}',
                   "preview_path": f"tiles/{i}/preview.jpg"} for i, s in list(SCENES.items())[:2]]
    scene_rows.append({"id": 9, "slug": "draft-room", "status": "draft", "panorama_path": None,
                       "tiles_path": None, "tile_config": None, "preview_path": None})
    subjects = [(t, s, loc) for t, s in (("scene", 1), ("scene", 2), ("artifact", 10)) for loc in ("vi", "en")]
    interp = [{"id": n, "subject_type": t, "subject_id": s, "locale": loc, "depth_level": 1, "title": "x",
               "is_machine_translated": 0, "reviewed_by": None} for n, (t, s, loc) in enumerate(subjects, start=1)]
    media = [{"id": n, "subject_type": "scene", "subject_id": s, "media_type": "audio", "locale": loc,
              "license": "cc_by_sa_3", "source_name": "Naturalis", "source_url": None}
             for n, (s, loc) in enumerate([(s, loc) for s in (1, 2) for loc in ("vi", "en")], start=1)]
    return {
        "scenes": pd.DataFrame(scene_rows),
        "hotspots": pd.DataFrame([
            {"id": 1, "scene_id": 1, "type": "scene", "pitch": -5.5, "yaw": 120.0, "target_scene_id": 2,
             "artifact_id": None, "status": "published"},
            {"id": 2, "scene_id": 2, "type": "artifact", "pitch": 10.0, "yaw": -179.9, "target_scene_id": None,
             "artifact_id": 10, "status": "published"},
            {"id": 3, "scene_id": 9, "type": "scene", "pitch": 0, "yaw": 0, "target_scene_id": 9,
             "artifact_id": None, "status": "published"},  # nằm trong scene draft → bỏ qua
        ]),
        "artifacts": pd.DataFrame([{"id": 10, "slug": "trix", "status": "published", "scene_id": 2},
                                   {"id": 11, "slug": "draft-artifact", "status": "draft", "scene_id": 2}]),
        "interpretations": pd.DataFrame(interp),
        "media_assets": pd.DataFrame(media),
        "tours": pd.DataFrame([{"id": 101, "slug": "hanh-trinh-khung-long", "status": "published"},
                               {"id": 109, "slug": "nhap", "status": "draft"}]),
        "tour_steps": pd.DataFrame([{"id": 1, "tour_id": 101, "step_order": 1, "scene_id": 1},
                                    {"id": 2, "tour_id": 101, "step_order": 2, "scene_id": 2},
                                    {"id": 3, "tour_id": 109, "step_order": 1, "scene_id": 9}]),
        "visit_days": pd.DataFrame([{"id": 1, "visit_date": "2026-11-03", "capacity": 200, "sold": 200,
                                     "status": "sold_out"}]),
        "orders": pd.DataFrame([{"id": 1, "code": "OD-AB12CD34EF", "status": "paid"},
                                {"id": 2, "code": "OD-ZZ99YY88XX", "status": "pending"}]),
        "order_items": pd.DataFrame([{"id": 1, "order_id": 1}, {"id": 2, "order_id": 2}]),
        "tickets": pd.DataFrame([{"id": 1, "order_item_id": 1}]),
        "blog_posts": pd.DataFrame([{"id": 201, "slug": "trix-tro-lai-leiden", "status": "published"}]),
    }


@pytest.fixture
def clean_tables() -> dict[str, pd.DataFrame]:
    return _clean_tables()


def _tour_events() -> pd.DataFrame:
    """Tour 101 có 4 bước: 10 phiên bắt đầu, 8 tới bước 2, chỉ 3 tới bước 3 (rơi rụng rõ ở bước 2), 2 hoàn thành."""
    rows = []
    reach = {f"s{i}": (1 if i > 8 else 2 if i > 3 else 4) for i in range(1, 11)}
    t0 = pd.Timestamp("2026-10-10 09:00:00")
    for n, (session, last) in enumerate(reach.items()):
        at = t0 + pd.Timedelta(minutes=n)
        rows.append({"session_key": session, "event_type": "tour_start", "tour_id": 101, "step_order": None,
                     "created_at": at})
        rows += [{"session_key": session, "event_type": "tour_step", "tour_id": 101, "step_order": k,
                  "created_at": at + pd.Timedelta(seconds=30 * k)} for k in range(1, last + 1)]
    rows += [{"session_key": s, "event_type": "tour_complete", "tour_id": 101, "step_order": 4,
              "created_at": t0 + pd.Timedelta(hours=1)} for s in ("s1", "s2")]
    return pd.DataFrame(rows)


@pytest.fixture
def tour_events() -> pd.DataFrame:
    return _tour_events()


@pytest.fixture
def tour_meta() -> pd.DataFrame:
    return pd.DataFrame([{"tour_id": 101, "slug": "hanh-trinh-khung-long", "step_count": 4},
                         {"tour_id": 102, "slug": "ky-phan-trang", "step_count": 2}])


@pytest.fixture
def scene_events() -> pd.DataFrame:
    """A: 1→2→3, B: 1→2, C: chỉ 1. Scene 1 có scene_leave (9 s, 21 s); scene 2 dùng hiệu thời gian."""
    t0 = pd.Timestamp("2026-10-10 10:00:00")

    def ev(i, s, t, scene, sec, dur=None):
        return {"id": i, "session_key": s, "event_type": t, "scene_id": scene, "duration_ms": dur,
                "created_at": t0 + pd.Timedelta(seconds=sec)}
    return pd.DataFrame([
        ev(1, "A", "scene_enter", 1, 0), ev(2, "A", "scene_leave", 1, 9, 9000), ev(3, "A", "scene_enter", 2, 10),
        ev(4, "A", "scene_enter", 3, 40),
        ev(5, "B", "scene_enter", 1, 0), ev(6, "B", "scene_leave", 1, 21, 21000), ev(7, "B", "scene_enter", 2, 20),
        ev(8, "C", "scene_enter", 1, 0),
    ])
