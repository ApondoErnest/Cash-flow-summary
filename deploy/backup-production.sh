#!/usr/bin/env bash
set -euo pipefail

# Backup Cash Flow Summary database before production updates (Step 115 outline).
# Uses ./deploy/compose-production.sh — run on the VPS with the stack up.

cd "$(dirname "$0")/.."

BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/cashflow-summary}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${BACKUP_ROOT}/${TIMESTAMP}"

mkdir -p "$DEST"
chmod 700 "$BACKUP_ROOT" 2>/dev/null || true

echo "Backing up MySQL to ${DEST}/database.sql ..."
./deploy/compose-production.sh exec -T mysql sh -c \
  'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  > "${DEST}/database.sql"

if [[ ! -s "${DEST}/database.sql" ]]; then
    echo "Backup failed: ${DEST}/database.sql is empty." >&2
    exit 1
fi

if [[ -f deploy/env/production.env ]]; then
    cp deploy/env/production.env "${DEST}/production.env.snapshot"
    chmod 600 "${DEST}/production.env.snapshot"
fi

echo "Backup complete:"
ls -lh "${DEST}/database.sql"
echo "Directory: ${DEST}"
