#!/usr/bin/env bash
#
# CI gate — refuses to ship a frontend bundle that leaks any backend
# URL or shared secret into the client JavaScript.
#
# Runs after `pnpm build`. Greps .next/static/ for forbidden tokens.
# Exit 0 = clean, exit 1 = leak.
#
# SECURITY.md §3.2 — Direct Backend Access Prevention.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="${SCRIPT_DIR}/../frontend"

if [ ! -d "${FRONTEND_DIR}/.next/static" ]; then
  echo "[leak-check] FAIL: ${FRONTEND_DIR}/.next/static does not exist."
  echo "[leak-check]        Run 'pnpm --dir=${FRONTEND_DIR} build' first."
  exit 1
fi

# Patterns we consider sensitive. The literal env-var NAMES are flagged
# (so anyone accidentally reading process.env.LARAVEL_API_URL gets caught),
# plus a few known-bad URL substrings.
LEAK_PATTERNS=(
  'LARAVEL_API_URL'
  'BACKEND_PROXY_SECRET'
  'JWT_SECRET'
  'RABBITMQ_PASSWORD'
  'POSTGRES_PASSWORD'
  'DB_PASSWORD'
)

# Also catch any obvious internal-only host names that the build might
# inline. Add new ones here as the prod stack evolves.
HOST_PATTERNS=(
  'http://backend/'
  'http://backend:'
)

cd "${FRONTEND_DIR}"

LEAKS=0
SCAN_DIR=".next/static"

echo "[leak-check] Scanning ${SCAN_DIR} for backend secrets…"

for pattern in "${LEAK_PATTERNS[@]}" "${HOST_PATTERNS[@]}"; do
  # --include='*.js' so .map files (dev-only source maps) don't trip the
  # scan. -F = fixed-string match, no regex meta interpretation.
  if grep -rIl --include='*.js' -F "${pattern}" "${SCAN_DIR}" >/dev/null 2>&1; then
    echo "[leak-check] FAIL: '${pattern}' found in client bundle"
    grep -rIn --include='*.js' -F "${pattern}" "${SCAN_DIR}" | head -5
    LEAKS=$((LEAKS + 1))
  fi
done

if [ "${LEAKS}" -gt 0 ]; then
  echo
  echo "[leak-check] ${LEAKS} leaked token(s) found in .next/static."
  echo "[leak-check] Fix: keep all backend URLs / secrets out of NEXT_PUBLIC_*"
  echo "[leak-check]      and never import @/shared/api/server-fetch from a"
  echo "[leak-check]      'use client' file."
  exit 1
fi

echo "[leak-check] OK — no backend URLs or secrets in client bundle"
