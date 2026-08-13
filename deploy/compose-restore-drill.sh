#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/deploy/env/restore-drill.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing $ENV_FILE" >&2
    echo "Run: ./deploy/restore-drill.sh run (creates it from production.env.snapshot)" >&2
    exit 1
fi

cd "$ROOT"
exec docker compose \
    --env-file "$ENV_FILE" \
    -f docker-compose.yml \
    -f docker-compose.restore-drill.yml \
    "$@"
