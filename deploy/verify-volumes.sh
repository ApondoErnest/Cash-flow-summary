#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE=(./deploy/compose.sh)
MARKER=".volume-verify-step110"

echo "Checking Docker stack is running..."
RUNNING="$("${COMPOSE[@]}" ps --services --filter status=running 2>/dev/null || true)"
for service in app mysql redis; do
    if ! echo "$RUNNING" | grep -qx "$service"; then
        echo "Service '$service' is not running. Start the stack: ./deploy/compose.sh up -d" >&2
        exit 1
    fi
done

echo "Checking named volumes exist..."
for volume in cashflow-summary_mysql_data cashflow-summary_redis_data cashflow-summary_storage_data; do
    if ! docker volume inspect "$volume" >/dev/null 2>&1; then
        echo "Missing volume: $volume" >&2
        exit 1
    fi
    echo "  ✓ $volume"
done

echo "Writing storage marker via app container..."
"${COMPOSE[@]}" exec -T app sh -c "mkdir -p storage/app/private && echo step110 > storage/app/private/${MARKER}"

echo "Restarting app, horizon, and scheduler..."
"${COMPOSE[@]}" restart app horizon scheduler >/dev/null
sleep 3

echo "Verifying storage marker after restart..."
if ! "${COMPOSE[@]}" exec -T app sh -c "test -f storage/app/private/${MARKER} && grep -q step110 storage/app/private/${MARKER}"; then
    echo "Storage volume persistence check failed." >&2
    exit 1
fi
echo "  ✓ storage_data"

echo "Verifying MySQL data survives mysql container restart..."
USER_COUNT_BEFORE="$("${COMPOSE[@]}" exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SELECT COUNT(*) FROM users" "$MYSQL_DATABASE"')"
"${COMPOSE[@]}" restart mysql >/dev/null

"${COMPOSE[@]}" exec -T mysql sh -c 'for i in $(seq 1 30); do mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent && exit 0; sleep 2; done; exit 1'

USER_COUNT_AFTER="$("${COMPOSE[@]}" exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SELECT COUNT(*) FROM users" "$MYSQL_DATABASE"')"
if [[ "$USER_COUNT_BEFORE" != "$USER_COUNT_AFTER" ]] || [[ "$USER_COUNT_AFTER" -lt 1 ]]; then
    echo "MySQL volume persistence check failed (users: ${USER_COUNT_BEFORE} → ${USER_COUNT_AFTER})." >&2
    exit 1
fi
echo "  ✓ mysql_data (${USER_COUNT_AFTER} users)"

echo "Cleaning up marker..."
"${COMPOSE[@]}" exec -T app rm -f "storage/app/private/${MARKER}" >/dev/null 2>&1 || true

echo ""
echo "Step 110 volume verification passed."
