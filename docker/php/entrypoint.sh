#!/bin/sh
# Production start: install the mounted JWT keys for the PHP-FPM workers (www-data), warm the cache, start FPM.
set -eu

for key in private.pem public.pem; do
    if [ -f "/run/jwt/${key}" ]; then
        install -o www-data -g www-data -m 0400 "/run/jwt/${key}" "config/jwt/${key}"
    fi
done

php bin/console cache:warmup --no-interaction
chown -R www-data:www-data var

exec "$@"
