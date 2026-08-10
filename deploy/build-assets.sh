#!/usr/bin/env bash
set -euo pipefail

# Optional: build frontend assets on the host (local dev iteration only).
# Production and Docker deploys compile assets inside the Docker image — no host Node required.

cd "$(dirname "$0")/.."

if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
    echo "Host Node/npm not found." >&2
    echo "For Docker deploys, run ./deploy/build.sh or ./deploy/build-production.sh instead." >&2
    exit 1
fi

echo "Building frontend assets on host (local dev only)..."
npm ci
npm run build

if [[ ! -f public/build/manifest.json ]]; then
    echo "Asset build failed: public/build/manifest.json is missing." >&2
    exit 1
fi

echo "Frontend assets ready in public/build/"
