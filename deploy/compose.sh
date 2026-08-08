#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/deploy/env/docker.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing $ENV_FILE"
    echo "Run: cp deploy/env/docker.env.example deploy/env/docker.env"
    echo "Edit passwords, then: ./deploy/compose.sh run --rm --no-deps app php artisan key:generate"
    exit 1
fi

cd "$ROOT"
exec docker compose --env-file "$ENV_FILE" "$@"
