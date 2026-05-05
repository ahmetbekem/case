#!/bin/sh
set -e

# ─── Storage / cache permissions ────────────────────────────────────
# When the image runs against a host-mounted code volume in dev,
# host-owned files can flip away from www-data. Re-stamp here so
# Laravel always has somewhere to write logs and cache.
if [ -d "/var/www/storage" ]; then
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
fi

# ─── Optional: warm Laravel caches ──────────────────────────────────
# Disabled by default — opt in with WARM_CACHES=1. Production typically
# does this once during image build (not start) so containers boot fast,
# but the entrypoint hook is here for environments that prefer it.
if [ "${WARM_CACHES:-0}" = "1" ]; then
    php /var/www/artisan config:cache --no-interaction || true
    php /var/www/artisan route:cache --no-interaction || true
fi

# ─── Optional: run migrations on start ──────────────────────────────
# OFF by default — multi-replica deployments would race. Set
# RUN_MIGRATIONS=1 only on a single-replica dev compose or a one-off
# deploy hook.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php /var/www/artisan migrate --force --no-interaction
fi

# Hand off to whatever the CMD asked for (supervisord by default;
# the queue-worker compose service overrides with `php artisan queue:work`).
exec "$@"
