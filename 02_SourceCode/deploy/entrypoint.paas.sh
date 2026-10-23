#!/bin/bash
# ---------------------------------------------------------------------------
# Entrypoint PaaS (Render / Railway / Fly / VM Oracle Cloud)
# Suy ra APP_URL và DB_* từ biến nền tảng, sinh khoá, chờ CSDL, migrate, seed,
# cấu hình nginx theo PORT, dựng chỉ mục + tiles 360°, rồi giao cho supervisord.
#
# Bổ sung so với bản gốc (phục vụ triển khai trên hạ tầng miễn phí):
#   A. ANALYTICS_ENABLED=false  -> không khởi động uvicorn (tiết kiệm ~300 MB RAM
#                                  trên gói Render Free 512 MB), bỏ qua bước
#                                  reindex/tiles. Ứng dụng tự chuyển sang
#                                  FULLTEXT + ảnh equirectangular.
#   B. Khôi phục tài nguyên ảnh khi hệ thống tệp KHÔNG bền vững: nếu CSDL đã có
#      dữ liệu nhưng storage/app/public rỗng (trường hợp Render Free sau khi
#      ngủ), chạy lại các seeder chỉ-ghi-media ở chế độ nền để ảnh hiện trở lại.
#   C. Cảnh báo khi APP_KEY không được đặt cố định trên nền tảng không có ổ đĩa.
# ---------------------------------------------------------------------------
set -e
cd /var/www/html
run_as_www() { setpriv --reuid=www-data --regid=www-data --init-groups -- "$@"; }

# 0. Biến nền tảng → biến Laravel
if [ -z "$APP_URL" ]; then
  if [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN";
  elif [ -n "$RENDER_EXTERNAL_URL" ]; then export APP_URL="$RENDER_EXTERNAL_URL";
  else export APP_URL="http://localhost:${PORT:-8080}"; fi
fi
# Railway MySQL: MYSQL_URL / MYSQLHOST…; Render/khác: DB_URL hoặc DB_*
[ -z "$DB_URL" ] && [ -n "$MYSQL_URL" ] && export DB_URL="$MYSQL_URL"
if [ -n "$DB_URL" ]; then
  eval "$(php -r '$u=parse_url(getenv("DB_URL")); printf("export DB_HOST=%s DB_PORT=%s DB_USERNAME=%s DB_PASSWORD=%s DB_DATABASE=%s\n", escapeshellarg($u["host"]??""), escapeshellarg($u["port"]??3306), escapeshellarg(rawurldecode($u["user"]??"")), escapeshellarg(rawurldecode($u["pass"]??"")), escapeshellarg(ltrim($u["path"]??"","/")));')"
else
  export DB_HOST="${DB_HOST:-$MYSQLHOST}" DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}" DB_USERNAME="${DB_USERNAME:-$MYSQLUSER}" DB_PASSWORD="${DB_PASSWORD:-$MYSQLPASSWORD}" DB_DATABASE="${DB_DATABASE:-$MYSQLDATABASE}"
fi
export DB_CONNECTION=mysql APP_ENV="${APP_ENV:-production}" APP_DEBUG="${APP_DEBUG:-false}" APP_LOCALE="${APP_LOCALE:-vi}" APP_FALLBACK_LOCALE="${APP_FALLBACK_LOCALE:-en}"
export SESSION_DRIVER="${SESSION_DRIVER:-database}" CACHE_STORE="${CACHE_STORE:-database}" QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}" MAIL_MAILER="${MAIL_MAILER:-log}"
export LOG_CHANNEL="${LOG_CHANNEL:-stack}" LOG_STACK="${LOG_STACK:-daily}" LOG_LEVEL="${LOG_LEVEL:-info}"
export TRUSTED_PROXIES="${TRUSTED_PROXIES:-*}" SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-true}" FORCE_HTTPS="${FORCE_HTTPS:-true}"
export ANALYTICS_URL="${ANALYTICS_URL:-http://127.0.0.1:8001}" PAYMENT_GATEWAY="${PAYMENT_GATEWAY:-sandbox}" PORT="${PORT:-8080}"

# A. Công tắc dịch vụ Python. supervisord đọc biến ANALYTICS_AUTOSTART.
export ANALYTICS_ENABLED="${ANALYTICS_ENABLED:-true}"
if [ "$ANALYTICS_ENABLED" = "false" ] || [ "$ANALYTICS_ENABLED" = "0" ]; then
  export ANALYTICS_AUTOSTART=false
else
  export ANALYTICS_AUTOSTART=true
fi
echo "[paas] APP_URL=$APP_URL DB_HOST=$DB_HOST PORT=$PORT ANALYTICS=$ANALYTICS_ENABLED"

mkdir -p storage/logs storage/app/public/panoramas storage/app/public/tiles storage/app/backups storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 1. APP_KEY và khoá HMAC sandbox: sinh một lần, lưu trong volume storage
KEY_FILE=storage/app/.appkey
if [ -z "$APP_KEY" ]; then
  echo "[paas] CẢNH BÁO: APP_KEY chưa được đặt. Trên nền tảng KHÔNG có ổ đĩa bền vững"
  echo "[paas]            (ví dụ Render gói Free), khoá sẽ đổi sau mỗi lần khởi động lại"
  echo "[paas]            và toàn bộ phiên đăng nhập, dữ liệu mã hoá sẽ không dùng được."
  [ -s "$KEY_FILE" ] || run_as_www sh -c "umask 077; php -r 'echo \"base64:\".base64_encode(random_bytes(32)).PHP_EOL;' > $KEY_FILE"
  export APP_KEY="$(cat $KEY_FILE)"
fi
SECRET_FILE=storage/app/.payment_secret
if [ -z "$PAYMENT_SANDBOX_SECRET" ] || [ "$PAYMENT_SANDBOX_SECRET" = "sandbox-secret-change-me" ]; then
  [ -s "$SECRET_FILE" ] || run_as_www sh -c "umask 077; php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;' > $SECRET_FILE"
  export PAYMENT_SANDBOX_SECRET="$(cat $SECRET_FILE)"
fi
if [ -z "$ANALYTICS_TOKEN" ]; then
  TOKEN_FILE=storage/app/.analytics_token
  [ -s "$TOKEN_FILE" ] || run_as_www sh -c "umask 077; php -r 'echo bin2hex(random_bytes(24)).PHP_EOL;' > $TOKEN_FILE"
  export ANALYTICS_TOKEN="$(cat $TOKEN_FILE)"
fi

# 2. Nginx: lắng nghe PORT của nền tảng, php-fpm cùng container
sed -e "s/listen 80 default_server;/listen ${PORT} default_server;/" -e "s/fastcgi_pass app:9000;/fastcgi_pass 127.0.0.1:9000;/" -e "/^server_tokens off;/d" \
    /etc/nginx/templates/default.conf.template | FORCE_HTTPS="$FORCE_HTTPS" envsubst '${FORCE_HTTPS}' > /etc/nginx/conf.d/default.conf
grep -q "^\s*server_tokens off;" /etc/nginx/nginx.conf || sed -i "s/^http {/http {\n\tserver_tokens off;/" /etc/nginx/nginx.conf
nginx -t >/dev/null

# 3. Chờ CSDL (tối đa ~3 phút)
echo "[paas] waiting for database ${DB_HOST}:${DB_PORT} ..."
for i in $(seq 1 90); do
  php -r 'try{new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"),getenv("DB_USERNAME"),getenv("DB_PASSWORD"));exit(0);}catch(Exception $e){exit(1);}' && break
  sleep 2
done

# 4. Migrate + seed lần đầu
run_as_www php artisan migrate --force
SEEDED="$(run_as_www php artisan app:is-seeded)"
if [ "$SEEDED" != "yes" ]; then
  echo "[paas] seeding Naturalis sample data ..."
  run_as_www php artisan db:seed --force
fi
ln -sfn /var/www/html/storage/app/public public/storage
install -d -o analytics -g www-data -m 2775 storage/app/public/tiles

# 4b. Khôi phục ảnh khi hệ thống tệp không bền vững (Render Free, Fly không volume…)
#     CSDL đã seed nhưng thư mục ảnh rỗng => chạy lại các seeder chỉ cập nhật
#     bản ghi (updateOrCreate) và chép lại tệp ảnh. Chạy nền để trang lên nhanh.
if [ "$SEEDED" = "yes" ] && [ -z "$(ls -A storage/app/public/artifacts 2>/dev/null)" ]; then
  echo "[paas] storage rỗng nhưng CSDL đã có dữ liệu – khôi phục ảnh ở chế độ nền"
  (
    for S in MuseumSeeder SceneSeeder ArtifactSeeder HotspotSeeder TourSeeder BlogSeeder; do
      run_as_www php artisan db:seed --class="Database\\Seeders\\$S" --force >/dev/null 2>&1 || true
    done
    echo "[paas] khôi phục ảnh xong"
  ) &
fi

# 5. Chỉ mục tìm kiếm + tiles 360° – chỉ khi dịch vụ Python được bật
if [ "$ANALYTICS_AUTOSTART" = "true" ]; then
  (
    for i in $(seq 1 40); do
      if run_as_www php artisan app:reindex-search --timeout=60 >/dev/null 2>&1; then
        echo "[paas] analytics reindex OK"; run_as_www php artisan app:generate-tiles --missing || true; exit 0
      fi
      sleep 5
    done
    echo "[paas] analytics unavailable – FULLTEXT fallback, equirectangular fallback"
  ) &
else
  echo "[paas] ANALYTICS_ENABLED=false – bỏ qua reindex/tiles, dùng FULLTEXT + equirectangular"
fi

run_as_www php artisan optimize:clear >/dev/null
run_as_www php artisan optimize >/dev/null
chown -R www-data:www-data storage bootstrap/cache
chown -R analytics:www-data storage/app/public/tiles
chmod -R g+rwX storage/app/public/tiles
# Biến môi trường cho các tiến trình con của supervisord
export ANALYTICS_TOKEN APP_KEY PAYMENT_SANDBOX_SECRET DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE ANALYTICS_AUTOSTART
echo "[paas] starting supervisord (nginx + php-fpm + scheduler$([ "$ANALYTICS_AUTOSTART" = "true" ] && echo " + analytics")) on port $PORT"
exec "$@"
