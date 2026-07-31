#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# php-fpm gestiona su propio drop de privilegios internamente (www.conf: user=www-data);
# los procesos de artisan de larga duración (worker/scheduler) no lo hacen por sí solos,
# así que se les baja el privilegio aquí (02A §9: menor privilegio posible).
if [ "$1" = "php-fpm" ]; then
    exec "$@"
else
    exec gosu www-data "$@"
fi
