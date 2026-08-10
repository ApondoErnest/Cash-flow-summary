#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f deploy/env/docker.env ]]; then
    echo "Missing deploy/env/docker.env — copy deploy/env/docker.env.example first."
    exit 1
fi

echo "Building app image (Composer + Vite assets inside Docker)..."
./deploy/compose.sh build app

echo "Building nginx image..."
./deploy/compose.sh build nginx

echo "Done. Start with: ./deploy/compose.sh up -d"
