#!/bin/sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
rm -f public/hot
php artisan storage:link --force

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec supervisord -c /etc/supervisor/conf.d/oxi-ventas.conf
