#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "Building frontend assets (host Node/npm — not inside Docker)..."
npm ci
npm run build

if [[ ! -f public/build/manifest.json ]]; then
    echo "Asset build failed: public/build/manifest.json is missing." >&2
    exit 1
fi

echo "Frontend assets ready in public/build/"
