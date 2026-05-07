#!/usr/bin/env bash
# Production deploy guard — checks .env values before allowing a
# production rollout. Designed to run in CI/CD pre-deploy step:
#   bash scripts/validate-prod-env.sh path/to/production/.env
#
# Exits non-zero on any violation. Sample violations:
#   - APP_DEBUG=true
#   - APP_ENV != production
#   - APP_KEY / JWT_SECRET / BACKEND_PROXY_SECRET empty
#   - SECURITY_ALLOWED_ORIGINS contains localhost
#
# Usage in deploy pipeline:
#   bash scripts/validate-prod-env.sh backend/.env || exit 1

set -euo pipefail

ENV_FILE="${1:-backend/.env}"

if [ ! -f "$ENV_FILE" ]; then
  echo "❌ ENV file not found: $ENV_FILE"
  exit 1
fi

errors=0
warn=0

require_value() {
  local key="$1"
  local pattern="$2"
  local message="$3"
  local value
  value=$(grep -E "^${key}=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"')
  if [ -z "$value" ]; then
    echo "❌ $key is empty — $message"
    errors=$((errors + 1))
    return
  fi
  if ! echo "$value" | grep -qE "$pattern"; then
    echo "❌ $key='$value' invalid — $message"
    errors=$((errors + 1))
  fi
}

forbid_value() {
  local key="$1"
  local pattern="$2"
  local message="$3"
  local value
  value=$(grep -E "^${key}=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"')
  if echo "$value" | grep -qE "$pattern"; then
    echo "❌ $key='$value' forbidden — $message"
    errors=$((errors + 1))
  fi
}

echo "🔍 Validating $ENV_FILE for production…"
echo

# ─── Critical: debug must be off ────────────────────────────
require_value APP_ENV '^production$' "must be 'production'"
forbid_value APP_DEBUG '^(true|1|on|yes)$' "MUST be false in production (stack-trace leak)"

# ─── Critical: secrets ──────────────────────────────────────
require_value APP_KEY '^base64:.{40,}' "must be set (php artisan key:generate)"
require_value JWT_SECRET '^.{32,}' "must be 32+ chars (bin2hex(random_bytes(32)) or base64)"
require_value BACKEND_PROXY_SECRET '^[0-9a-f]{32,}' "must be 32+ hex chars; same value in frontend .env"

# ─── Critical: DB credentials ───────────────────────────────
require_value DB_HOST '^[A-Za-z0-9._-]+$' "must point to internal DB host"
forbid_value DB_HOST '127\.0\.0\.1|localhost' "should NOT be localhost in production"
require_value DB_PASSWORD '.{12,}' "min 12 chars; rotate quarterly"

# ─── Important: cookie security ─────────────────────────────
forbid_value SECURITY_ALLOWED_ORIGINS 'localhost' "must not contain localhost"
forbid_value SECURITY_ALLOWED_ORIGINS '^http://' "must be https:// only"

# ─── Important: log noise ───────────────────────────────────
LOG_LEVEL=$(grep -E "^LOG_LEVEL=" "$ENV_FILE" | head -1 | cut -d= -f2-)
if [ "$LOG_LEVEL" = "debug" ]; then
  echo "⚠️  LOG_LEVEL=debug — production log volume riski; info+ önerilir"
  warn=$((warn + 1))
fi

# ─── Important: swagger regen off ───────────────────────────
SWAG=$(grep -E "^L5_SWAGGER_GENERATE_ALWAYS=" "$ENV_FILE" | head -1 | cut -d= -f2-)
if [ "$SWAG" = "true" ]; then
  echo "⚠️  L5_SWAGGER_GENERATE_ALWAYS=true — production'da OFF olmalı (build-time generate)"
  warn=$((warn + 1))
fi

# ─── Important: bcrypt rounds ───────────────────────────────
ROUNDS=$(grep -E "^BCRYPT_ROUNDS=" "$ENV_FILE" | head -1 | cut -d= -f2-)
if [ -n "$ROUNDS" ] && [ "$ROUNDS" -lt 12 ]; then
  echo "⚠️  BCRYPT_ROUNDS=$ROUNDS — production min 12 önerilir"
  warn=$((warn + 1))
fi

echo
if [ $errors -gt 0 ]; then
  echo "❌ $errors critical error(s), $warn warning(s) — DEPLOY BLOCKED"
  exit 1
fi

if [ $warn -gt 0 ]; then
  echo "⚠️  $warn warning(s) — review before deploying"
fi
echo "✅ ENV validated; production deploy approved"
