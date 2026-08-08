#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
fi

exec docker-php-entrypoint "$@"
