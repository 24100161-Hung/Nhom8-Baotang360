from app.search import SearchIndex


def _keys(results):
    return [(r["subject_type"], r["slug"]) for r in results]


def test_documents_cover_four_subject_types_in_two_locales(documents):
    assert set(documents["subject_type"]) == {"artifact", "tour", "scene", "post"}
    assert set(documents["locale"]) == {"vi", "en"}
    # alias lớp App\Models\Artifact được quy về 'artifact' khi ghép thẻ
    mammoth = documents[(documents["slug"] == "woolly-mammoth") & (documents["locale"] == "vi")]
    assert mammoth.iloc[0]["tags_text"] == "Động vật có vú"


def test_unaccented_query_finds_trix_and_dinosaur_tour(index):
    results = index.search("khung long bao chua", "vi", 10)
    # Tour 101 có "khủng long bạo chúa" trong mô tả và highlights nên đứng cùng nhóm đầu với hiện vật Trix
    assert set(_keys(results[:2])) == {("artifact", "trix"), ("tour", "hanh-trinh-khung-long")}
    assert {"khung", "long", "bao", "chua"} <= set(results[0]["matched_terms"])


def test_accented_query_gives_same_top_result(index):
    accented = [r["slug"] for r in index.search("Khủng long bạo chúa", "vi")]
    plain = [r["slug"] for r in index.search("khung long bao chua", "vi")]
    assert accented[:2] == plain[:2]


def test_multiple_depths_are_merged_into_one_subject(index):
    results = index.search("trix", "vi", 20)
    keys = [(r["subject_type"], r["subject_id"]) for r in results]
    assert len(keys) == len(set(keys))
    assert ("artifact", 10) in keys and ("post", 201) in keys


def test_types_filter_and_english_locale(index):
    assert _keys(index.search("trix", "vi", 20, ["post"])) == [("post", "trix-tro-lai-leiden")]
    en = index.search("mammoth", "en")
    assert en[0]["slug"] == "woolly-mammoth"
    assert ("tour", "ky-bang-ha") in _keys(en)
    assert index.search("ice age", "en", 5, ["scene"])[0]["slug"] == "ice-age"


def test_tag_names_and_html_body_are_indexed(index):
    assert index.search("dong vat co vu", "vi")[0]["slug"] in {"woolly-mammoth", "ky-bang-ha"}
    assert index.search("luu dien chau au", "vi")[0]["slug"] == "trix-tro-lai-leiden"


def test_unrelated_query_and_empty_index_return_nothing(index):
    assert index.search("zzzzqqqq", "vi") == []
    assert index.search("trix", "fr") == []
    assert SearchIndex.empty().search("trix", "vi") == []
    assert SearchIndex.empty().size == 0


def test_limit_is_respected(index):
    assert len(index.search("khung long hoa thach", "vi", 2)) <= 2


def test_typo_and_glued_queries_use_char_ngrams(index):
    assert index.search("khung lonh bao chua", "vi")[0]["slug"] in {"trix", "hanh-trinh-khung-long"}
    assert index.search("woolly mammmoth", "en")[0]["slug"] == "woolly-mammoth"
    assert index.search("thienthach", "vi")[0]["slug"] in {"iron-meteorite", "trai-dat-thien-thach"}
