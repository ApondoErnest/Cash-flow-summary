#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE=(./deploy/compose.sh)
ENV_FILE="${ENV_FILE:-deploy/env/docker.env}"

if [[ -n "${COMPOSE_SCRIPT:-}" ]]; then
    COMPOSE=("$COMPOSE_SCRIPT")
fi

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
for service in nginx app mysql redis horizon scheduler; do
    if echo "$RUNNING" | grep -qx "$service"; then
        pass "$service"
    else
        fail "service not running: $service"
    fi
done

echo ""
echo "2. Queue worker configuration ($ENV_FILE)"
grep -qE '^CSV_IMPORTS_SYNC=false' "$ENV_FILE" || fail "CSV_IMPORTS_SYNC must be false in $ENV_FILE"
grep -qE '^CSV_VERIFICATION_SYNC=false' "$ENV_FILE" || fail "CSV_VERIFICATION_SYNC must be false in $ENV_FILE"
grep -qE '^QUEUE_CONNECTION=redis' "$ENV_FILE" || fail "QUEUE_CONNECTION must be redis in $ENV_FILE"
pass "queue env flags"

echo ""
echo "3. PHP-FPM environment ($ENV_FILE)"
APP_KEY_WWWDATA="$("${COMPOSE[@]}" exec -T -u www-data app php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo config('app.key') ?: '';
" 2>/dev/null || true)"
if [[ -z "$APP_KEY_WWWDATA" ]]; then
    fail "APP_KEY empty for www-data (PHP-FPM) — ensure compose env_file injects $ENV_FILE"
fi
pass "APP_KEY visible to www-data"

echo ""
echo "4. HTTP smoke (via nginx)"
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
echo "5. In-container application checks"
"${COMPOSE[@]}" exec -T app php deploy/smoke-check-app.php
pass "in-container smoke-check-app.php"

echo ""
echo "6. Horizon worker"
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
echo "7. Scheduler worker"
if "${COMPOSE[@]}" exec -T scheduler sh -c 'tr "\0" " " < /proc/1/cmdline' 2>/dev/null | grep -q 'schedule:work'; then
    pass "scheduler container command"
else
    fail "scheduler is not running (schedule:work required for WhatsApp summaries)"
fi

echo ""
echo "=== All Docker deploy smoke tests passed (AC #34) ==="
