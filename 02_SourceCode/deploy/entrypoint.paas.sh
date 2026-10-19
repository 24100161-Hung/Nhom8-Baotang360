#!/bin/bash
# Entrypoint PaaS (Railway/Render): suy ra APP_URL và DB_* từ biến nền tảng, sinh khóa, chờ CSDL, migrate, seed,
# cấu hình nginx theo PORT, dựng chỉ mục + tiles (dịch vụ Python chạy tạm), rồi giao cho supervisord.
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
  eval "$(php -r '$u=parse_url(getenv("DB_URL")); printf("export DB_HOST=%s DB_PORT=%s DB_USERNAME=%s DB_PASSWORD=%s DB_DATABASE=%s\n", escapeshellarg($u["host"]??""), escapeshellarg($u["port"]??3306), escapeshellarg($u["user"]??""), escapeshellarg($u["pass"]??""), escapeshellarg(ltrim($u["path"]??"","/")));')"
else
  export DB_HOST="${DB_HOST:-$MYSQLHOST}" DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}" DB_USERNAME="${DB_USERNAME:-$MYSQLUSER}" DB_PASSWORD="${DB_PASSWORD:-$MYSQLPASSWORD}" DB_DATABASE="${DB_DATABASE:-$MYSQLDATABASE}"
fi
export DB_CONNECTION=mysql APP_ENV="${APP_ENV:-production}" APP_DEBUG="${APP_DEBUG:-false}" APP_LOCALE="${APP_LOCALE:-vi}" APP_FALLBACK_LOCALE="${APP_FALLBACK_LOCALE:-en}"
export SESSION_DRIVER="${SESSION_DRIVER:-database}" CACHE_STORE="${CACHE_STORE:-database}" QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}" MAIL_MAILER="${MAIL_MAILER:-log}"
export LOG_CHANNEL="${LOG_CHANNEL:-stack}" LOG_STACK="${LOG_STACK:-daily}" LOG_LEVEL="${LOG_LEVEL:-info}"
export TRUSTED_PROXIES="${TRUSTED_PROXIES:-*}" SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-true}" FORCE_HTTPS="${FORCE_HTTPS:-true}"
export ANALYTICS_URL="${ANALYTICS_URL:-http://127.0.0.1:8001}" PAYMENT_GATEWAY="${PAYMENT_GATEWAY:-sandbox}" PORT="${PORT:-8080}"
echo "[paas] APP_URL=$APP_URL DB_HOST=$DB_HOST PORT=$PORT"

mkdir -p storage/logs storage/app/public/panoramas storage/app/public/tiles storage/app/backups storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 1. APP_KEY và khóa HMAC sandbox: sinh một lần, lưu trong volume storage
KEY_FILE=storage/app/.appkey
if [ -z "$APP_KEY" ]; then
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
# server_tokens đã có ở nginx.conf của Debian → bỏ khỏi conf.d để tránh trùng; đảm bảo tắt ở cấp http
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
if [ "$(run_as_www php artisan app:is-seeded)" != "yes" ]; then
  echo "[paas] seeding Naturalis sample data ..."
  run_as_www php artisan db:seed --force
fi
ln -sfn /var/www/html/storage/app/public public/storage
install -d -o analytics -g www-data -m 2775 storage/app/public/tiles

# 5. Chỉ mục tìm kiếm + tiles 360° (chạy nền sau khi dịch vụ Python lên)
(
  for i in $(seq 1 40); do
    if run_as_www php artisan app:reindex-search --timeout=60 >/dev/null 2>&1; then
      echo "[paas] analytics reindex OK"; run_as_www php artisan app:generate-tiles --missing || true; exit 0
    fi
    sleep 5
  done
  echo "[paas] analytics unavailable – FULLTEXT fallback, equirectangular fallback"
) &

run_as_www php artisan optimize:clear >/dev/null
run_as_www php artisan optimize >/dev/null
chown -R www-data:www-data storage bootstrap/cache
# Toàn bộ cây tiles (kể cả .status/*.json từ lần chạy trước) phải ghi được bởi dịch vụ Python
chown -R analytics:www-data storage/app/public/tiles
chmod -R g+rwX storage/app/public/tiles
# Biến môi trường cho các tiến trình con của supervisord
export ANALYTICS_TOKEN APP_KEY PAYMENT_SANDBOX_SECRET DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE
echo "[paas] starting supervisord (nginx + php-fpm + analytics + scheduler) on port $PORT"
exec "$@"
