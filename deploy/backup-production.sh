#!/usr/bin/env bash
set -euo pipefail

# Cash Flow Summary — production backups (Step 115).
# Run on the VPS with the Docker stack up.
#
# Usage:
#   ./deploy/backup-production.sh [run|retention|verify|offsite|config]
#
# Default command `run`: MySQL dump + storage files + production.env snapshot,
# tier promotion (weekly/monthly), retention, optional off-site sync.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMPOSE=(./deploy/compose-production.sh)
BACKUP_ENV="${BACKUP_ENV:-$ROOT/deploy/env/backup.env}"

BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/cashflow-summary}"
STORAGE_VOLUME="${STORAGE_VOLUME:-cashflow-summary_storage_data}"
RUNS_DIR="${BACKUP_ROOT}/runs"
WEEKLY_DIR="${BACKUP_ROOT}/weekly"
MONTHLY_DIR="${BACKUP_ROOT}/monthly"
CONFIG_DIR="${BACKUP_ROOT}/config"
LOG_TAG="cashflow-summary-backup"

RETENTION_DAILY="${RETENTION_DAILY:-28}"
RETENTION_WEEKLY="${RETENTION_WEEKLY:-0}"
RETENTION_MONTHLY="${RETENTION_MONTHLY:-0}"

load_config() {
    if [[ -f "$BACKUP_ENV" ]]; then
        set -a
        # shellcheck disable=SC1090
        source "$BACKUP_ENV"
        set +a
    fi
}

log() {
    echo "[$(date -Iseconds)] [$LOG_TAG] $*"
}

require_production_env() {
    if [[ ! -f deploy/env/production.env ]]; then
        echo "Missing deploy/env/production.env" >&2
        exit 1
    fi
}

require_stack() {
    local running
    running="$("${COMPOSE[@]}" ps --services --filter status=running 2>/dev/null || true)"
    for service in mysql app; do
        if ! echo "$running" | grep -qx "$service"; then
            echo "Service not running: $service (start the stack first)" >&2
            exit 1
        fi
    done
}

latest_run_dir() {
    local latest=""
    if [[ -d "$RUNS_DIR" ]]; then
        latest="$(find "$RUNS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sort | tail -n 1 || true)"
    fi
    printf '%s' "$latest"
}

sha256_file() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$1" | awk '{print $1}'
    else
        echo "unknown"
    fi
}

backup_database() {
    local dest="$1"
    log "Dumping MySQL to ${dest}/database.sql.gz ..."
    "${COMPOSE[@]}" exec -T mysql sh -c \
        'mysqldump --single-transaction --quick -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
        | gzip -9 > "${dest}/database.sql.gz"

    if [[ ! -s "${dest}/database.sql.gz" ]]; then
        echo "Backup failed: ${dest}/database.sql.gz is empty." >&2
        exit 1
    fi
}

backup_storage_files() {
    local dest="$1"
    log "Archiving storage volume to ${dest}/storage-files.tgz ..."

    docker run --rm \
        -v "${STORAGE_VOLUME}:/data:ro" \
        -v "${dest}:/backup" \
        alpine:3.20 \
        sh -c 'tar czf /backup/storage-files.tgz -C /data app/private app/exports logs 2>/dev/null || tar czf /backup/storage-files.tgz -C /data .'

    if [[ ! -s "${dest}/storage-files.tgz" ]]; then
        echo "Backup failed: ${dest}/storage-files.tgz is empty." >&2
        exit 1
    fi
}

backup_env_snapshot() {
    local dest="$1"
    if [[ -f deploy/env/production.env ]]; then
        cp deploy/env/production.env "${dest}/production.env.snapshot"
        chmod 600 "${dest}/production.env.snapshot"
    fi
}

write_manifest() {
    local dest="$1"
    local timestamp="$2"
    local git_commit host_name

    git_commit="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo unknown)"
    host_name="$(hostname -s 2>/dev/null || hostname 2>/dev/null || echo unknown)"

    cat > "${dest}/manifest.json" <<EOF
{
  "timestamp": "${timestamp}",
  "app": "cashflow-summary",
  "host": "${host_name}",
  "git_commit": "${git_commit}",
  "storage_volume": "${STORAGE_VOLUME}",
  "storage_paths": ["app/private", "app/exports", "logs"],
  "files": {
    "database.sql.gz": {
      "bytes": $(stat -c%s "${dest}/database.sql.gz" 2>/dev/null || stat -f%z "${dest}/database.sql.gz"),
      "sha256": "$(sha256_file "${dest}/database.sql.gz")"
    },
    "storage-files.tgz": {
      "bytes": $(stat -c%s "${dest}/storage-files.tgz" 2>/dev/null || stat -f%z "${dest}/storage-files.tgz"),
      "sha256": "$(sha256_file "${dest}/storage-files.tgz")"
    },
    "production.env.snapshot": {
      "present": $([ -f "${dest}/production.env.snapshot" ] && echo true || echo false)
    }
  }
}
EOF
}

promote_tiers() {
    local run_dir="$1"
    local week_key month_key

    mkdir -p "$WEEKLY_DIR" "$MONTHLY_DIR"

    if [[ "${RETENTION_WEEKLY:-0}" -gt 0 && "$(date +%u)" == "7" ]]; then
        week_key="$(date +%G-W%V)"
        log "Promoting run to weekly/${week_key} ..."
        rm -rf "${WEEKLY_DIR}/${week_key}"
        cp -a "$run_dir" "${WEEKLY_DIR}/${week_key}"
    fi

    if [[ "${RETENTION_MONTHLY:-0}" -gt 0 && "$(date +%d)" == "01" ]]; then
        month_key="$(date +%Y-%m)"
        log "Promoting run to monthly/${month_key} ..."
        rm -rf "${MONTHLY_DIR}/${month_key}"
        cp -a "$run_dir" "${MONTHLY_DIR}/${month_key}"
    fi
}

prune_dir_count() {
    local dir="$1"
    local keep="$2"
    local count=0
    local path

    [[ -d "$dir" ]] || return 0

    count="$(find "$dir" -mindepth 1 -maxdepth 1 2>/dev/null | wc -l | tr -d ' ')"
    if [[ "$count" -le "$keep" ]]; then
        return 0
    fi

    find "$dir" -mindepth 1 -maxdepth 1 | sort | head -n "$((count - keep))" | while read -r path; do
        log "Pruning ${path} ..."
        rm -rf "$path"
    done
}

prune_runs_older_than_days() {
    local days="$1"
    local path

    [[ -d "$RUNS_DIR" ]] || return 0

    find "$RUNS_DIR" -mindepth 1 -maxdepth 1 -type d -mtime "+${days}" 2>/dev/null | while read -r path; do
        log "Pruning daily run ${path} (older than ${days} days) ..."
        rm -rf "$path"
    done
}

cmd_retention() {
    mkdir -p "$RUNS_DIR" "$WEEKLY_DIR" "$MONTHLY_DIR"
    chmod 700 "$BACKUP_ROOT" 2>/dev/null || true

    prune_runs_older_than_days "$RETENTION_DAILY"

    if [[ "${RETENTION_WEEKLY:-0}" -gt 0 ]]; then
        prune_dir_count "$WEEKLY_DIR" "$RETENTION_WEEKLY"
    fi

    if [[ "${RETENTION_MONTHLY:-0}" -gt 0 ]]; then
        prune_dir_count "$MONTHLY_DIR" "$RETENTION_MONTHLY"
    fi

    log "Retention applied (daily>${RETENTION_DAILY}d pruned; weekly=${RETENTION_WEEKLY}; monthly=${RETENTION_MONTHLY})."
}

cmd_run() {
    local timestamp dest

    require_production_env
    require_stack

    timestamp="$(date +%Y%m%d-%H%M%S)"
    dest="${RUNS_DIR}/${timestamp}"

    mkdir -p "$dest" "$RUNS_DIR" "$WEEKLY_DIR" "$MONTHLY_DIR" "$CONFIG_DIR"
    chmod 700 "$BACKUP_ROOT" 2>/dev/null || true

    backup_database "$dest"
    backup_storage_files "$dest"
    backup_env_snapshot "$dest"
    write_manifest "$dest" "$timestamp"

    promote_tiers "$dest"

    if [[ "${BACKUP_SKIP_RETENTION:-0}" != "1" ]]; then
        cmd_retention
    fi

    if [[ -n "${BACKUP_OFFSITE_RSYNC_DEST:-}" && "${BACKUP_SKIP_OFFSITE:-0}" != "1" ]]; then
        maybe_encrypt_for_offsite "$dest"
        cmd_offsite
    fi

    log "Backup complete: ${dest}"
    ls -lh "${dest}/database.sql.gz" "${dest}/storage-files.tgz"
}

cmd_verify() {
    local latest db storage

    latest="$(latest_run_dir)"
    if [[ -z "$latest" ]]; then
        echo "No backups found under ${RUNS_DIR}" >&2
        exit 1
    fi

    db="${latest}/database.sql.gz"
    storage="${latest}/storage-files.tgz"

    [[ -s "$db" ]] || { echo "Missing or empty: $db" >&2; exit 1; }
    [[ -s "$storage" ]] || { echo "Missing or empty: $storage" >&2; exit 1; }

    log "Latest backup OK: ${latest}"
    ls -lh "$db" "$storage"
    if [[ -f "${latest}/manifest.json" ]]; then
        head -n 5 "${latest}/manifest.json"
    fi
}

cmd_offsite() {
    local dest="${BACKUP_OFFSITE_RSYNC_DEST:?Set BACKUP_OFFSITE_RSYNC_DEST in deploy/env/backup.env}"

    if ! command -v rsync >/dev/null 2>&1; then
        echo "rsync is required for off-site backup." >&2
        exit 1
    fi

    mkdir -p "$BACKUP_ROOT"
    log "Syncing ${BACKUP_ROOT}/ to ${dest} ..."
    rsync -a --delete \
        --exclude='.rsync-partial' \
        "${BACKUP_ROOT}/" "${dest}/"
    log "Off-site sync complete."
}

maybe_encrypt_for_offsite() {
    if [[ -z "${BACKUP_GPG_RECIPIENT:-}" ]]; then
        return 0
    fi

    if ! command -v gpg >/dev/null 2>&1; then
        echo "gpg not installed; skipping encryption." >&2
        return 0
    fi

    local latest="$1"
    log "Encrypting production.env snapshot for off-site copy (GPG recipient: ${BACKUP_GPG_RECIPIENT}) ..."
    if [[ -f "${latest}/production.env.snapshot" ]]; then
        gpg --batch --yes --encrypt --recipient "$BACKUP_GPG_RECIPIENT" \
            -o "${latest}/production.env.snapshot.gpg" \
            "${latest}/production.env.snapshot"
        chmod 600 "${latest}/production.env.snapshot.gpg"
    fi
}

cmd_config() {
    local stamp dest nginx_enabled

    mkdir -p "$CONFIG_DIR"
    stamp="$(date +%Y%m%d-%H%M%S)"
    dest="${CONFIG_DIR}/${stamp}"
    mkdir -p "$dest"

    log "Saving compose + deploy nginx templates to ${dest} ..."
    cp docker-compose.yml docker-compose.production.yml "$dest/"
    cp deploy/vps/nginx-host/cashflow.gsautobilan.com.conf "$dest/" 2>/dev/null || true

    nginx_enabled="/etc/nginx/sites-enabled/cashflow-summary.conf"
    if [[ -f "$nginx_enabled" ]]; then
        cp "$nginx_enabled" "${dest}/host-nginx-cashflow-summary.conf"
    elif [[ -r /etc/nginx/sites-enabled/cashflow-summary.conf ]]; then
        sudo cp "$nginx_enabled" "${dest}/host-nginx-cashflow-summary.conf"
    fi

    prune_dir_count "$CONFIG_DIR" 8
    log "Config snapshot complete: ${dest}"
}

usage() {
    cat <<EOF
Usage: $(basename "$0") [command]

Commands:
  run        Full backup (default): DB + storage + env, retention, optional off-site
  retention  Apply retention only
  verify     Validate latest backup artifacts
  offsite    rsync BACKUP_ROOT to BACKUP_OFFSITE_RSYNC_DEST
  config     Weekly host/compose config snapshot

Optional: deploy/env/backup.env (see deploy/env/backup.env.example)
EOF
}

main() {
    load_config

    case "${1:-run}" in
        run)
            cmd_run
            ;;
        retention)
            cmd_retention
            ;;
        verify)
            cmd_verify
            ;;
        offsite)
            cmd_offsite
            ;;
        config)
            cmd_config
            ;;
        -h|--help|help)
            usage
            ;;
        *)
            echo "Unknown command: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
}

main "$@"
