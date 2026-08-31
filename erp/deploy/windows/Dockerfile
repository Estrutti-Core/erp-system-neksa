# syntax=docker/dockerfile:1

# ---------- 1) assets front-end ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
RUN npm run build

# ---------- 2) dependencias PHP ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- 3) runtime (nginx + php-fpm em um container) ----------
FROM serversideup/php:8.4-fpm-nginx AS app

ENV PHP_OPCACHE_ENABLE=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    AUTORUN_ENABLED=false

USER root
RUN install-php-extensions pgsql pdo_pgsql redis gd zip bcmath intl
USER www-data

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN rm -rf .env .git tests node_modules \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache
