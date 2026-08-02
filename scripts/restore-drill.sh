#!/usr/bin/env bash
# restore-drill.sh — verify that the latest backup can actually be restored.
#
# Restores the newest dump into a THROWAWAY database, runs sanity queries,
# prints PASS/FAIL, then drops the throwaway database. The live database is
# never touched. Safe to run any time (e.g. weekly via cron).
#
# Usage:
#   ./scripts/restore-drill.sh [backup.dump.gz]   (default: newest daily dump)
#
# Environment overrides (same as db-backup.sh):
#   PG_CONTAINER, DB_USER, DB_NAME, BACKUP_DIR

set -euo pipefail

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

fail() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" >&2
    exit 1
}

# Load defaults from the Laravel .env if it exists.
if [[ -f .env ]]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

DB_USER="${DB_USER:-${DB_USERNAME:-sail}}"
DB_NAME="${DB_NAME:-${DB_DATABASE:-coffee_shop}}"
BACKUP_DIR="${BACKUP_DIR:-./storage/app/backups}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/../$BACKUP_DIR"
[[ "$BACKUP_DIR" == /* ]] || BACKUP_DIR="$PWD/$BACKUP_DIR"

# --- Resolve pgsql execution (same logic as db-backup.sh) --------------------
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

# --- Pick the backup to test --------------------------------------------------
DUMP_FILE="${1:-}"
if [[ -z "$DUMP_FILE" ]]; then
    DUMP_FILE="$(ls -1 "$BACKUP_DIR"/coffee_shop_daily_*.dump.gz 2>/dev/null | sort -r | head -1 || true)"
fi
[[ -n "$DUMP_FILE" && -f "$DUMP_FILE" ]] || fail "no backup found to restore (looked in $BACKUP_DIR)"
log "restoring backup: $DUMP_FILE"

# --- Throwaway database -------------------------------------------------------
DRILL_DB="${DB_NAME}_restore_drill_$(date '+%Y%m%d_%H%M%S')"
cleanup() {
    "${PG_EXEC[@]}" psql -U "$DB_USER" -d postgres \
        -c "DROP DATABASE IF EXISTS \"$DRILL_DB\" WITH (FORCE);" >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${PG_EXEC[@]}" psql -U "$DB_USER" -d postgres \
    -c "CREATE DATABASE \"$DRILL_DB\" OWNER \"$DB_USER\";" >/dev/null
log "created throwaway database: $DRILL_DB"

# --- Restore + verify ---------------------------------------------------------
if gunzip -c "$DUMP_FILE" | "${PG_EXEC[@]}" pg_restore -U "$DB_USER" -d "$DRILL_DB" --no-owner; then
    log "pg_restore finished, running sanity checks..."
    "${PG_EXEC[@]}" psql -U "$DB_USER" -d "$DRILL_DB" -tAc "SELECT count(*) FROM menu_items;" >/dev/null
    "${PG_EXEC[@]}" psql -U "$DB_USER" -d "$DRILL_DB" -tAc "SELECT count(*) FROM orders;" >/dev/null
    log "sanity checks PASSED (menu_items, orders readable)"
else
    fail "pg_restore failed — backup is not restorable"
fi

log "restore drill PASSED for $(basename "$DUMP_FILE")"
