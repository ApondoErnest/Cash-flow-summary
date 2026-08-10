#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f deploy/env/production.env ]]; then
    echo "Missing deploy/env/production.env — copy deploy/env/production.env.example first."
    exit 1
fi

echo "Building app image (Composer + Vite assets inside Docker)..."
./deploy/compose-production.sh build app

echo "Building nginx image..."
./deploy/compose-production.sh build nginx

echo "Done. Start with: ./deploy/compose-production.sh up -d"
