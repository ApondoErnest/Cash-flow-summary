# syntax=docker/dockerfile:1

# Stage 1 — PHP dependencies (Composer on PHP with pcntl — Horizon requires ext-pcntl)
FROM php:8.4-cli-bookworm AS vendor

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install pcntl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Stage 2 — Frontend assets (no host Node required)
FROM node:24-bookworm AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /app/vendor ./vendor

ENV VITE_APP_NAME="Cash Flow Summary"

RUN npm run build

# Stage 3 — Production PHP-FPM runtime
FROM php:8.4-fpm-bookworm

ARG APP_ENV=production

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN if [ -f public/fonts-manifest.dev.json ] && [ ! -f public/fonts-manifest.json ]; then \
        cp public/fonts-manifest.dev.json public/fonts-manifest.json; \
    fi

RUN test -f public/build/manifest.json

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker/php/conf.d/99-laravel.ini /usr/local/etc/php/conf.d/99-laravel.ini
COPY docker/php-fpm/zz-laravel-env.conf /usr/local/etc/php-fpm.d/zz-laravel-env.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts \
    && rm /usr/bin/composer

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
