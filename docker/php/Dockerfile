# Targets: dev (local, code mounted, runs as the host user), assets (Vite build), app (production PHP-FPM),
# web (production Nginx with the compiled assets).

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


FROM node:22-alpine AS assets

WORKDIR /app

# Installed strictly from the committed lock file, without lifecycle scripts.
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts

COPY vite.config.js ./
COPY assets assets
COPY templates templates
RUN npm run build


FROM base AS app

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY . .
# Defaults without secrets; the real values come from the container environment, which takes precedence.
COPY .env.example .env
COPY --from=assets /app/public/build public/build

RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --classmap-authoritative --no-scripts \
    && mkdir -p var \
    && chown -R www-data:www-data var

COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

HEALTHCHECK --interval=10s --timeout=3s --retries=5 CMD pgrep -f "php-fpm: master" > /dev/null || exit 1


FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/public /var/www/public

HEALTHCHECK --interval=10s --timeout=3s --retries=5 CMD wget -qO /dev/null http://127.0.0.1/ || exit 1
