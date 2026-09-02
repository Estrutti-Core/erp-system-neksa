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
# Usa a mesma base do runtime para que as extensoes exigidas pelo composer.lock
# (gd, zip, intl, ...) existam tambem na hora de resolver as dependencias.
FROM serversideup/php:8.4-cli AS vendor
USER root
RUN install-php-extensions pgsql pdo_pgsql redis gd zip bcmath intl pcntl
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
# Retry: a API do GitHub as vezes devolve 504 e derruba o download dos pacotes.
RUN for i in 1 2 3; do \
      composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --no-progress && exit 0; \
      echo "composer install falhou (tentativa $i), repetindo em 15s..."; sleep 15; \
    done; exit 1
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- 3) runtime (nginx + php-fpm em um container) ----------
FROM serversideup/php:8.4-fpm-nginx AS app

ENV PHP_OPCACHE_ENABLE=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    AUTORUN_ENABLED=false \
    SHOW_WELCOME_MESSAGE=false

USER root
RUN install-php-extensions pgsql pdo_pgsql redis gd zip bcmath intl pcntl
USER www-data

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# bootstrap/cache guarda o manifest de pacotes gerado com as deps de dev;
# mantido, ele quebra a aplicacao procurando pacotes que --no-dev removeu.
RUN rm -rf .env .git tests node_modules bootstrap/cache/*.php \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache
