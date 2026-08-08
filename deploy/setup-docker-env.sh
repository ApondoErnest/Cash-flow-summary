#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EXAMPLE="$ROOT/deploy/env/docker.env.example"
TARGET="$ROOT/deploy/env/docker.env"

if [[ -f "$TARGET" ]]; then
    echo "Already exists: $TARGET"
    echo "Edit that file directly. Root .env is not touched."
    exit 0
fi

cp "$EXAMPLE" "$TARGET"
echo "Created $TARGET from example."
echo ""
echo "Next:"
echo "  1. Edit deploy/env/docker.env — set DB_PASSWORD / DB_ROOT_PASSWORD"
echo "  2. ./deploy/compose.sh build app"
echo "  3. ./deploy/compose.sh run --rm --no-deps app php artisan key:generate"
echo "  4. ./deploy/build.sh && ./deploy/compose.sh up -d mysql redis"
echo "  5. ./deploy/compose.sh run --rm app php artisan migrate --force --seed"
echo "  6. ./deploy/compose.sh up -d"
