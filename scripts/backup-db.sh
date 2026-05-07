#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
#  PostgreSQL backup — daily pg_dump | gzip + retention rotation
# ─────────────────────────────────────────────────────────────
#
# KVKK + mali kayıt + audit trail için Postgres volume kaybı
# yıkıcı. Bu script daily dump üretir, 30 günlük rolling retention
# uygular ve aylık archive bırakır.
#
# Usage:
#   bash scripts/backup-db.sh                      # default → ./backups
#   BACKUP_DIR=/var/backups bash scripts/backup-db.sh
#
# Cron örneği (production crontab):
#   0 2 * * * cd /app && bash scripts/backup-db.sh >> /var/log/db-backup.log 2>&1
#
# Veya Laravel scheduler ile (routes/console.php):
#   Schedule::exec('bash /app/scripts/backup-db.sh')->dailyAt('02:00');
#
# Production'da S3/Minio sync için son adım:
#   aws s3 sync $BACKUP_DIR s3://dehasoft-db-backups/$(hostname)/

set -euo pipefail

# ─── Config ──────────────────────────────────────────────────
BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DOCKER_SERVICE="${DOCKER_SERVICE:-postgres}"
DB_NAME="${DB_NAME:-testproject}"
DB_USER="${DB_USER:-dehasoft}"

# ─── Setup ───────────────────────────────────────────────────
mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/monthly"
TIMESTAMP=$(date -u +%Y-%m-%d-%H%M%S)
DAILY_FILE="$BACKUP_DIR/daily/${DB_NAME}_${TIMESTAMP}.sql.gz"

# ─── Dump ────────────────────────────────────────────────────
echo "🗄️  Dumping ${DB_NAME} → ${DAILY_FILE}"
docker compose exec -T "$DOCKER_SERVICE" pg_dump \
    -U "$DB_USER" \
    -d "$DB_NAME" \
    --format=plain \
    --no-owner \
    --no-acl \
    --clean \
    --if-exists \
    | gzip -9 > "$DAILY_FILE"

# Verify dump integrity (gzip ok + non-empty)
if [ ! -s "$DAILY_FILE" ]; then
    echo "❌ Dump file empty or missing: $DAILY_FILE"
    exit 1
fi
gzip -t "$DAILY_FILE" || { echo "❌ Gzip integrity check failed"; exit 1; }

SIZE=$(du -h "$DAILY_FILE" | cut -f1)
echo "✅ Daily backup OK ($SIZE)"

# ─── Monthly archive (her ayın 1'inde) ───────────────────────
DAY_OF_MONTH=$(date -u +%d)
if [ "$DAY_OF_MONTH" = "01" ]; then
    MONTH_FILE="$BACKUP_DIR/monthly/${DB_NAME}_$(date -u +%Y-%m).sql.gz"
    cp "$DAILY_FILE" "$MONTH_FILE"
    echo "📦 Monthly archive: $MONTH_FILE"
fi

# ─── Retention rotation ──────────────────────────────────────
# Daily: $RETENTION_DAYS gün öncesini sil
echo "🧹 Pruning daily backups older than ${RETENTION_DAYS} days…"
find "$BACKUP_DIR/daily" -type f -name "*.sql.gz" -mtime +"$RETENTION_DAYS" -print -delete | wc -l | xargs echo "   deleted:"

# Monthly: 12 aydan eski archive'leri sil
echo "🧹 Pruning monthly archives older than 365 days…"
find "$BACKUP_DIR/monthly" -type f -name "*.sql.gz" -mtime +365 -print -delete | wc -l | xargs echo "   deleted:"

# ─── Summary ─────────────────────────────────────────────────
DAILY_COUNT=$(find "$BACKUP_DIR/daily" -name "*.sql.gz" | wc -l)
MONTHLY_COUNT=$(find "$BACKUP_DIR/monthly" -name "*.sql.gz" | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo
echo "📊 Backup state: ${DAILY_COUNT} daily, ${MONTHLY_COUNT} monthly, ${TOTAL_SIZE} total"

# ─── (Optional) S3 sync — production deploy ──────────────────
if [ -n "${BACKUP_S3_BUCKET:-}" ]; then
    echo "☁️  Syncing to s3://${BACKUP_S3_BUCKET}/$(hostname)/"
    aws s3 sync "$BACKUP_DIR" "s3://${BACKUP_S3_BUCKET}/$(hostname)/" \
        --exclude "*" \
        --include "*.sql.gz" \
        --storage-class STANDARD_IA
fi
