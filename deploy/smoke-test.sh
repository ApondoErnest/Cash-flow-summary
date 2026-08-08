#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE=(./deploy/compose.sh)
ENV_FILE="deploy/env/docker.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing $ENV_FILE" >&2
    exit 1
fi

HTTP_PORT="$(grep -E '^HTTP_PORT=' "$ENV_FILE" | cut -d= -f2- | tr -d '\r' || true)"
HTTP_PORT="${HTTP_PORT:-8080}"
BASE_URL="http://127.0.0.1:${HTTP_PORT}"

pass() { echo "  ✓ $1"; }
fail() { echo "  ✗ $1" >&2; exit 1; }

echo "=== Docker deploy smoke tests (Step 111 / AC #34) ==="
echo "Base URL: ${BASE_URL}"
echo ""

echo "1. Stack services running"
RUNNING="$("${COMPOSE[@]}" ps --services --filter status=running 2>/dev/null || true)"
for service in nginx app mysql redis horizon; do
    if echo "$RUNNING" | grep -qx "$service"; then
        pass "$service"
    else
        fail "service not running: $service"
    fi
done

echo ""
echo "2. Queue worker configuration (docker.env)"
grep -qE '^CSV_IMPORTS_SYNC=false' "$ENV_FILE" || fail "CSV_IMPORTS_SYNC must be false in $ENV_FILE"
grep -qE '^CSV_VERIFICATION_SYNC=false' "$ENV_FILE" || fail "CSV_VERIFICATION_SYNC must be false in $ENV_FILE"
grep -qE '^QUEUE_CONNECTION=redis' "$ENV_FILE" || fail "QUEUE_CONNECTION must be redis in $ENV_FILE"
pass "queue env flags"

echo ""
echo "3. HTTP smoke (via nginx)"
HEALTH_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/up" || echo "000")"
[[ "$HEALTH_CODE" == "200" ]] || fail "/up returned HTTP ${HEALTH_CODE}"
pass "/up HTTP 200"

LOGIN_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/login" || echo "000")"
[[ "$LOGIN_CODE" == "200" ]] || fail "/login returned HTTP ${LOGIN_CODE}"
pass "/login HTTP 200"

LOGIN_BODY="$(curl -s "${BASE_URL}/login")"
echo "$LOGIN_BODY" | grep -q 'Sign in' || fail "/login body missing sign-in copy"
pass "/login content"

echo ""
echo "4. In-container application checks"
"${COMPOSE[@]}" exec -T app php deploy/smoke-check-app.php
pass "in-container smoke-check-app.php"

echo ""
echo "5. Horizon worker"
HORIZON_STATUS="$("${COMPOSE[@]}" exec -T app php artisan horizon:status 2>&1 || true)"
if echo "$HORIZON_STATUS" | grep -qi 'running'; then
    pass "horizon status running"
elif "${COMPOSE[@]}" exec -T horizon sh -c 'tr "\0" " " < /proc/1/cmdline' 2>/dev/null | grep -q 'artisan horizon'; then
    pass "horizon container command"
else
    echo "$HORIZON_STATUS" >&2
    fail "horizon is not running"
fi

echo ""
echo "=== All Docker deploy smoke tests passed (AC #34) ==="
