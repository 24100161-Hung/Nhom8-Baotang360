#!/bin/bash
# Entrypoint container app (nginx + php-fpm + scheduler): chờ MySQL, sinh khóa, migrate, seed lần đầu,
# gọi Python (reindex + sinh tiles cho scene chưa có), optimize, chạy scheduler nền rồi exec php-fpm.
set -e
cd /var/www/html

# Mọi lệnh artisan/ghi tệp chạy dưới www-data (cùng user với worker php-fpm)
run_as_www() {
  if command -v setpriv >/dev/null 2>&1; then
    setpriv --reuid=www-data --regid=www-data --init-groups -- "$@"
  else
    su -s /bin/sh www-data -c "$(printf '%q ' "$@")"
  fi
}

mkdir -p storage/logs storage/app/public storage/app/public/panoramas storage/app/public/tiles storage/app/backups \
  storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 0. Nginx cùng container: php-fpm tại 127.0.0.1:9000, cổng 80 (template docker/nginx.conf; FORCE_HTTPS qua envsubst)
sed -e "s/fastcgi_pass app:9000;/fastcgi_pass 127.0.0.1:9000;/" -e "/^server_tokens off;/d" /etc/nginx/templates/default.conf.template \
  | FORCE_HTTPS="${FORCE_HTTPS:-false}" envsubst '${FORCE_HTTPS}' > /etc/nginx/conf.d/default.conf
grep -q "server_tokens off;" /etc/nginx/nginx.conf || sed -i 's/^http {/http {\n\tserver_tokens off;/' /etc/nginx/nginx.conf
nginx -t >/dev/null

# 1. APP_KEY: sinh một lần, lưu trong volume storage (không commit vào Git)
KEY_FILE=storage/app/.appkey
if [ -z "$APP_KEY" ]; then
  if [ ! -s "$KEY_FILE" ]; then
    run_as_www sh -c "umask 077; php -r 'echo \"base64:\".base64_encode(random_bytes(32)).PHP_EOL;' > $KEY_FILE"
  fi
  export APP_KEY="$(cat $KEY_FILE)"
fi

# 1b. Khóa HMAC cổng thanh toán sandbox (thiếu hoặc còn giá trị mẫu → sinh 32 byte ngẫu nhiên một lần)
SECRET_FILE=storage/app/.payment_secret
if [ -z "$PAYMENT_SANDBOX_SECRET" ] || [ "$PAYMENT_SANDBOX_SECRET" = "sandbox-secret-change-me" ]; then
  if [ ! -s "$SECRET_FILE" ]; then
    run_as_www sh -c "umask 077; php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;' > $SECRET_FILE"
    echo "[entrypoint] generated PAYMENT_SANDBOX_SECRET in $SECRET_FILE"
  fi
  export PAYMENT_SANDBOX_SECRET="$(cat $SECRET_FILE)"
fi

# 2. Chờ CSDL (tối đa ~2 phút)
echo "[entrypoint] waiting for database ${DB_HOST}:${DB_PORT:-3306} ..."
for i in $(seq 1 60); do
  if php -r 'try{new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT")?:3306),getenv("DB_USERNAME"),getenv("DB_PASSWORD"));exit(0);}catch(Exception $e){exit(1);}'; then
    break
  fi
  sleep 2
done

# 3. Migrate; seed khi bảng roles trống
run_as_www php artisan migrate --force
if [ "$(run_as_www php artisan app:is-seeded)" != "yes" ]; then
  echo "[entrypoint] seeding Naturalis sample data ..."
  run_as_www php artisan db:seed --force
fi

# public/storage → storage/app/public (Nginx mount cùng đường dẫn nên symlink dùng được)
ln -sfn /var/www/html/storage/app/public public/storage

# 4. Python: dựng chỉ mục tìm kiếm + sinh tiles đa phân giải cho scene chưa có (chạy nền, không chặn khởi động)
(
  for i in $(seq 1 20); do
    if run_as_www php artisan app:reindex-search --timeout=60 >/dev/null 2>&1; then
      echo "[entrypoint] analytics reindex OK"
      run_as_www php artisan app:generate-tiles --missing || true
      exit 0
    fi
    sleep 5
  done
  echo "[entrypoint] analytics unavailable - search uses MySQL FULLTEXT fallback, tiles use equirectangular fallback"
) &

run_as_www php artisan optimize:clear >/dev/null
run_as_www php artisan optimize >/dev/null

# 5. Lịch nền do supervisord chạy (program:scheduler): schedule:run mỗi phút

chown -R www-data:www-data storage bootstrap/cache
# Thư mục tiles do dịch vụ Python (uid 10001, non-root) ghi: chủ sở hữu 10001, nhóm www-data, setgid
install -d -o 10001 -g www-data -m 2775 storage/app/public/tiles
chown 10001:www-data storage/app/public/tiles
# Biến đã sinh phải tới được các tiến trình con của supervisord
export APP_KEY PAYMENT_SANDBOX_SECRET ANALYTICS_TOKEN
echo "[entrypoint] starting $*"
exec "$@"
