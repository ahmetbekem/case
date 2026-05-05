# syntax=docker/dockerfile:1.7

# ─────────────────────────────────────────────────────────────────────
# Stage 1 — Composer dependencies
# Cached as long as composer.json/composer.lock don't change. Code
# edits do NOT invalidate vendor/ — Docker layer cache stays warm.
# ─────────────────────────────────────────────────────────────────────
FROM composer:2.7 AS deps

WORKDIR /app

COPY composer.json composer.lock ./

# --ignore-platform-reqs — the composer:2.7 image ships PHP 8.3 + a
# minimal extension set, but our lockfile pins symfony/translation v8.0
# (PHP 8.4+) and depends on ext-sockets / ext-pdo_pgsql. Those are all
# present in the runtime stage (php:8.4-fpm-alpine with our docker-php-
# ext-install line) — the builder stage just downloads vendor/ tarballs
# and skips the platform check.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-progress \
    --no-interaction \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction --ignore-platform-reqs


# ─────────────────────────────────────────────────────────────────────
# Stage 2 — Runtime: nginx + php-fpm + supervisor
# php:8.4-fpm-alpine because PHP 8.5 official Docker image is not GA
# yet (cutting-edge brew install on dev hosts is a different story);
# Laravel 11 supports PHP 8.2+ so 8.4 is the latest officially-blessed
# combination right now. Bump to 8.5 once it lands on hub.docker.com.
# ─────────────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

# System packages
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        bash \
        # postgresql-libs ships the runtime libpq.so the pdo_pgsql .so needs.
        # Without it, `apk del .build-deps` below would yank libpq with the
        # dev headers and PHP would fail "Unable to load dynamic library
        # 'pdo_pgsql' ... libpq.so.5: No such file" on every request.
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        autoconf \
        g++ \
        make \
        postgresql-dev \
        linux-headers \
    && docker-php-ext-install \
        pdo_pgsql \
        sockets \
        bcmath \
        opcache \
        pcntl \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Configs
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf     /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh    /entrypoint.sh
RUN chmod +x /entrypoint.sh

# App code (vendor/ included, optimized autoloader)
WORKDIR /var/www
COPY --from=deps --chown=www-data:www-data /app /var/www

# Storage + bootstrap/cache must be writable for Laravel
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

# k8s-style probe: hits Laravel's withRouting health endpoint /up.
# /health is the richer one with DB+Redis checks (see routes/web.php).
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
