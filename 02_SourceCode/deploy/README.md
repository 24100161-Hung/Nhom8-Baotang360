# deploy/ – mẫu biến môi trường cho Railway

- `railway-web.env`: dán vào Variables → Raw Editor của dịch vụ `web` (image `deploy/Dockerfile.web`, một container chứa nginx + php-fpm + scheduler + dịch vụ Python).
- Cần thêm dịch vụ MySQL của Railway và một volume gắn tại `/var/www/html/storage/app`.
- Render dùng `render.yaml` ở gốc kho (Blueprint), cần MySQL ngoài.

Quy trình đầy đủ: `docs/TrienKhai_Railway_Render.md`. Không bao giờ commit tệp đã điền giá trị bí mật thật.
