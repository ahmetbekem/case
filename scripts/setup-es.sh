#!/usr/bin/env bash
# Elasticsearch bootstrap — ILM policy + component template + index template'leri yükler.
# Idempotent: tekrar çalıştırıldığında PUT'lar mevcut tanımı günceller.
#
# Kullanım: ./scripts/setup-es.sh [es-url]
# Default: http://localhost:9200

set -euo pipefail

ES_URL="${1:-${ELASTICSEARCH_URL:-http://localhost:9200}}"
INFRA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/infrastructure/elasticsearch"

echo "[setup-es] target: ${ES_URL}"

# Cluster hazır olana kadar bekle (max 60s)
for i in {1..30}; do
  if curl -sf "${ES_URL}/_cluster/health" >/dev/null 2>&1; then
    echo "[setup-es] cluster reachable"
    break
  fi
  if [[ $i -eq 30 ]]; then
    echo "[setup-es] ERROR: cluster not reachable after 60s" >&2
    exit 1
  fi
  sleep 2
done

put() {
  local label="$1" url="$2" body="$3"
  local code
  code=$(curl -sS -o /tmp/es-resp -w '%{http_code}' \
    -X PUT "${url}" \
    -H 'Content-Type: application/json' \
    -d "${body}")
  if [[ "${code}" =~ ^2 ]]; then
    echo "[setup-es] ✓ ${label}"
  else
    echo "[setup-es] ✗ ${label} (HTTP ${code})" >&2
    cat /tmp/es-resp >&2
    echo >&2
    exit 1
  fi
}

# 1) ILM policies (önce — index template referans verecek)
for policy in logs-audit-policy logs-app-policy logs-access-policy; do
  put "ILM ${policy}" \
    "${ES_URL}/_ilm/policy/${policy}" \
    "$(cat "${INFRA_DIR}/ilm-policies/${policy}.json")"
done

# 2) Component templates
for component in logs-common logs-audit logs-access; do
  put "component ${component}" \
    "${ES_URL}/_component_template/${component}" \
    "$(cat "${INFRA_DIR}/templates/component-${component}.json")"
done

# 3) Index templates (component'leri compose ediyor)
for cat in audit app access; do
  put "index template logs-${cat}" \
    "${ES_URL}/_index_template/logs-${cat}" \
    "$(cat "${INFRA_DIR}/templates/index-template-logs-${cat}.json")"
done

echo "[setup-es] OK — 3 ILM + 3 component + 3 index template"
