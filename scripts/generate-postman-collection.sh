#!/usr/bin/env bash
# OpenAPI 3.0 spec'i Postman 2.1 collection'a dönüştürür.
# Idempotent — her çalıştırmada docs/postman/eticaret-demo.postman_collection.json
# üzerine yazar. Ardından login request'inin "Tests" alanına auto-token
# JavaScript'ini enjekte eder (Postman'de token'ları otomatik environment'a yazsın).
#
# Kullanım: bash scripts/generate-postman-collection.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SPEC="${ROOT_DIR}/backend/storage/api-docs/api-docs.json"
OUT_DIR="${ROOT_DIR}/docs/postman"
OUT_FILE="${OUT_DIR}/eticaret-demo.postman_collection.json"

mkdir -p "${OUT_DIR}"

# 1) Spec güncel mi?
if [[ ! -f "${SPEC}" ]]; then
  echo "[postman] OpenAPI spec yok. Önce backend tarafında \`php artisan l5-swagger:generate\` çalıştır." >&2
  exit 1
fi

# 2) openapi-to-postmanv2 install (idempotent)
if [[ ! -f "${ROOT_DIR}/node_modules/.bin/openapi2postmanv2" ]]; then
  echo "[postman] openapi-to-postmanv2 yok — npm install çalıştırılıyor"
  (cd "${ROOT_DIR}" && npm install --no-audit --no-fund >/dev/null)
fi

echo "[postman] Generating collection from ${SPEC}"
"${ROOT_DIR}/node_modules/.bin/openapi2postmanv2" \
  -s "${SPEC}" \
  -o "${OUT_FILE}" \
  -p \
  -O folderStrategy=Tags,requestNameSource=Fallback >/dev/null

# 3) Auto-token script — POST /auth/login cevabından accessToken/refreshToken
# alıp environment'a yazsın. Manuel JSON edit yerine Node ile inject ediyoruz
# çünkü openapi-to-postmanv2 native event hook üretmiyor.
node "${ROOT_DIR}/scripts/inject-postman-auth.mjs" "${OUT_FILE}"

echo "[postman] OK → ${OUT_FILE}"
echo "[postman] Environment: ${OUT_DIR}/eticaret-demo.postman_environment.json"
