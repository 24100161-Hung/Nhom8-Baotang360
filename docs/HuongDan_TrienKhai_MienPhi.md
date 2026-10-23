# Hướng dẫn triển khai BaoTang360 lên hạ tầng trực tuyến miễn phí

Học phần CSE703073 – Nhóm 08 – Lớp ĐT-16. Cập nhật 23/10/2026.

Tài liệu này thay thế cho `docs/TrienKhai_Railway_Render.md` được `02_SourceCode/deploy/README.md` tham chiếu.

## 1. Vì sao không dùng GitHub Pages

Hệ thống là ứng dụng động: Laravel 12 trên PHP 8.3, giao diện Vue 3 + Inertia biên dịch bằng Vite, dịch vụ FastAPI kèm pandas/scikit-learn, cơ sở dữ liệu MySQL 8. GitHub Pages chỉ phục vụ tệp tĩnh, không thực thi mã phía máy chủ và không kết nối cơ sở dữ liệu.

## 2. So sánh các nền tảng miễn phí

Số liệu trích từ tài liệu chính thức của từng nhà cung cấp, truy cập ngày 23/10/2026. Chính sách thay đổi thường xuyên, cần kiểm tra lại trước mỗi lần triển khai.

| Nền tảng | Mức miễn phí hiện hành | Ràng buộc chính | Đánh giá |
|---|---|---|---|
| Oracle Cloud Always Free | 2 OCPU ARM, 12 GB RAM, 200 GB ổ đĩa, 10 TB băng thông/tháng | Xác minh thẻ khi mở tài khoản; có thời điểm hết máy ARM trống | **Được chọn làm bản chính thức** |
| Render gói Free | 512 MB RAM, không kèm MySQL | Ngủ sau 15 phút, 750 giờ/tháng, không có ổ đĩa bền vững | Chỉ phù hợp bản trình diễn |
| Northflank gói Sandbox | 2 dịch vụ, 2 tác vụ, 1 addon CSDL; luôn bật, không cần thẻ | Không công bố định mức vCPU/RAM cụ thể *(cần kiểm chứng)* | Dự phòng đáng cân nhắc |
| Railway | 30 ngày dùng thử kèm 5 USD tín dụng, sau đó 1 USD/tháng | Không còn gói miễn phí vĩnh viễn | Không đáp ứng tiêu chí miễn phí |
| Fly.io | Không còn hạn mức miễn phí cho tổ chức mới | Máy luôn bật rẻ nhất ~2,19 USD/30 ngày | Không đáp ứng |
| Koyeb | Không còn gói máy chủ miễn phí | Tính theo giờ sử dụng | Không đáp ứng |
| Aiven for MySQL Free | 1 CPU, 1 GB RAM, 1 GB dung lượng | Tự tắt khi nhàn rỗi lâu | CSDL ngoài cho Render |
| GitHub Pages | Chỉ tệp tĩnh | Không chạy mã máy chủ | Không đáp ứng |

## 3. Phương án chính thức – máy ảo Oracle Cloud Always Free

### 3.1. Tạo máy ảo

1. `oracle.com/cloud/free` → **Start for free**. Chọn Home Region gần Việt Nam (Singapore hoặc Tokyo); vùng này không đổi được về sau.
2. **Compute → Instances → Create instance**. Image: Ubuntu 22.04 hoặc 24.04.
3. **Change shape → Ampere → VM.Standard.A1.Flex**, đặt 2 OCPU và 12 GB — toàn bộ hạn mức Always Free.
4. Tạo cặp khoá SSH, tải cả hai tệp về máy. Ghi lại **Public IP address**.

Thẻ thanh toán chỉ dùng để xác minh danh tính; tài nguyên trong hạn mức Always Free không bị tính phí. Thông báo *Out of host capacity* nghĩa là vùng hết máy ARM tại thời điểm đó — thử lại vào khung giờ khác hoặc đổi Availability Domain.

### 3.2. Mở cổng mạng ở hai lớp

Lưu lượng bị chặn ở hai lớp độc lập; thiếu một lớp thì trang vẫn không truy cập được.

- **Mạng ảo của Oracle:** Networking → Virtual Cloud Networks → VCN đang dùng → Security Lists → Default Security List → **Add Ingress Rules**: Source CIDR `0.0.0.0/0`, TCP, cổng `80` và `443`.
- **Tường lửa trong máy ảo:** kịch bản `oracle-setup.sh` tự xử lý bằng iptables.

### 3.3. Cài đặt và khởi chạy

```bash
chmod 600 khoa.key
ssh -i khoa.key ubuntu@<IP>

git clone --depth 1 https://github.com/24100161-Hung/Nhom8-Baotang360.git ~/baotang360
cd ~/baotang360
chmod +x 02_SourceCode/deploy/oracle-setup.sh
./02_SourceCode/deploy/oracle-setup.sh
```

Kịch bản tự cài Docker Engine, mở cổng trong iptables, sinh `.env.prod` với các bí mật ngẫu nhiên, dựng image và khởi chạy ba dịch vụ (MySQL 8, ứng dụng, Caddy). Thời gian dựng trên máy ảo ARM khoảng 15–25 phút.

Dùng tên miền riêng thay cho `<IP>.sslip.io`:

```bash
./02_SourceCode/deploy/oracle-setup.sh baotang360.id.vn ten@phenikaa-uni.edu.vn
```

### 3.4. Kiểm tra

```bash
cd ~/baotang360
docker compose -f compose.prod.yaml --env-file .env.prod ps
docker compose -f compose.prod.yaml --env-file .env.prod logs -f caddy
docker compose -f compose.prod.yaml --env-file .env.prod logs -f web
```

## 4. Phương án thay thế – Render gói Free

Render không cung cấp MySQL nên cần một cơ sở dữ liệu bên ngoài (ví dụ Aiven for MySQL gói Free).

1. Tạo dịch vụ MySQL, lấy chuỗi kết nối dạng `mysql://user:password@host:port/database`.
2. Sinh khoá ứng dụng và giữ cố định:
   ```bash
   php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
   # hoặc
   echo "base64:$(openssl rand -base64 32)"
   ```
3. `dashboard.render.com` → **New → Blueprint** → chọn kho này → **Apply**.
4. Điền ba biến bí mật: `APP_KEY`, `DB_URL`, `SEED_DEMO_PASSWORD`. Hai biến `ANALYTICS_TOKEN` và `PAYMENT_SANDBOX_SECRET` do Render sinh tự động.
5. Kiểm tra: `curl -i https://<ten-dich-vu>.onrender.com/healthz` phải trả mã 200.

Giới hạn cần chấp nhận: ngủ sau 15 phút không truy cập, 750 giờ chạy mỗi tháng, hệ thống tệp không bền vững, dịch vụ Python bị tắt nên tìm kiếm dùng FULLTEXT thay cho ngữ nghĩa.

## 5. Các tệp cấu hình

| Tệp | Chức năng |
|---|---|
| `render.yaml` | Bản thiết kế dịch vụ Render cho gói Free |
| `compose.prod.yaml` | Ba dịch vụ cho máy ảo Oracle Cloud |
| `env.prod.example` | Mẫu biến môi trường (`cp env.prod.example .env.prod`) |
| `02_SourceCode/deploy/entrypoint.paas.sh` | Khởi động container; công tắc `ANALYTICS_ENABLED`, khôi phục ảnh chạy nền |
| `02_SourceCode/deploy/supervisord.paas.conf` | Quản lý tiến trình; `autostart` của analytics đọc biến `ANALYTICS_AUTOSTART` |
| `02_SourceCode/deploy/Caddyfile` | Proxy TLS, Let's Encrypt, dự phòng ZeroSSL |
| `02_SourceCode/deploy/oracle-setup.sh` | Kịch bản cài đặt một lệnh trên máy ảo |

Hai tệp `entrypoint.paas.sh` và `supervisord.paas.conf` phải được cập nhật cùng nhau, vì `supervisord` đọc biến `ANALYTICS_AUTOSTART` do `entrypoint` xuất ra.

## 6. Kiểm thử nghiệm thu

| TT | Nội dung | Tiêu chí đạt |
|---|---|---|
| 1 | Gọi `/healthz` | Mã 200, trường `database` có giá trị `ok` |
| 2 | Mở trang chủ | Giao diện đầy đủ, không có lỗi trong Console |
| 3 | Mở một phòng trưng bày 360° | Ảnh toàn cảnh xoay được, điểm nóng hiển thị |
| 4 | Tìm kiếm hiện vật bằng tiếng Việt | Trả về kết quả phù hợp |
| 5 | Đăng nhập tài khoản mẫu | Vào được khu vực quản trị |
| 6 | Đặt vé ở chế độ thử nghiệm | Đơn được tạo, trạng thái thanh toán đúng |
| 7 | Chuyển đổi ngôn ngữ Việt – Anh | Đổi ngôn ngữ, không mất phiên |
| 8 | Kiểm tra chứng thư TLS | Kết nối an toàn, không cảnh báo |

## 7. Xử lý sự cố

| Triệu chứng | Nguyên nhân thường gặp | Hướng xử lý |
|---|---|---|
| Không mở được trang trên Oracle Cloud | Chưa thêm Ingress Rule cổng 80/443 | Thực hiện lại mục 3.2 |
| Caddy không xin được chứng thư | Hạn mức Let's Encrypt của sslip.io đã cạn | Chờ Caddy chuyển sang ZeroSSL, hoặc dùng tên miền riêng |
| Đăng nhập xong bị đăng xuất ngay | `APP_KEY` thay đổi sau mỗi lần khởi động | Đặt `APP_KEY` cố định trong biến môi trường |
| Ảnh hiện vật không hiển thị | Hệ thống tệp bị xoá sau khi dịch vụ ngủ | Chờ tiến trình khôi phục ảnh chạy nền (1–2 phút) |
| Tiến trình bị dừng đột ngột trên Render | Vượt hạn mức 512 MB bộ nhớ | Xác nhận `ANALYTICS_ENABLED=false` |
| Trang trắng, lỗi kết nối CSDL | `DB_URL` sai hoặc dịch vụ CSDL đã tắt | Kiểm tra chuỗi kết nối, khởi động lại dịch vụ CSDL |

Sao lưu cơ sở dữ liệu trên máy ảo:

```bash
cd ~/baotang360
docker compose -f compose.prod.yaml --env-file .env.prod exec -T db \
  mysqldump -u root -p"$DB_ROOT_PASSWORD" baotang360 | gzip > ~/baotang360-$(date +%F).sql.gz
```

## 8. Nguyên tắc bảo mật

Mọi thông tin bí mật (`APP_KEY`, `DB_URL`, mật khẩu cơ sở dữ liệu, nội dung `.env.prod`) chỉ nằm trong biến môi trường của nền tảng hoặc tệp `.env.prod` trên máy ảo. Không đưa vào kho mã nguồn, không dán vào báo cáo nộp bài, không hiển thị trong video hướng dẫn. Khoá riêng SSH đặt quyền `600` và không chia sẻ.

## 9. Ghi chú học thuật

Sản phẩm phục vụ mục đích đào tạo. Dữ liệu Naturalis theo giấy phép CC BY-SA 3.0, dữ liệu NBA theo CC0; giữ nguyên phần ghi công trong giao diện. Chức năng thanh toán chạy ở chế độ thử nghiệm, không xử lý giao dịch tài chính thực tế.
