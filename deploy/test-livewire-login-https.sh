#!/usr/bin/env bash
# Test Livewire login through the public HTTPS URL (host nginx → Docker → PHP).
# Run on the VPS after deploy when the browser Sign-in button spins.
#
#   ./deploy/test-livewire-login-https.sh
#
# This does NOT change the app — it simulates the browser POST /livewire-…/update.

set -euo pipefail

APP_URL="${APP_URL:-https://cashflow.gsautobilan.com}"
USERNAME="${SEED_OWNER_USERNAME:-owner}"
PASSWORD="${SEED_OWNER_PASSWORD:-password}"

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

echo "=== Livewire login via public HTTPS ($APP_URL) ==="
echo

curl -sS -c "$tmpdir/cookies.txt" "$APP_URL/login" -o "$tmpdir/login.html"

if ! grep -q 'Sign in\|Connexion' "$tmpdir/login.html"; then
    echo "FAIL: /login HTML missing expected sign-in copy"
    exit 1
fi

csrf="$(grep -o 'data-csrf="[^"]*"' "$tmpdir/login.html" | head -1 | sed 's/data-csrf="//;s/"//')"
update_path="$(grep -oE 'livewire-[a-f0-9]+/update' "$tmpdir/login.html" | head -1)"

if [[ -z "$csrf" || -z "$update_path" ]]; then
    echo "FAIL: login page missing Livewire CSRF or update path"
    exit 1
fi

snapshot="$(python3 - <<'PY' "$tmpdir/login.html"
import html, re, sys
page = open(sys.argv[1]).read()
m = re.search(r'wire:snapshot="([^"]+)"', page)
if not m:
    raise SystemExit(1)
print(html.unescape(m.group(1)))
PY
)"

payload="$(CSRF="$csrf" SNAPSHOT="$snapshot" USER="$USERNAME" PASS="$PASSWORD" python3 - <<'PY'
import json, os
print(json.dumps({
    "_token": os.environ["CSRF"],
    "components": [{
        "snapshot": os.environ["SNAPSHOT"],
        "updates": {"username": os.environ["USER"], "password": os.environ["PASS"]},
        "calls": [{"path": "", "method": "authenticate", "params": []}],
    }],
}))
PY
)"

echo "Update path: /$update_path"
echo "POSTing Livewire authenticate…"

http_code="$(
    curl -sS -o "$tmpdir/post.json" -w '%{http_code}' \
        -c "$tmpdir/cookies.txt" -b "$tmpdir/cookies.txt" \
        -X POST "$APP_URL/$update_path" \
        -H 'Content-Type: application/json' \
        -H 'X-Livewire: true' \
        -H "X-CSRF-TOKEN: $csrf" \
        -H "Referer: $APP_URL/login" \
        -H 'Origin: '"$APP_URL" \
        -d "$payload"
)"

echo "HTTP status: $http_code"

if [[ "$http_code" == "419" ]]; then
    echo "FAIL: 419 Page Expired — CSRF/session cookie issue (clear browser cookies for this site)"
    exit 1
fi

if [[ "$http_code" != "200" ]]; then
    echo "FAIL: unexpected status (expected 200 JSON with redirect effect)"
    head -c 400 "$tmpdir/post.json" || true
    echo
    exit 1
fi

if python3 - <<'PY' "$tmpdir/post.json"
import json, sys
data = json.load(open(sys.argv[1]))
for component in data.get("components", []):
    effects = component.get("effects") or {}
    redirect = effects.get("redirect", "")
    if "password/change" in redirect:
        raise SystemExit(0)
raise SystemExit(1)
PY
then
    echo "OK: Livewire authenticate returned redirect to /password/change"
    exit 0
fi

echo "FAIL: 200 but no redirect to password/change in response body:"
head -c 400 "$tmpdir/post.json"
echo
exit 1
