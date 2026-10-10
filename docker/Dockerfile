# syntax=docker/dockerfile:1.7
# Image produksi Regulasi FKIP Unsil (STANDAR-TEKNIS §7, pola docker-apps seperti keuangan).
# Bangun: docker build -f docker/Dockerfile --target app --build-arg ASSET_URL=https://supportfkip.unsil.ac.id/regulasi -t regulasi-app:<tag> .
#         docker build -f docker/Dockerfile --target web --build-arg ASSET_URL=https://supportfkip.unsil.ac.id/regulasi -t regulasi-web:<tag> .

ARG PHP_VERSION=8.5

# ---------- base: PHP-FPM + ekstensi + poppler-utils ----------
FROM php:${PHP_VERSION}-fpm-alpine AS base

# poppler-utils: pdftotext/pdfinfo untuk ekstraksi teks PDF dari tautan (F7.1, spatie/pdf-to-text).
RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype fcgi poppler-utils tzdata \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath gd zip pcntl exif \
 && (php -m | grep -qi '^Zend OPcache$' || docker-php-ext-install opcache) \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del .build-deps \
 && rm -rf /tmp/pear \
 && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime && echo "Asia/Jakarta" > /etc/timezone

COPY docker/php/produksi.ini /usr/local/etc/php/conf.d/zz-produksi.ini
COPY docker/php/fpm-ping.conf /usr/local/etc/php-fpm.d/zz-ping.conf
WORKDIR /var/www/html

# ---------- vendor: dependensi Composer tanpa dev ----------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# ---------- aset: build Vite dengan ASSET_URL subpath ----------
FROM node:22-alpine AS aset
ARG ASSET_URL=https://supportfkip.unsil.ac.id/regulasi
ENV ASSET_URL=${ASSET_URL}
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# Sumber kelas Tailwind (@source di resources/css/app.css).
COPY app/View ./app/View
COPY app/Livewire ./app/Livewire
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ---------- app: PHP-FPM (juga dipakai queue/Horizon & scheduler) ----------
FROM base AS app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data --from=aset /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && php artisan filament:assets \
 && rm -f /usr/bin/composer \
 && mkdir -p storage/app/tmp/ekspor storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache public \
 && pdftotext -v

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint

USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------- web: Nginx dengan berkas publik (index.php, build, aset Filament, panduan) ----------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
