#!/usr/bin/env bash
set -euo pipefail

# Install Step 115 cron jobs for the current user on the VPS.
# Safe to re-run — replaces only the Cash Flow backup block.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_DIR="${APP_DIR:-$ROOT}"
CRON_TEMPLATE="$ROOT/deploy/cron/cashflow-summary-backup.cron"
MARKER_BEGIN="# cashflow-summary-step115-begin"
MARKER_END="# cashflow-summary-step115-end"

if [[ ! -f "$CRON_TEMPLATE" ]]; then
    echo "Missing $CRON_TEMPLATE" >&2
    exit 1
fi

if [[ ! -x "$ROOT/deploy/backup-production.sh" ]]; then
    chmod +x "$ROOT/deploy/backup-production.sh"
fi

BLOCK="$(sed "s|__APP_DIR__|${APP_DIR}|g" "$CRON_TEMPLATE")"
BLOCK="${MARKER_BEGIN}
${BLOCK}
${MARKER_END}"

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

{
    crontab -l 2>/dev/null | awk -v b="$MARKER_BEGIN" -v e="$MARKER_END" '
        $0 == b { skip=1; next }
        $0 == e { skip=0; next }
        skip == 0 { print }
    ' || true
    echo "$BLOCK"
} > "$TMP"

crontab "$TMP"

echo "Installed Cash Flow backup cron for user $(whoami):"
echo "$BLOCK"
echo ""
echo "Log file: /var/log/cashflow-summary-backup.log"
echo "Test now: cd ${APP_DIR} && ./deploy/backup-production.sh run && ./deploy/backup-production.sh verify"
