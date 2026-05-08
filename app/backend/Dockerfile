# syntax=docker/dockerfile:1

# ─── Base stage ──────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        mysql-client \
        libpng-dev \
        oniguruma-dev \
        libxml2-dev \
        zip \
        unzip \
        curl \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─── Development target ───────────────────────────────────────────────────────
FROM base AS dev

COPY composer*.json ./
RUN composer install --no-interaction --no-scripts

COPY . .

EXPOSE 8081
CMD ["sh", "-c", "mkdir -p bootstrap/cache && chmod -R 775 bootstrap/cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${APP_PORT:-8081}"]

# ─── Production target ────────────────────────────────────────────────────────
FROM base AS prod

COPY composer*.json ./
RUN composer install --no-interaction --no-dev --optimize-autoloader

COPY . .
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
