# syntax=docker/dockerfile:1

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN if [ ! -f public/build/manifest.json ]; then \
        echo "Missing public/build/manifest.json — run ./deploy/build-assets.sh before building the Docker image." >&2; \
        exit 1; \
    fi

RUN if [ -f public/fonts-manifest.dev.json ] && [ ! -f public/fonts-manifest.json ]; then \
        cp public/fonts-manifest.dev.json public/fonts-manifest.json; \
    fi

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

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
