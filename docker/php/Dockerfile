# Targets: base (PHP-FPM with the extensions the app needs), dev (local, code mounted, runs as the host user).

FROM php:8.5-fpm-alpine AS base

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions intl opcache redis

WORKDIR /var/www


FROM base AS dev

# Match the host user so files written to the mounted project stay editable.
ARG UID=1000
ARG GID=1000
RUN addgroup -g "${GID}" app && adduser -D -u "${UID}" -G app app

USER app
