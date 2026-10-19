#!/bin/sh
# Sao lưu CSDL BaoTang360 (NFR dữ liệu): dump MySQL vào storage/app/backups, quyền 0600, giữ 7 bản mới nhất.
# Chạy tự động 02:00 bởi scheduler (app:backup-db); chạy tay: docker compose exec app backup.sh
# Khôi phục: docker compose exec -T db mysql -ubaotang360 -p baotang360 < storage/app/backups/<file>.sql
set -e
cd /var/www/html
if [ "$(id -u)" = "0" ]; then
  exec setpriv --reuid=www-data --regid=www-data --init-groups -- php artisan app:backup-db --keep="${BACKUP_KEEP:-7}"
fi
exec php artisan app:backup-db --keep="${BACKUP_KEEP:-7}"
