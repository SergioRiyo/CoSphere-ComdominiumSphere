#!/bin/sh
set -eu
cd /var/www/html
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi
if [ "${RUN_ANNOUNCEMENT_WORKER:-false}" = "true" ]; then
    su -s /bin/sh www-data -c 'while true; do php artisan queue:work database --queue=announcement-email --sleep=3 --tries=3 --backoff=60 --timeout=60; sleep 5; done' &
fi
sed -i "s/Listen 80/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
