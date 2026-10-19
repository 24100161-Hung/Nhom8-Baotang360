# BaoTang360 — CSE703073 Nhóm 08

Bảo tàng ảo và tour tham quan 360° trên nền web cho Naturalis Biodiversity Center (Leiden, Hà Lan).


## Chạy nhanh bằng một lệnh

| Hệ điều hành | Khởi động | Dừng (giữ dữ liệu) | Nạp lại dữ liệu mẫu |
| --- | --- | --- | --- |
| Windows (Docker Desktop) | nháy đúp `chay.bat` | `dung.bat` | `nap-lai-du-lieu.bat` |
| macOS / Linux | `./chay.sh` | `./dung.sh` | `docker compose down -v && ./chay.sh` |

Script tự tạo `.env` từ `.env.example`, dựng 3 dịch vụ (db, analytics, app), chờ `/healthz` và mở trình duyệt tại http://localhost:8080. Mẫu biến môi trường cho Railway nằm ở `deploy/`.

## 1. Giới thiệu

- **Mã đề tài:** ĐT-16 – "BẢO TÀNG 360 ĐỘ – Bảo tàng ảo và tour tham quan 360° trên nền web".
- **Học phần:** CSE703073 – Lập trình ứng dụng web trong du lịch 2, Trường Công nghệ thông tin, Đại học Phenikaa. Lớp `20xx-2-CSE703073-01`, Nhóm 08. Giảng viên: TS. Nguyễn Văn Tánh.
- **Phạm vi:**
  - Du khách tham quan 8 phòng trưng bày bằng ảnh 360°: Pannellum, multi-resolution tiles, hotspot, minimap, chế độ tự do hoặc tour có hướng dẫn, audio guide VI/EN.
  - Xem và tìm kiếm 193 hiện vật thật của Naturalis.
  - Mua vé Regular (150.000 đ/lượt) hoặc hội viên Premium (590.000 đ/năm): giỏ hàng, lịch ngày, thanh toán sandbox, vé QR.
  - Blog, liên hệ, sổ lưu bút, đánh giá, yêu thích; giao diện song ngữ VI/EN và 2 theme.
  - Khu quản trị cho `admin` và `curator`.
  - Dịch vụ Python: tìm kiếm và gợi ý tour bằng TF-IDF + cosine, phân tích điểm rơi (drop-off) của luồng tham quan, sinh tiles 360°, kiểm định dữ liệu.
- Đặc tả gồm 50 yêu cầu chức năng (FR) và 34 yêu cầu phi chức năng (NFR); xem tài liệu yêu cầu của nhóm.
- **Sản phẩm học thuật, phục vụ mục đích đào tạo.** Thanh toán chỉ ở chế độ thử nghiệm (sandbox), không có giao dịch tiền thật.

## 2. Kiến trúc

Vue 3.5 + Inertia 2 (frontend) — Laravel 12.70 trên PHP-FPM 8.3 (backend) — Nginx 1.27 — MySQL 8.0 — FastAPI (python-service, Python 3.12).

```
 Trình duyệt (Vue 3 + Inertia + Pannellum 2.5.6)
        │ HTTP(S)
        ▼
 ┌────────────┐  FastCGI  ┌───────────────────────────────┐   HTTP + token   ┌─────────────────────────┐
 │ web        │ ────────▶ │ app  PHP-FPM 8.3 + Laravel 12 │ ───────────────▶ │ analytics  FastAPI      │
 │ Nginx 1.27 │  app:9000 │ Controller → Service → Model  │  (dự phòng SQL)  │ TF-IDF, drop-off, tiles │
 └─────┬──────┘           └──────────────┬────────────────┘                  └──────┬──────────────────┘
       │ đọc /build, /storage/tiles      │ Eloquent (PDO)                          │ đọc
       ▼                                 ▼                                         ▼
  volume app_storage ◀── ghi tiles ──  MySQL 8.0 (51 bảng, utf8mb4)  ◀────────────┘
```

- Sơ đồ ảnh: `../01_BaoCao/hinh/kien_truc_he_thong.png` (nguồn `.dot` cùng thư mục).
- ERD: `../03_Database/ERD.png` (schema.sql, seed_sample.sql, từ điển dữ liệu cùng thư mục).
- Ba tầng trong Laravel:
  - Trình bày: `app/Http/Controllers`, `Requests`, `Resources`, các trang Vue trong `app/resources/js/Pages`.
  - Nghiệp vụ: `app/Services` (OrderService, PaymentService, TicketService, SearchService…).
  - Truy cập dữ liệu: `app/Models` (Eloquent).
- Dữ liệu được kiểm tra hợp lệ ở cả hai phía: FormRequest ở máy chủ, ràng buộc trường trong form Vue.

| Thư mục | Nội dung |
| --- | --- |
| `app/` | Laravel 12 + Vue 3 (Inertia): `app/`, `routes/`, `database/{migrations,seeders,data,seed-assets}`, `lang/`, `resources/views` (Blade gốc), `tests/` (PHPUnit) |
| `python-service/` | FastAPI: `app/` (search, recommend, analytics, tiles, quality), `tests/` (pytest), `kiem_dinh_du_lieu.py` |
| `app/docker/` | `Dockerfile` (nginx 1.27 + php-fpm 8.3 + scheduler, một container), `nginx.conf`, `entrypoint.sh`, `php.ini`, `php-fpm.conf`, `supervisord.conf`, `backup.sh` |
| `deploy/` | `Dockerfile.web` (image PaaS "tất cả trong một" kèm Python, ngữ cảnh = gốc kho), `railway-web.env`, `supervisord.paas.conf`, `entrypoint.paas.sh` |
| `python-service/` | FastAPI: `app/{main,search,recommend,flow,tiles,quality,...}.py`, `tests/` (pytest), `Dockerfile`, `run.py` |

## 3. Yêu cầu môi trường

| Cách chạy | Yêu cầu |
| --- | --- |
| Docker (khuyến nghị) | Docker Engine 24+ hoặc Docker Desktop (Windows 10/11, macOS), Docker Compose v2, Git ≥ 2.40, RAM trống ≥ 4 GB, ổ đĩa trống ≥ 5 GB, cổng 8080/3307/8001 trống |
| Thủ công | PHP ≥ 8.2 (dự án cố định nền tảng 8.3) với `pdo_mysql intl gd zip bcmath`, Composer ≥ 2.7, MySQL ≥ 8.0, Node.js ≥ 20 (npm ≥ 10), Python ≥ 3.11 (image dùng 3.12) |

Kiểm tra nhanh máy: `chay.sh` / `chay.bat` tự kiểm tra Docker và cổng trước khi dựng.

## 4. Cài đặt nhanh bằng Docker

```bash
git clone <URL-kho> baotang360 && cd baotang360
cp .env.example .env                 # tùy chọn: chỉ cần khi muốn đổi cổng/mật khẩu
docker compose up -d --build         # lần đầu khoảng 5–10 phút
# Mở http://localhost:8080
```

- Không cần chạy `key:generate` hay `migrate --seed` bằng tay. `app/docker/entrypoint.sh` tự làm các việc sau:
  - chờ MySQL;
  - sinh `APP_KEY` và khóa HMAC sandbox, lưu trong volume và không commit;
  - `migrate --force`, rồi `db:seed` nếu CSDL rỗng;
  - đồng bộ `public/` cho Nginx;
  - gọi Python dựng chỉ mục và sinh tiles cho các phòng chưa có;
  - chạy scheduler.
- Kiểm tra: `docker compose ps` (cả 3 dịch vụ ở trạng thái `running`/`healthy`); `curl http://localhost:8080/healthz` trả `{"status":"ok","db":"ok","analytics":"ok"}`.
- Trùng cổng: đặt `APP_FORWARD_PORT`, `DB_FORWARD_PORT`, `ANALYTICS_FORWARD_PORT` và `APP_URL` trong `.env`. Ví dụ:
  ```bash
  DB_FORWARD_PORT=3327 APP_FORWARD_PORT=8090 ANALYTICS_FORWARD_PORT=8021 APP_URL=http://localhost:8090 docker compose up -d
  ```
- Dừng: `docker compose down`. Xóa sạch dữ liệu: `docker compose down -v`.
- Sao lưu tay: `docker compose exec app backup.sh`. Hướng dẫn khôi phục ở `../04_HuongDanSuDung/HuongDan_CaiDat_QuanTri.md`.

## 5. Cài đặt thủ công

**Backend (Laravel)**
```bash
cd app
composer install
cp .env.example .env                 # mẫu cho chạy ngoài Docker (DB 127.0.0.1:3317, analytics 127.0.0.1:8011)
php artisan key:generate
mysql -uroot -p -e "CREATE DATABASE baotang360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed           # đọc database/data/*.json, sao chép seed-assets vào storage
php artisan storage:link
php artisan serve --port=8080        # hoặc cấu hình Nginx/Apache trỏ app/public
php artisan schedule:work            # cửa sổ khác: hết hạn đơn, sao lưu 02:00, health 5 phút
```

**Giao diện (Vue + Vite, vẫn trong `app/`)**
```bash
npm ci
npm run build                        # xuất ra public/build
# npm run dev                        # phát triển có HMR (Laravel đọc public/hot)
```

**Dịch vụ Python (FastAPI)**
```bash
cd python-service
python -m venv .venv && . .venv/bin/activate      # Windows: .venv\Scripts\activate
pip install -r requirements.txt
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=baotang360 DB_USERNAME=baotang360 DB_PASSWORD=<mat-khau> \
       ANALYTICS_TOKEN=dev-analytics-token STORAGE_ROOT=../app/storage/app
uvicorn app.main:app --host 127.0.0.1 --port 8001
```
`ANALYTICS_TOKEN` phải trùng giữa Laravel và Python. Khi Python tắt, hệ thống vẫn chạy:
- tìm kiếm chuyển sang MySQL FULLTEXT/LIKE;
- phân tích luồng dùng truy vấn SQL dự phòng;
- trình xem 360° dùng ảnh equirectangular thay cho tiles.

## 6. Tài khoản kiểm thử

Mật khẩu chung là `BaoTang@2026`, lấy từ biến `SEED_DEMO_PASSWORD`; đổi biến này trước khi triển khai công khai.

| Vai trò | Tài khoản | Mật khẩu | Phạm vi quyền |
| --- | --- | --- | --- |
| Quản trị viên (`admin`) | `admin@baotang360.test` | `BaoTang@2026` | Toàn quyền: dashboard, mọi CRUD, gói vé, lịch vé, đơn hàng, tài khoản, nhật ký, thống kê, phân tích luồng |
| Người phụ trách nội dung (`curator`) | `curator@baotang360.test` | `BaoTang@2026` | Hiện vật, không gian 360°, hotspot, tour, audio guide, blog, kiểm duyệt đánh giá, xem thống kê; không quản lý tài khoản/gói vé/đơn hàng (403) |
| Khách tham quan (`visitor`) | `visitor@baotang360.test` | `BaoTang@2026` | Tham quan, yêu thích, đánh giá, giỏ hàng, mua vé, vé QR |
| Khách tham quan có hội viên Premium | `premium@baotang360.test` | `BaoTang@2026` | Như visitor, hội viên Premium còn hạn (giảm 20% vé Regular) |
| Khách vãng lai | (không đăng nhập) | – | Xem công khai, tham quan 360° chế độ tự do, thêm giỏ hàng; phải đăng nhập mới thanh toán |

Ngày 18/10/2026 đã thử đăng nhập cả 4 tài khoản trên `/vi/dang-nhap`; tất cả đều thành công (302 → `/vi/tai-khoan`).

## 7. Thành viên và phân công

| Họ tên | MSSV | Vai trò | Phạm vi phụ trách |
| --- | --- | --- | --- |
| `<Họ tên 1>` (Trưởng nhóm) | `<MSSV1>` | V1 Trưởng nhóm, quản trị cấu hình; V6 Nghiệp vụ và tài liệu | Kế hoạch, biên bản, Git, đóng gói; yêu cầu, ca sử dụng, hướng dẫn sử dụng, 00_INFO |
| `<Họ tên 2>` | `<MSSV2>` | V2 Kiến trúc sư dữ liệu | Mô hình 44 bảng, migration, dữ liệu Naturalis/NBA/Commons, ERD, từ điển dữ liệu, dịch vụ Python |
| `<Họ tên 3>` | `<MSSV3>` | V3 Phát triển máy chủ | Laravel Services/Controllers, RBAC, đơn hàng – vé – thanh toán sandbox, API Sanctum, Docker, nhật ký, sao lưu |
| `<Họ tên 4>` | `<MSSV4>` | V4 Phát triển giao diện; V5 Kiểm thử và triển khai | Trang Vue, trình xem 360°, minimap, tour, 2 theme, đáp ứng 3 khổ; kiểm thử, Playwright, Lighthouse, go-live |

Chi tiết và tỷ lệ đóng góp: `07_QuanTriDuAn/PhanCong.md` trong gói nộp, sheet PHAN_CONG.

## 8. Giấy phép và nguồn dữ liệu

- **Naturalis Biodiversity Center** (Leiden): thông tin bảo tàng và phòng trưng bày. Dữ liệu dùng theo **CC BY-SA 3.0**. Chân trang mọi trang ghi "Ảnh 360°, dữ liệu hiện vật: Naturalis Biodiversity Center – CC BY-SA 3.0". Khi tái sử dụng phải ghi công và chia sẻ cùng giấy phép.
- **Netherlands Biodiversity API (NBA)**: bản ghi mẫu vật (số đăng ký RGM/RMNH/ZMA/L, tên khoa học, nơi thu, năm thu) và ảnh medialib.naturalis.nl, **CC0**.
- **Wikimedia Commons**: 5 ảnh 360° các gian Naturalis (Henk Caspers/Naturalis, **CC BY-SA 3.0**), ảnh xem trước phòng, ảnh bìa bài viết. Giấy phép và tác giả ghi theo từng media trong bảng `media_assets`.
- **Dữ liệu mô phỏng** do nhóm tạo, có gắn cờ:
  - 3 ảnh 360° (seduction, death, ice_age) và logo chữ (không phải logo chính thức);
  - audio guide đọc bằng TTS của trình duyệt;
  - gói vé Học sinh – Sinh viên, sự kiện trong blog;
  - tài khoản, đơn hàng, thanh toán.
- **Thư viện:**
  - Pannellum 2.5.6 (MIT); thuật toán sinh tiles viết lại từ `generate.py` của Pannellum (MIT, có ghi công);
  - Laravel, Vue, Inertia, Tailwind (MIT);
  - FastAPI, scikit-learn, pandas (BSD/MIT).
- Mã nguồn của nhóm là sản phẩm học thuật của học phần CSE703073.

---

## Phụ lục README

### A. Lệnh kiểm thử (chạy từ thư mục gốc kho)

```bash
# PHPUnit (CSDL baotang360_test do db-init/01-test-db.sql tạo; image runtime không có dev-deps nên mount mã nguồn)
docker run --rm -u $(id -u):$(id -g) --network baotang360_default -v "$PWD/app":/var/www/html \
  -w /var/www/html -e HOME=/tmp --entrypoint php baotang360-app artisan test
# pytest dịch vụ Python
cd python-service && docker run --rm -u root -v "$PWD":/src -w /src --entrypoint sh baotang360-analytics \
  -c "pip install -q -r requirements-dev.txt && python -m pytest -q"
# Playwright 3 trình duyệt (hệ thống đang chạy)
cd app && npx playwright install chromium firefox webkit && E2E_BASE_URL=http://localhost:8080 \
  npx playwright test --project=chromium --project=firefox --project=webkit
# Kiểm định dữ liệu 14 phép kiểm
docker compose exec analytics python kiem_dinh_du_lieu.py
```

Kết quả gần nhất:
- PHPUnit: 172 test / 1.933 assertion, đạt (18/10/2026).
- pytest: [CẬP NHẬT: số ca đạt/tổng].
- Kiểm định dữ liệu: 0 lỗi, 8 cảnh báo (Q01: chưa sinh tiles).
- Playwright và Lighthouse: [CẬP NHẬT], xem `05_KiemThu` trong gói nộp.

### B. Lựa chọn phiên bản so với ngăn xếp tham chiếu của học phần

Tài liệu kỹ thuật lấy ví dụ bằng Laravel 11 và CSS thuần/Bootstrap. Nhóm chọn khác ở bốn điểm:
- **Laravel 12 thay cho Laravel 11.**
  - Laravel 11 hết hạn sửa lỗi bảo mật vào 03/2026. Laravel 12 vẫn được hỗ trợ bảo mật trong toàn bộ thời gian chấm và duy trì 30 ngày sau hạn nộp.
  - Cấu trúc thư mục và API của Laravel 12 hầu như giữ nguyên so với bản 11 (bản 12 là bản bảo trì), nên mọi hướng dẫn của tài liệu vẫn áp dụng được.
- **Tailwind CSS 4 thay cho CSS thuần.**
  - Hai theme được dựng bằng token CSS `data-theme` (biến CSS), Tailwind 4 đọc trực tiếp các biến này.
  - Plugin Vite `@tailwindcss/vite` loại CSS thừa khi build. Bundle khởi đầu là 140,9 KB gzip, dưới ngân sách 150 KB.
- **Inertia 2 thay cho SPA tách rời gọi REST.**
  - Định tuyến, phân quyền (middleware, Policy), CSRF và phiên vẫn nằm hoàn toàn ở Laravel; Vue chỉ nhận props đã lọc.
  - Nhờ vậy nhóm không phải duy trì hai lớp xác thực (session và token) cho cùng giao diện.
  - API mở `/api/v1` (Sanctum, OpenAPI 3.0.3) vẫn có sẵn cho bên thứ ba.
- **Nginx + PHP-FPM thay cho `php artisan serve`.**
  - Cách này đúng mô hình triển khai mà tài liệu khuyến nghị.
  - Tệp tĩnh và tiles 360° được Nginx phục vụ trực tiếp với cache 1 năm, không đi qua PHP.
- **Pannellum 2.5.6, sai khác so với tài liệu yêu cầu.**
  - Tài liệu yêu cầu của nhóm ghi Pannellum 2.5.7 và Laravel 11; mã nguồn hiện khóa `pannellum@2.5.6` (`app/package.json`). Trên npm đã có bản 2.5.7.
  - Nhóm cần chọn một trong hai hướng: nâng lên 2.5.7 rồi chạy lại kiểm thử trình xem, hoặc ghi lý do giữ 2.5.6 vào báo cáo. `[CẬP NHẬT]`
