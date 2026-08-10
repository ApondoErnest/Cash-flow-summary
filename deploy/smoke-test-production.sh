#!/usr/bin/env bash
# Production smoke tests (same as smoke-test.sh but uses production.env + compose-production.sh)
set -euo pipefail

cd "$(dirname "$0")/.."

export COMPOSE_SCRIPT=./deploy/compose-production.sh
export ENV_FILE=deploy/env/production.env

exec ./deploy/smoke-test.sh "$@"
