#!/usr/bin/env bash
# db-backup.sh — nightly PostgreSQL backup for the coffee-shop app.
#
# Dumps the database with pg_dump (custom format, compressed) and keeps a
# rolling retention of daily + weekly snapshots. Optionally copies the newest
# dump to S3. Designed to run from a cron job on the VPS or a dev machine.
#
# Usage:
#   ./scripts/db-backup.sh [backup_dir]
#
# Environment overrides (all optional):
#   PG_CONTAINER     explicit pgsql container name for `docker exec`
#                    (dev: <branch>-pgsql-1, prod default: coffee-shop-pgsql-1)
#   DB_USER          database user            (default: sail, or DB_USERNAME from .env)
#   DB_NAME          database name            (default: coffee_shop, or DB_DATABASE from .env)
#   BACKUP_DIR       destination directory    (default: ./storage/app/backups)
#   RETENTION_DAILY  daily dumps to keep      (default: 14)
#   RETENTION_WEEKLY weekly dumps to keep      (default: 8)
#   S3_BACKUP=1      also copy the daily dump to S3 (requires `aws` CLI)
#   S3_BUCKET        S3 bucket name           (required when S3_BACKUP=1)
#   S3_PREFIX        S3 key prefix            (default: backups/coffee-shop)
#
# Suggested VPS crontab (runs every day at 02:00 server time):
#   0 2 * * *  /opt/rizkilab/coffee-shop/scripts/db-backup.sh >> /var/log/coffee-shop-backup.log 2>&1

set -euo pipefail

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

fail() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" >&2
    exit 1
}

# Load defaults from the Laravel .env if it exists (AWS_*, DB_* etc.).
if [[ -f .env ]]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

DB_USER="${DB_USER:-${DB_USERNAME:-sail}}"
DB_NAME="${DB_NAME:-${DB_DATABASE:-coffee_shop}}"
BACKUP_DIR="${BACKUP_DIR:-./storage/app/backups}"
RETENTION_DAILY="${RETENTION_DAILY:-14}"
RETENTION_WEEKLY="${RETENTION_WEEKLY:-8}"
S3_PREFIX="${S3_PREFIX:-backups/coffee-shop}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/../$BACKUP_DIR"
[[ "$BACKUP_DIR" == /* ]] || BACKUP_DIR="$PWD/$BACKUP_DIR"

mkdir -p "$BACKUP_DIR"

# --- Resolve how to run commands inside the pgsql container -------------------
# Priority: 1) PG_CONTAINER override, 2) `docker compose exec pgsql` (Sail/dev
# or any repo checkout with compose.yaml), 3) default prod container name.
PG_EXEC=()
if [[ -n "${PG_CONTAINER:-}" ]]; then
    if docker exec "$PG_CONTAINER" pg_isready >/dev/null 2>&1; then
        PG_EXEC=(docker exec -i "$PG_CONTAINER")
    else
        fail "PG_CONTAINER='$PG_CONTAINER' is not running or unreachable"
    fi
elif docker compose exec -T pgsql pg_isready >/dev/null 2>&1; then
    PG_EXEC=(docker compose exec -T pgsql)
elif docker exec coffee-shop-pgsql-1 pg_isready >/dev/null 2>&1; then
    PG_EXEC=(docker exec -i coffee-shop-pgsql-1)
else
    fail "no reachable pgsql container found (start the stack or set PG_CONTAINER)"
fi

log "backing up '$DB_NAME' as '$DB_USER' via ${PG_EXEC[*]}"
log "backup dir: $BACKUP_DIR (daily=$RETENTION_DAILY, weekly=$RETENTION_WEEKLY)"

# --- Daily dump ---------------------------------------------------------------
ts="$(date +%F)"
daily_file="$BACKUP_DIR/coffee_shop_daily_${ts}.dump.gz"
if [[ -f "$daily_file" ]]; then
    log "daily dump for $ts already exists, keeping it"
else
    "${PG_EXEC[@]}" pg_dump -Fc -U "$DB_USER" -d "$DB_NAME" | gzip -9 > "$daily_file"
    log "daily dump written: $(basename "$daily_file") ($(du -h "$daily_file" | cut -f1))"
fi

# --- Weekly snapshot (first run of a new ISO week) ----------------------------
week="$(date +%Y)-W$(date +%V)"
week_file="$BACKUP_DIR/coffee_shop_weekly_${week}.dump.gz"
if [[ ! -f "$week_file" ]]; then
    cp -p "$daily_file" "$week_file"
    log "weekly snapshot written: $(basename "$week_file")"
fi

# --- Retention ----------------------------------------------------------------
prune() {
    local pattern="$1" keep="$2"
    local files newest
    files="$(ls -1 "$BACKUP_DIR"/"$pattern" 2>/dev/null || true)"
    [[ -z "$files" ]] && return 0
    newest="$(wc -l <<< "$files")"
    if (( newest > keep )); then
        tail -n +$((keep + 1)) <<< "$files" | while read -r f; do
            rm -f -- "$BACKUP_DIR/$f"
            log "pruned $f (over $keep limit)"
        done
    fi
}
prune "coffee_shop_daily_*.dump.gz" "$RETENTION_DAILY"
prune "coffee_shop_weekly_*.dump.gz" "$RETENTION_WEEKLY"

# --- Optional S3 copy ---------------------------------------------------------
if [[ "${S3_BACKUP:-0}" == "1" ]]; then
    if ! command -v aws >/dev/null 2>&1; then
        fail "S3_BACKUP=1 but the 'aws' CLI is not installed"
    fi
    [[ -n "${S3_BUCKET:-}" ]] || fail "S3_BACKUP=1 requires S3_BUCKET"
    aws s3 cp "$daily_file" "s3://$S3_BUCKET/$S3_PREFIX/" --no-progress \
        || fail "S3 copy failed for $(basename "$daily_file")"
    log "copied to s3://$S3_BUCKET/$S3_PREFIX/$(basename "$daily_file")"
fi

log "backup finished OK"
