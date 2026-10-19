# BaoTang360 – image ứng dụng Laravel chạy php-fpm 8.3 (Nginx 1.27 là service "web" riêng trong compose.yaml)

# ---------- Stage 1: build front-end assets (Vite) ----------
# Context build = thư mục app/ (như DiSanSo). Image app chạy nginx 1.27 + php-fpm 8.3 + scheduler trong một container (supervisord).
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY lang ./lang
RUN npm run build

# ---------- Stage 2: PHP dependencies (không có gói dev) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=ext-intl --ignore-platform-req=ext-gd
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------- Stage 3: php-fpm 8.3 + extension (dùng riêng làm image chạy PHPUnit: --target php-base) ----------
FROM php:8.3-fpm AS php-base
RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx supervisor gettext-base libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
      libicu-dev default-mysql-client curl unzip \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd opcache bcmath \
 && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

# ---------- Stage 4: runtime ----------
FROM php-base AS runtime

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
RUN chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx.conf /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/baotang360.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/backup.sh /usr/local/bin/backup.sh
RUN chmod +x /usr/local/bin/entrypoint /usr/local/bin/backup.sh

RUN mkdir -p /var/log/supervisor /run/nginx
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=180s CMD curl -fsS http://127.0.0.1/healthz || exit 1
ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
