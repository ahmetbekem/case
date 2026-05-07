#!/usr/bin/env bash
# ADR-017 — Guest cart e2e smoke test against running stack.
#
# Stack (docker compose) ayakta olmalı + en az 1 ürün seedlenmiş olmalı.
# Bu script frontend BFF üzerinden tüm guest cart akışını exercise
# eder: cookie üretim → add → persist → guest checkout block →
# register-with-merge → user cart ownership doğrulaması.
#
# Bash sürümü: macOS 3.2 ile uyumlu kalmak için POSIX features.

set -euo pipefail

FRONTEND_URL="${FRONTEND_URL:-http://localhost:3030}"
JQ="${JQ:-jq}"

bold() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
fail() { printf '  \033[31m✗\033[0m %s\n' "$*" >&2; exit 1; }

require() { command -v "$1" >/dev/null 2>&1 || fail "Missing prerequisite: $1"; }
require curl
require "$JQ"

bold "1) Anonymous GET /sales/cart issues a guest_cart_token"
TMP1=$(mktemp)
JAR=$(mktemp)
HTTP=$(curl -s -c "$JAR" -o "$TMP1" -w "%{http_code}" "$FRONTEND_URL/api/sales/cart")
[ "$HTTP" = "200" ] || fail "Expected 200 on first cart fetch, got $HTTP"
SID=$($JQ -r '.data.guestSessionId' "$TMP1")
[ "$SID" != "null" ] && [ -n "$SID" ] || fail "Guest session id missing in response"
$JQ -e '.data.isGuest == true and .data.userId == null' "$TMP1" >/dev/null || fail "isGuest/userId mismatch"
ok "guestSessionId=$SID, isGuest=true"

bold "2) Adding to cart persists across requests with same cookie"
PID=$(curl -s "$FRONTEND_URL/api/catalog/products?limit=1" | $JQ -r '.data[0].id')
[ -n "$PID" ] && [ "$PID" != "null" ] || fail "No products available — seed catalog first"
TMP2=$(mktemp)
HTTP=$(curl -s -X POST -H "Cookie: guest_cart_token=$SID" -H "Content-Type: application/json" \
  -o "$TMP2" -w "%{http_code}" \
  -d "{\"productId\":\"$PID\",\"quantity\":2}" \
  "$FRONTEND_URL/api/sales/cart/items")
[ "$HTTP" = "200" ] || fail "Expected 200 on add, got $HTTP"
$JQ -e '.data.totalQuantity == 2 and .data.itemCount == 1' "$TMP2" >/dev/null || fail "Cart totals wrong after add"
ok "added 2x product=$PID; totalQuantity=2"

bold "3) Guest checkout is blocked (POST /sales/orders → 401)"
TMP3=$(mktemp)
HTTP=$(curl -s -X POST -H "Cookie: guest_cart_token=$SID" -H "Content-Type: application/json" \
  -o "$TMP3" -w "%{http_code}" \
  -d '{"displayCurrency":"TRY"}' \
  "$FRONTEND_URL/api/sales/orders")
[ "$HTTP" = "401" ] || fail "Expected 401 on guest checkout, got $HTTP"
ok "guest order blocked with 401"

bold "4) Register with guest cookie merges cart"
EMAIL="smoke-$(date +%s)@guest-cart.test"
TMP4=$(mktemp)
HTTP=$(curl -s -X POST -H "Cookie: guest_cart_token=$SID" -H "Content-Type: application/json" \
  -o "$TMP4" -w "%{http_code}" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"pass1234\",\"fullName\":\"Smoke E2E\"}" \
  "$FRONTEND_URL/api/auth/register")
[ "$HTTP" = "201" ] || fail "Expected 201 on register, got $HTTP (body: $(cat "$TMP4"))"
$JQ -e '.data.cartMerged == true' "$TMP4" >/dev/null || fail "cartMerged != true after register-with-cookie"
ok "registered $EMAIL, cartMerged=true"

bold "5) Logged-in cart belongs to user, retains items"
LOGIN_JAR=$(mktemp)
curl -s -X POST -H "Content-Type: application/json" \
  -c "$LOGIN_JAR" -o /dev/null \
  -d "{\"email\":\"$EMAIL\",\"password\":\"pass1234\"}" \
  "$FRONTEND_URL/api/auth/login"
TMP5=$(mktemp)
curl -s -b "$LOGIN_JAR" -o "$TMP5" "$FRONTEND_URL/api/sales/cart"
$JQ -e '.data.isGuest == false and .data.userId != null and .data.totalQuantity == 2' "$TMP5" >/dev/null \
  || fail "User cart didn't inherit guest items (body: $(cat "$TMP5"))"
ok "user owns cart with 2 quantity"

bold "ADR-017 smoke OK"
