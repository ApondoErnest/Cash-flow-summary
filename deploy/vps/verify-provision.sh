#!/usr/bin/env bash
# Verify Step 112 VPS provisioning on the server.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

pass() { echo "  ✓ $1"; }
fail() { echo "  ✗ $1" >&2; exit 1; }

echo "=== Step 112 VPS provision verification ==="

if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    [[ "${ID:-}" == "ubuntu" ]] || fail "Expected Ubuntu, got ${ID:-unknown}"
    VERSION_ID_NUM="${VERSION_ID%%.*}"
    [[ "${VERSION_ID_NUM:-0}" -ge 22 ]] || fail "Ubuntu 22.04+ required"
    pass "OS ${PRETTY_NAME:-Ubuntu}"
else
    fail "Cannot detect OS"
fi

command -v git >/dev/null 2>&1 && pass "git installed" || fail "git missing"
command -v docker >/dev/null 2>&1 && pass "docker installed" || fail "docker missing"
docker compose version >/dev/null 2>&1 && pass "docker compose plugin" || fail "docker compose missing"

id deploy >/dev/null 2>&1 && pass "deploy user exists" || fail "deploy user missing"
id -nG deploy 2>/dev/null | grep -qw docker && pass "deploy in docker group" || fail "deploy not in docker group"

APP_DIR="${APP_DIR:-/var/www/cashflow-summary}"
[[ -d "$APP_DIR" ]] && pass "app directory $APP_DIR" || fail "missing $APP_DIR"

if [[ -f "$ROOT/composer.json" && -f "$ROOT/docker-compose.yml" ]]; then
    pass "application repository present"
else
    fail "run from cloned repo root or clone into $APP_DIR first"
fi

AVAIL_GB="$(df -BG "$APP_DIR" | awk 'NR==2 {gsub(/G/,"",$4); print $4}')"
if [[ "${AVAIL_GB:-0}" -ge 20 ]]; then
    pass "disk free ${AVAIL_GB}G (≥20G recommended)"
else
    echo "  ! disk free ${AVAIL_GB}G — minimum 40G VPS recommended" >&2
fi

RAM_GB="$(free -g | awk '/^Mem:/ {print $2}')"
if [[ "${RAM_GB:-0}" -ge 3 ]]; then
    pass "RAM ${RAM_GB}G"
else
    echo "  ! RAM ${RAM_GB}G — 4G minimum recommended" >&2
fi

echo ""
echo "Step 112 provision checks passed."
echo "Next: Step 113 — deploy/vps/harden-server.md (SSH, UFW, TLS)"
