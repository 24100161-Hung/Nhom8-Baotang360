# BaoTang360 Analytics (FastAPI)

Dịch vụ Python nội bộ của BaoTang360 (bảo tàng ảo Naturalis 360°). Có các chức năng: tìm kiếm TF-IDF song ngữ (gõ không dấu vẫn ra) trên hiện vật, tour, không gian 360 và bài viết; gợi ý tour (tour tương tự, hoặc theo hành vi của phiên); phân tích phễu rơi rụng của tour và luồng chuyển cảnh từ `visit_events`; sinh multi-resolution tiles cho Pannellum; kiểm định chất lượng dữ liệu; thống kê tổng quan. Laravel gọi qua `App\Services\AnalyticsClient` và cache 30 phút. Khi dịch vụ tắt, Laravel dùng phương án dự phòng (FULLTEXT, gợi ý cùng chủ đề, GROUP BY SQL).

## Cấu trúc

| Tệp | Vai trò |
| --- | --- |
| `app/main.py` | Ứng dụng FastAPI, xác thực `X-Analytics-Token` (so sánh hằng thời gian, không đặt token thì từ chối hết), lifespan dựng chỉ mục và reindex định kỳ 10 phút |
| `app/db.py` | SQLAlchemy + PyMySQL (utf8mb4). Truy vấn có tham số, tên bảng/cột lấy từ danh sách trắng. Bảng hoặc cột chưa có (lỗi MySQL 1146/1054) thì trả DataFrame rỗng |
| `app/text.py` | Chuẩn hóa NFC, chữ thường, bỏ dấu tiếng Việt (đ→d), tokenizer, `plain_text` (bỏ HTML, làm phẳng mảng JSON) |
| `app/vectorize.py` | Bộ vector lai TF-IDF word (1,2)-gram + char_wb (2,4)-gram |
| `app/search.py` | `SearchIndex`: mỗi locale một không gian vector, tiêu đề ×3, gộp các depth |
| `app/recommend.py` | `TourRecommender`: cosine giữa các tour, hồ sơ phiên, `why`, cache TTL 30 phút |
| `app/flow.py` | `tour_dropoff`, `scene_flow` (pandas) |
| `app/tiles.py` | Sinh cube face, level, tile, fallback, preview, `config.json` (viết lại từ Pannellum `generate.py`, MIT) |
| `app/tile_jobs.py` | Hàng đợi sinh tiles: tối đa 1 job đồng thời, trạng thái lưu trong bộ nhớ và `tiles/.status/<id>.json`, chống path traversal |
| `app/quality.py` | 14 phép kiểm định (Q01–Q14) chạy trên DataFrame |
| `app/stats.py` | Thống kê tổng quan |
| `kiem_dinh_du_lieu.py` | CLI kiểm định (bản sao ở `03_Database/kiem_dinh_du_lieu.py`) |

## Chạy

```bash
# Cùng toàn hệ thống (từ 02_SourceCode)
docker compose up -d --build
curl -s localhost:8001/healthz
# Tài liệu OpenAPI: http://localhost:8001/docs (chỉ khi APP_ENV khác production)

# Kiểm định dữ liệu (exit 0 = sạch, 1 = có lỗi mức error, 2 = không kết nối được DB)
docker compose exec analytics python kiem_dinh_du_lieu.py
docker compose exec analytics python kiem_dinh_du_lieu.py --json
```

Biến môi trường:
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- `ANALYTICS_TOKEN`: bắt buộc.
- `STORAGE_ROOT` (mặc định `/srv/storage`): điểm mount `storage/app` của Laravel. Dịch vụ tự dùng `STORAGE_ROOT/public` nếu thư mục này tồn tại.
- `TILES_PUBLIC_PREFIX` (mặc định `/storage`): tiền tố URL của `basePath`.
- Tùy chọn: `REINDEX_INTERVAL_SECONDS` (600), `EMPTY_RETRY_SECONDS` (30), `LOG_LEVEL`, `APP_ENV`.

Nếu MySQL chưa sẵn sàng, dịch vụ vẫn khởi động với chỉ mục rỗng (`/healthz` báo `index_size = 0`) và tự thử lại.

## Kiểm thử

```bash
# từ 02_SourceCode
docker compose build analytics
cd python-service
docker run --rm -u root -v "$PWD":/src -w /src --entrypoint sh baotang360-analytics \
  -c "pip install -q -r requirements-dev.txt && python -m pytest -q"
```

Test không cần MySQL: dữ liệu là fixture DataFrame trong `tests/conftest.py` (6 scene, 5 hiện vật, 6 tour, 2 bài viết, sự kiện tour có điểm rơi rụng rõ, sự kiện chuyển cảnh). Test tiles sinh ảnh bằng PIL (512×256 và 4096×2048).

## API (mọi endpoint trừ `/healthz` cần header `X-Analytics-Token`)

| Method | URL | Vào → Ra |
| --- | --- | --- |
| GET | `/healthz` | `{status, index_size, tours_indexed, indexed_at}` |
| POST | `/search` | `{q, locale='vi', limit=20 (1..100), types?: ['artifact','tour','scene','post']}` → `{engine:'tfidf', took_ms, results:[{subject_type, subject_id, slug, score, matched_terms}]}` |
| POST | `/recommend/tours` | `{tour_id, locale, limit=4 (1..20)}` hoặc `{session_key, locale, limit}` (chỉ một trong hai) → `{mode:'tour'|'session', took_ms, results:[{tour_id, slug, score, why:[string], shared_scene_ids, matched_terms}]}` |
| GET | `/analytics/dropoff?days=30` | `{days, tours:[{tour_id, slug, starts, completes, completion_rate, steps:[{order, reached, dropped, drop_rate}], worst_step}]}` |
| GET | `/analytics/scene-flow?days=30` | `{days, transitions:[{from_scene_id, to_scene_id, count}], scenes:[{scene_id, enters, avg_dwell_ms, exits, exit_rate}]}` |
| POST | `/tiles/generate` | `{scene_id, panorama_path}` (tương đối trong `storage/app/public`, ví dụ `panoramas/dinosaur.jpg`) → **202** `{scene_id, state:'pending', queued}` |
| GET | `/tiles/status/{scene_id}` | `{state: pending|running|done|error, max_level, cube_resolution, tile_resolution, tile_count, tiles_path, config_path, preview_path, tile_config, error, duration_ms}`; 404 nếu chưa sinh |
| GET | `/quality-report` | `{generated_at, summary:{errors, warnings, checks}, checks:[{code, severity, title, count, samples}]}` |
| POST | `/reindex` | `{indexed, tours, took_ms}`. Chỉ mục mới thay nguyên khối, cache gợi ý tự bị xóa |
| GET | `/stats/overview?days=30` | `{scenes, artifacts, tours, posts: {total, published}, events:{days, total, sessions, by_type}, generated_at}` |

Mã lỗi: 401 thiếu hoặc sai token; 422 dữ liệu vào sai (locale khác vi/en, `days` ngoài 1..365, `session_key` có ký tự lạ, đường dẫn panorama chứa `..` hoặc đường dẫn tuyệt đối); 404 không thấy ảnh panorama; 503 không truy cập được DB.

### Đầu ra tiles
```
storage/app/public/tiles/<scene_id>/
  config.json        {"type":"multires","multiRes":{"basePath":"/storage/tiles/<id>","path":"/%l/%s/%y_%x",
                      "fallbackPath":"/fallback/%s","extension":"jpg","tileResolution":512,"maxLevel":3,"cubeResolution":1296}}
  <level>/<face>/<y>_<x>.jpg   face ∈ f r b l u d; level 1 nhỏ nhất
  fallback/<face>.jpg          ≤ 1024 px, cho trình duyệt không có WebGL
  preview.jpg                  equirectangular 1024×512
```
Ảnh 4096×2048 cho ra cube 1296, 3 level, tổng 84 tile.

### Danh mục kiểm định

| Mã | Mức | Nội dung |
| --- | --- | --- |
| Q01_SCENE_MISSING_TILES | warning | Scene `published` chưa có `tiles_path`/`tile_config` |
| Q02_SCENE_MISSING_PREVIEW | warning | Scene `published` thiếu `preview_path` |
| Q03/Q04_SCENE_MISSING_AUDIO_VI/EN | warning | Scene `published` không có `media_assets` audio cho locale vi/en |
| Q05_HOTSPOT_OUT_OF_RANGE | error | pitch ngoài [−90, 90], yaw ngoài [−180, 180], hoặc giá trị rỗng |
| Q06_HOTSPOT_TARGET_UNPUBLISHED | error | Hotspot loại scene, nằm trong scene đã công bố, trỏ tới scene draft hoặc không tồn tại |
| Q07_TOUR_STEP_UNPUBLISHED_SCENE | error | Tour `published` có bước trỏ tới scene chưa công bố |
| Q08/Q09_MISSING_DEPTH1_VI/EN | error | Hiện vật hoặc scene `published` thiếu interpretation depth 1 |
| Q10_MEDIA_MISSING_LICENSE | error | Media không có license |
| Q11_MEDIA_MISSING_SOURCE | warning | Media thiếu cả `source_name` lẫn `source_url` |
| Q12_VISIT_DAY_OVERSOLD | error | `visit_days.sold > capacity` |
| Q13_PAID_ORDER_NO_TICKET | error | Đơn `paid` không có vé nào |
| Q14_MT_UNREVIEWED | warning | Bản dịch máy chưa được duyệt |

## Thuật toán

**Tìm kiếm.** Mỗi tài liệu ứng với một bộ (đối tượng, locale, depth):
- artifact/scene: `interpretations`;
- tour: `tour_translations` (name, description, highlights);
- post: `blog_post_translations` (title, excerpt, body đã bỏ HTML).

Nội dung chỉ mục gồm tiêu đề ×3, tóm tắt, nội dung và tên thẻ. Văn bản giữ cả dạng có dấu lẫn không dấu. Điểm = 0,6·cosine(word 1–2-gram) + 0,4·cosine(char_wb 2–4-gram). Tài liệu không chung từ nào với truy vấn chỉ được giữ khi điểm char đủ cao. Các depth của cùng một đối tượng được gộp lại.

**Gợi ý tour.** Văn bản tour gồm tên ×2, mô tả, highlights, tên các scene trong các bước và tên hiện vật trưng bày trong các scene đó. Văn bản này được vector hóa bằng cùng bộ lai word + char, mỗi locale một mô hình.
- Theo `tour_id`: lấy cosine với mọi tour khác, loại chính tour đó.
- Theo `session_key`: hồ sơ phiên dựng từ tên các scene đã `scene_enter` và các hiện vật đã `artifact_view` (mỗi mục lặp tối đa 3 lần). Điểm = 0,7·cosine + 0,3·(tỷ lệ scene của tour mà phiên đã ghé). Tour đã `tour_complete` trong phiên bị loại.
- `why` liệt kê scene chung và từ khớp: các unigram có tích trọng số TF-IDF lớn nhất, gộp dạng có dấu với không dấu.
- Kết quả cache 30 phút trong đối tượng recommender. Khi reindex, recommender được tạo mới nên cache cũ cũng mất theo.

**Phễu rơi rụng.**
- `starts` là số phiên có `tour_start`. `reached[k]` là số phiên có `tour_step` với `step_order ≥ k`; phiên đã `tour_start` được tính là đạt bước 1, phiên đã `tour_complete` được tính là đạt bước cuối.
- `drop_rate[k] = (reached[k] − reached[k+1]) / reached[k]`; ở bước cuối, `reached[n+1]` lấy bằng `completes`.
- `worst_step` là bước có `drop_rate` lớn nhất.
- Số bước lấy từ `tour_steps`, nên tour chưa có sự kiện nào vẫn hiện trong kết quả.

**Luồng chuyển cảnh.**
- Các `scene_enter` trong cùng `session_key` được sắp theo `created_at`; cặp liên tiếp khác nhau tạo thành cạnh from→to.
- `avg_dwell_ms` là trung bình `scene_leave.duration_ms`; nếu scene không có sự kiện này thì dùng hiệu thời gian đến lần `scene_enter` kế tiếp.
- `exit_rate` = số phiên kết thúc tại scene / số lần vào scene.

**Tiles.**
- Các bước giống `generate.py` của Pannellum: `cubeResolution = 8·⌊width/π/8⌋`, `levels = ⌈log2(cube/512)⌉ + 1` (bỏ một level ở trường hợp biên), mỗi level giảm nửa kích thước bằng LANCZOS rồi cắt thành ô 512 px.
- Mặt cube được chiếu trực tiếp bằng numpy với nội suy song tuyến (bản gốc gọi Hugin `nona`).
- Kết quả được ghi vào thư mục tạm rồi đổi tên thay cho thư mục cũ, nên không bao giờ để lại bản sinh dở.

## Hạn chế
- TF-IDF không hiểu ngữ nghĩa: không nhận ra từ đồng nghĩa ("khủng long bạo chúa" với "T. rex") khi hai bên không có chữ chung, và không tìm xuyên ngữ (mỗi locale một chỉ mục).
- Tokenizer tách theo âm tiết, không tách từ ghép tiếng Việt. Bỏ dấu gây nhập nhằng ("trống"/"trong").
- Trọng số (0,6/0,4; 0,7/0,3; tiêu đề ×3) được chọn tay trên dữ liệu nhỏ, chưa đánh giá bằng tập truy vấn có nhãn.
- Gợi ý theo phiên bị cold start: phiên mới chưa có sự kiện nào thì trả rỗng, Laravel dùng phương án dự phòng. Hồ sơ phiên chỉ dựa trên tên scene và tên hiện vật.
- Drop-off và scene-flow chỉ phản ánh những sự kiện client gửi được (sendBeacon có thể mất khi tắt tab, bị adblock, hoặc khi người dùng chặn JS). Phiên được xác định theo `session_key`, nên đổi thiết bị hay xóa cookie sẽ tạo phiên mới. `avg_dwell_ms` theo hiệu thời gian bị kéo dài nếu người dùng để tab nhàn rỗi.
- Sinh tiles nặng CPU và chạy đơn luồng (1 job tại một thời điểm, các job khác xếp hàng). Ảnh 4096×2048 mất vài giây, ảnh 12000×6000 cần khoảng 1 GB RAM. Hàng đợi nằm trong bộ nhớ, nên job đang chạy khi dịch vụ khởi động lại sẽ được báo `error: interrupted`. Preview là ảnh thu nhỏ, không phải SHT hash như bản gốc. Nội suy song tuyến kém sắc nét hơn Lanczos của nona khi ảnh gốc lớn hơn nhiều so với cube.
- Chỉ mục nằm trong bộ nhớ và được dựng lại toàn bộ mỗi 10 phút. Cách này phù hợp với vài nghìn tài liệu.

## Ghi công
`app/tiles.py` là bản viết lại thuật toán trong `utils/multires/generate.py` của Pannellum (https://github.com/mpetroff/pannellum), Copyright (c) 2014–2025 Matthew Petroff, giấy phép MIT.
