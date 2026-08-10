#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/deploy/env/production.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing $ENV_FILE"
    echo "On the VPS: cp deploy/env/production.env.example deploy/env/production.env"
    exit 1
fi

cd "$ROOT"
exec docker compose \
    --env-file "$ENV_FILE" \
    -f docker-compose.yml \
    -f docker-compose.production.yml \
    "$@"
