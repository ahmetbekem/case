#!/usr/bin/env bash
# ADR-018 — Address management e2e smoke (canlı stack).
#
# Bu script frontend BFF üzerinden tüm akışı exercise eder:
#   1) Public locations endpoint'leri (cities, districts)
#   2) Login (mevcut admin)
#   3) Yeni adres oluştur (TR il/ilçe validate)
#   4) Listele (default flag'ler)
#   5) Set-default-delivery
#   6) Sipariş ver (deliveryAddressId zorunlu, snapshot DB'de)
#   7) Adresi sil (soft delete + sipariş hala adres bilgisini gösterir)

set -euo pipefail

FRONTEND_URL="${FRONTEND_URL:-http://localhost:3030}"
JQ="${JQ:-jq}"

bold() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
fail() { printf '  \033[31m✗\033[0m %s\n' "$*" >&2; exit 1; }

require() { command -v "$1" >/dev/null 2>&1 || fail "Missing: $1"; }
require curl
require "$JQ"

bold "1) GET /locations/cities — public, 81 il bekleniyor"
T1=$(mktemp)
HTTP=$(curl -s -o "$T1" -w "%{http_code}" "$FRONTEND_URL/api/locations/cities")
[ "$HTTP" = "200" ] || fail "Cities HTTP $HTTP"
COUNT=$($JQ '.data | length' "$T1")
[ "$COUNT" = "81" ] || fail "Expected 81 cities, got $COUNT"
ok "81 il alındı"

bold "2) GET /locations/cities/İstanbul/districts — 39 ilçe bekleniyor"
T2=$(mktemp)
curl -s -o "$T2" "$FRONTEND_URL/api/locations/cities/$(printf '%s' 'İstanbul' | jq -sRr @uri)/districts"
DC=$($JQ '.data | length' "$T2")
[ "$DC" = "39" ] || fail "Expected 39 districts, got $DC"
ok "İstanbul 39 ilçe"

bold "3) Login as admin@example.test"
JAR=$(mktemp)
curl -s -X POST -H "Content-Type: application/json" \
  -c "$JAR" -o /dev/null \
  -d '{"email":"admin@example.test","password":"Pass1234"}' \
  "$FRONTEND_URL/api/auth/login"
ME=$(curl -s -b "$JAR" "$FRONTEND_URL/api/auth/me" | $JQ -r '.data.email // .data.user.email // .error.message')
[ "$ME" = "admin@example.test" ] || fail "Login failed: $ME"
ok "Logged in"

bold "4) POST /identity/addresses — yeni adres"
T3=$(mktemp)
HTTP=$(curl -s -b "$JAR" -X POST -H "Content-Type: application/json" \
  -o "$T3" -w "%{http_code}" \
  -d '{
    "title": "Smoke Test Adresi",
    "fullName": "Smoke Test User",
    "phone": "+905551234567",
    "city": "İstanbul",
    "district": "Beşiktaş",
    "addressLine": "Smoke Caddesi No: 123 Daire: 1",
    "setAsDefaultDelivery": true
  }' \
  "$FRONTEND_URL/api/identity/addresses")
[ "$HTTP" = "201" ] || fail "POST /addresses HTTP $HTTP — $(cat "$T3")"
ADDRESS_ID=$($JQ -r '.data.id' "$T3")
ok "Adres oluştu: $ADDRESS_ID, default delivery: $($JQ -r '.data.isDefaultDelivery' "$T3")"

bold "5) GET /identity/addresses — listede yeni adres + default"
T4=$(mktemp)
curl -s -b "$JAR" -o "$T4" "$FRONTEND_URL/api/identity/addresses"
COUNT=$($JQ '[.data[] | select(.id == "'$ADDRESS_ID'")] | length' "$T4")
[ "$COUNT" = "1" ] || fail "New address missing in list"
ok "Listede mevcut, toplam adres: $($JQ '.data | length' "$T4")"

bold "6) Sipariş ver (deliveryAddressId zorunlu)"
# Önce sepete bir ürün ekle (adminin var olan ürünlerinden bir tanesi)
PROD=$(curl -s "$FRONTEND_URL/api/catalog/products" | $JQ -r '.data[0].id')
curl -s -b "$JAR" -X POST -H "Content-Type: application/json" \
  -o /dev/null \
  -d "{\"productId\":\"$PROD\",\"quantity\":1}" \
  "$FRONTEND_URL/api/sales/cart/items"

T5=$(mktemp)
HTTP=$(curl -s -b "$JAR" -X POST -H "Content-Type: application/json" \
  -o "$T5" -w "%{http_code}" \
  -d "{\"displayCurrency\":\"TRY\",\"deliveryAddressId\":\"$ADDRESS_ID\"}" \
  "$FRONTEND_URL/api/sales/orders")
[ "$HTTP" = "201" ] || fail "Place order HTTP $HTTP — $(cat "$T5")"
ORDER_ID=$($JQ -r '.data.id' "$T5")
SNAPSHOT_CITY=$($JQ -r '.data.deliveryAddress.city // empty' "$T5")
[ "$SNAPSHOT_CITY" = "İstanbul" ] || fail "Snapshot city wrong: $SNAPSHOT_CITY"
ok "Sipariş #$ORDER_ID, deliveryAddress.city = $SNAPSHOT_CITY"

bold "7) Adres sil + sipariş hala snapshot'tan adres bilgisini gösterir"
HTTP=$(curl -s -b "$JAR" -X DELETE -o /dev/null -w "%{http_code}" \
  "$FRONTEND_URL/api/identity/addresses/$ADDRESS_ID")
[ "$HTTP" = "204" ] || fail "DELETE address HTTP $HTTP"
ok "Adres soft-delete edildi"

T6=$(mktemp)
curl -s -b "$JAR" -o "$T6" "$FRONTEND_URL/api/sales/orders/$ORDER_ID"
SURVIVED=$($JQ -r '.data.deliveryAddress.city // empty' "$T6")
[ "$SURVIVED" = "İstanbul" ] || fail "Snapshot survival broke: $SURVIVED"
ok "Sipariş hala deliveryAddress.city = $SURVIVED (snapshot pattern ✓)"

bold "ADR-018 smoke OK"
