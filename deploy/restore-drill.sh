#!/usr/bin/env bash
set -euo pipefail

# Cash Flow Summary — restore drill on isolated staging stack (Step 117).
# Restores a production backup into separate Docker volumes (port 8082 by default).
# Never modifies cashflow-summary_* production volumes.
#
# Usage:
#   ./deploy/restore-drill.sh run [--backup PATH] [--teardown]
#   ./deploy/restore-drill.sh verify-backup [--backup PATH]
#   ./deploy/restore-drill.sh smoke
#   ./deploy/restore-drill.sh teardown

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMPOSE=(./deploy/compose-restore-drill.sh)
BACKUP_ENV="${BACKUP_ENV:-$ROOT/deploy/env/backup.env}"
RESTORE_DRILL_ENV="${RESTORE_DRILL_ENV:-$ROOT/deploy/env/restore-drill.env}"
REPORT_DIR="${RESTORE_DRILL_REPORT_DIR:-$ROOT/deploy/reports}"

BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/cashflow-summary}"
RUNS_DIR="${BACKUP_ROOT}/runs"
DRILL_STORAGE_VOLUME="${RESTORE_DRILL_STORAGE_VOLUME:-cashflow-restore-drill_storage_data}"
RESTORE_DRILL_HTTP_PORT="${RESTORE_DRILL_HTTP_PORT:-8082}"
LOG_TAG="cashflow-restore-drill"

BACKUP_DIR=""
TEARDOWN_AFTER=0
START_EPOCH=0

load_config() {
    if [[ -f "$BACKUP_ENV" ]]; then
        set -a
        # shellcheck disable=SC1090
        source "$BACKUP_ENV"
        set +a
        RUNS_DIR="${BACKUP_ROOT}/runs"
    fi

    # backup.env may define STORAGE_VOLUME for production — never use it in the drill.
    DRILL_STORAGE_VOLUME="${RESTORE_DRILL_STORAGE_VOLUME:-cashflow-restore-drill_storage_data}"
}

log() {
    echo "[$(date -Iseconds)] [$LOG_TAG] $*"
}

require_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        echo "docker is required." >&2
        exit 1
    fi
}

assert_isolated_volumes() {
    local vol

    for vol in \
        "${DRILL_STORAGE_VOLUME}" \
        "cashflow-restore-drill_mysql_data" \
        "cashflow-restore-drill_redis_data"; do
        if [[ "$vol" == cashflow-summary_* ]]; then
            echo "Refusing to run: volume name ${vol} matches production prefix." >&2
            exit 1
        fi
    done
}

resolve_backup_dir() {
    local explicit="${1:-}"

    if [[ -n "$explicit" ]]; then
        BACKUP_DIR="$explicit"
    else
        BACKUP_DIR="$(find "$RUNS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sort | tail -n 1 || true)"
    fi

    if [[ -z "$BACKUP_DIR" || ! -d "$BACKUP_DIR" ]]; then
        echo "Backup directory not found under ${RUNS_DIR}" >&2
        echo "Run ./deploy/backup-production.sh run or pass --backup /path/to/run" >&2
        exit 1
    fi

    BACKUP_DIR="$(cd "$BACKUP_DIR" && pwd)"
}

verify_backup_artifacts() {
    local db storage manifest

    db="${BACKUP_DIR}/database.sql.gz"
    storage="${BACKUP_DIR}/storage-files.tgz"

    [[ -s "$db" ]] || { echo "Missing or empty: $db" >&2; exit 1; }
    [[ -s "$storage" ]] || { echo "Missing or empty: $storage" >&2; exit 1; }

    log "Checking gzip integrity: $(basename "$db")"
    gunzip -t "$db"

    log "Checking tar integrity: $(basename "$storage")"
    tar -tzf "$storage" >/dev/null

    manifest="${BACKUP_DIR}/manifest.json"
    if [[ -f "$manifest" ]] && command -v python3 >/dev/null 2>&1; then
        log "Validating manifest checksums ..."
        MANIFEST="$manifest" BACKUP_DIR="$BACKUP_DIR" python3 - <<'PY'
import hashlib, json, os, sys

manifest_path = os.environ["MANIFEST"]
backup_dir = os.environ["BACKUP_DIR"]

with open(manifest_path, encoding="utf-8") as fh:
    data = json.load(fh)

files = data.get("files") or {}
errors = []

def sha256(path):
    h = hashlib.sha256()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()

for name, meta in files.items():
    if name.endswith(".snapshot"):
        continue
    path = os.path.join(backup_dir, name)
    if not os.path.isfile(path):
        errors.append(f"missing {name}")
        continue
    expected = (meta or {}).get("sha256")
    if expected and expected != "unknown":
        actual = sha256(path)
        if actual != expected:
            errors.append(f"{name} sha256 mismatch")

if errors:
    print("Manifest validation failed:", file=sys.stderr)
    for err in errors:
        print(f" - {err}", file=sys.stderr)
    sys.exit(1)
PY
    fi

    log "Backup artifacts OK: ${BACKUP_DIR}"
}

prepare_restore_env() {
    local snapshot="${BACKUP_DIR}/production.env.snapshot"

    if [[ ! -f "$snapshot" ]]; then
        echo "Missing ${snapshot}" >&2
        echo "Restore drill requires production.env.snapshot from the backup run." >&2
        exit 1
    fi

    log "Preparing ${RESTORE_DRILL_ENV} from production.env.snapshot ..."
    cp "$snapshot" "$RESTORE_DRILL_ENV"
    chmod 600 "$RESTORE_DRILL_ENV"

    if grep -q '^HTTP_PORT=' "$RESTORE_DRILL_ENV"; then
        sed -i.bak "s/^HTTP_PORT=.*/HTTP_PORT=${RESTORE_DRILL_HTTP_PORT}/" "$RESTORE_DRILL_ENV"
        rm -f "${RESTORE_DRILL_ENV}.bak"
    else
        printf '\nHTTP_PORT=%s\n' "$RESTORE_DRILL_HTTP_PORT" >> "$RESTORE_DRILL_ENV"
    fi

    if grep -q '^APP_URL=' "$RESTORE_DRILL_ENV"; then
        sed -i.bak "s|^APP_URL=.*|APP_URL=http://127.0.0.1:${RESTORE_DRILL_HTTP_PORT}|" "$RESTORE_DRILL_ENV"
        rm -f "${RESTORE_DRILL_ENV}.bak"
    else
        printf 'APP_URL=http://127.0.0.1:%s\n' "$RESTORE_DRILL_HTTP_PORT" >> "$RESTORE_DRILL_ENV"
    fi

    if ! grep -q '^RESTORE_DRILL_HTTP_PORT=' "$RESTORE_DRILL_ENV"; then
        printf 'RESTORE_DRILL_HTTP_PORT=%s\n' "$RESTORE_DRILL_HTTP_PORT" >> "$RESTORE_DRILL_ENV"
    fi
}

wait_for_mysql() {
    local attempt

    log "Waiting for MySQL to become healthy ..."
    for attempt in $(seq 1 60); do
        if "${COMPOSE[@]}" exec -T mysql sh -c \
            'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null 2>&1; then
            log "MySQL is ready"
            return 0
        fi
        sleep 2
    done

    echo "MySQL did not become ready in time." >&2
    exit 1
}

load_db_credentials() {
    set -a
    # shellcheck disable=SC1090
    source "$RESTORE_DRILL_ENV"
    set +a
}

restore_database() {
    local db="${BACKUP_DIR}/database.sql.gz"

    log "Restoring MySQL from $(basename "$db") ..."
    gunzip -c "$db" | "${COMPOSE[@]}" exec -T mysql sh -c \
        'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
    log "MySQL restore complete"
}

restore_storage() {
    local storage="${BACKUP_DIR}/storage-files.tgz"

    log "Stopping app workers before storage restore ..."
    "${COMPOSE[@]}" stop nginx app horizon scheduler >/dev/null 2>&1 || true

    log "Restoring storage volume ${DRILL_STORAGE_VOLUME} ..."
    docker run --rm \
        -v "${DRILL_STORAGE_VOLUME}:/data" \
        -v "${BACKUP_DIR}:/backup:ro" \
        alpine:3.20 \
        sh -c 'find /data -mindepth 1 -maxdepth 1 -exec rm -rf {} +; tar xzf /backup/storage-files.tgz -C /data'

    log "Storage restore complete"
}

start_stack() {
    log "Starting restore drill stack on 127.0.0.1:${RESTORE_DRILL_HTTP_PORT} ..."
    "${COMPOSE[@]}" up -d
}

wait_for_up() {
    local url="http://127.0.0.1:${RESTORE_DRILL_HTTP_PORT}/up"
    local attempt code

    log "Waiting for ${url} ..."
    for attempt in $(seq 1 60); do
        code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 5 --max-time 10 "$url" 2>/dev/null || echo "000")"
        if [[ "$code" == "200" ]]; then
            log "/up HTTP 200"
            return 0
        fi
        sleep 2
    done

    echo "/up did not return HTTP 200 in time (${url})." >&2
    exit 1
}

run_smoke_tests() {
    log "Running smoke tests against restore drill stack ..."
    COMPOSE_SCRIPT=./deploy/compose-restore-drill.sh \
        ENV_FILE=deploy/env/restore-drill.env \
        ./deploy/smoke-test.sh
}

write_report() {
    local end_epoch duration report_path

    mkdir -p "$REPORT_DIR"
    end_epoch="$(date +%s)"
    duration=$(( end_epoch - START_EPOCH ))
    report_path="${REPORT_DIR}/restore-drill-$(date +%Y%m%d-%H%M%S).txt"

    {
        echo "Cash Flow Summary — restore drill report"
        echo "Generated: $(date -Iseconds)"
        echo "Backup: ${BACKUP_DIR}"
        echo "Stack URL: http://127.0.0.1:${RESTORE_DRILL_HTTP_PORT}"
        echo "Duration_seconds: ${duration}"
        echo "Volumes: cashflow-restore-drill_* (isolated from production)"
        echo "Smoke tests: passed"
        echo "Issues: none"
    } > "$report_path"

    log "Report written: ${report_path}"
}

cmd_verify_backup() {
    resolve_backup_dir "$1"
    verify_backup_artifacts
}

cmd_run() {
    local backup_arg=""

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --backup)
                backup_arg="${2:?--backup requires a path}"
                shift 2
                ;;
            --teardown)
                TEARDOWN_AFTER=1
                shift
                ;;
            *)
                echo "Unknown option: $1" >&2
                usage >&2
                exit 1
                ;;
        esac
    done

    require_docker
    assert_isolated_volumes
    load_config
    resolve_backup_dir "$backup_arg"
    verify_backup_artifacts

    START_EPOCH="$(date +%s)"
    log "Restore drill starting (backup=${BACKUP_DIR})"

    prepare_restore_env
    load_db_credentials

    log "Resetting isolated drill volumes for a clean restore ..."
    "${COMPOSE[@]}" down -v >/dev/null 2>&1 || true

    "${COMPOSE[@]}" up -d mysql redis
    wait_for_mysql
    restore_database
    restore_storage
    start_stack
    wait_for_up
    run_smoke_tests
    write_report

    if [[ "$TEARDOWN_AFTER" -eq 1 ]]; then
        cmd_teardown
        log "Drill complete — stack torn down (--teardown)"
    else
        log "Drill complete — stack running on 127.0.0.1:${RESTORE_DRILL_HTTP_PORT}"
        log "Inspect in browser, then: ./deploy/restore-drill.sh teardown"
    fi
}

cmd_smoke() {
    if [[ ! -f "$RESTORE_DRILL_ENV" ]]; then
        echo "Missing ${RESTORE_DRILL_ENV}. Run ./deploy/restore-drill.sh run first." >&2
        exit 1
    fi

    require_docker
    run_smoke_tests
}

cmd_teardown() {
    require_docker
    assert_isolated_volumes

    if [[ -f "$RESTORE_DRILL_ENV" ]]; then
        log "Removing restore drill stack and isolated volumes ..."
        "${COMPOSE[@]}" down -v
        rm -f "$RESTORE_DRILL_ENV"
    else
        log "No ${RESTORE_DRILL_ENV} — removing drill containers and volumes by name ..."
        docker ps -aq --filter "name=cashflow-restore-drill" 2>/dev/null | while read -r id; do
            [[ -n "$id" ]] && docker rm -f "$id" >/dev/null 2>&1 || true
        done
        docker volume rm \
            cashflow-restore-drill_mysql_data \
            cashflow-restore-drill_redis_data \
            cashflow-restore-drill_storage_data \
            2>/dev/null || true
    fi

    log "Teardown complete"
}

usage() {
    cat <<EOF
Usage: $(basename "$0") <command> [options]

Commands:
  run [--backup PATH] [--teardown]   Restore backup to isolated stack + smoke tests
  verify-backup [--backup PATH]  Validate backup artifacts only (no Docker restore)
  smoke                          Re-run smoke tests against running drill stack
  teardown                       Stop drill stack and delete cashflow-restore-drill_* volumes

Safety:
  Uses project cashflow-restore-drill on 127.0.0.1:${RESTORE_DRILL_HTTP_PORT} — never touches
  cashflow-summary_* production volumes.

Optional: deploy/env/backup.env (BACKUP_ROOT)
Reports: ${REPORT_DIR}/restore-drill-*.txt
EOF
}

main() {
    local cmd="${1:-}"
    shift || true

    case "$cmd" in
        run)
            cmd_run "$@"
            ;;
        verify-backup)
            local backup_arg=""
            if [[ "${1:-}" == "--backup" ]]; then
                backup_arg="${2:?--backup requires a path}"
            fi
            load_config
            cmd_verify_backup "$backup_arg"
            ;;
        smoke)
            cmd_smoke
            ;;
        teardown)
            cmd_teardown
            ;;
        -h|--help|help|"")
            usage
            ;;
        *)
            echo "Unknown command: $cmd" >&2
            usage >&2
            exit 1
            ;;
    esac
}

main "$@"
