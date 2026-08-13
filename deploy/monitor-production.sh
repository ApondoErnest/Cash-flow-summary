#!/usr/bin/env bash
set -euo pipefail

# Cash Flow Summary — production monitoring + alerts (Step 116).
# Run on the VPS with the Docker stack deployed.
#
# Usage:
#   ./deploy/monitor-production.sh check        # run checks; alert on failure (cron)
#   ./deploy/monitor-production.sh status       # print checks only; no alerts
#   ./deploy/monitor-production.sh alert-test   # send a test alert

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMPOSE=(./deploy/compose-production.sh)
MONITOR_ENV="${MONITOR_ENV:-$ROOT/deploy/env/monitor.env}"
BACKUP_ENV="${BACKUP_ENV:-$ROOT/deploy/env/backup.env}"

LOG_TAG="cashflow-summary-monitor"

MONITOR_PUBLIC_URL="${MONITOR_PUBLIC_URL:-}"
MONITOR_LOCAL_URL="${MONITOR_LOCAL_URL:-http://127.0.0.1:8081}"
UPTIME_ALERT_MINUTES="${UPTIME_ALERT_MINUTES:-2}"
ALERT_COOLDOWN_MINUTES="${ALERT_COOLDOWN_MINUTES:-30}"
DISK_WARN_PERCENT="${DISK_WARN_PERCENT:-85}"
BACKUP_MAX_AGE_HOURS="${BACKUP_MAX_AGE_HOURS:-26}"
QUEUE_BACKLOG_WARN="${QUEUE_BACKLOG_WARN:-100}"
TLS_WARN_DAYS="${TLS_WARN_DAYS:-14}"
REDIS_MEMORY_WARN_PERCENT="${REDIS_MEMORY_WARN_PERCENT:-80}"
ALERT_EMAIL="${ALERT_EMAIL:-}"
ALERT_WEBHOOK_URL="${ALERT_WEBHOOK_URL:-}"
ALERT_ON_OK="${ALERT_ON_OK:-true}"
STATE_DIR="${STATE_DIR:-/var/lib/cashflow-summary}"

BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/cashflow-summary}"
RUNS_DIR="${BACKUP_ROOT}/runs"

FAILURES=()
WARNINGS=()

load_config() {
    if [[ -f "$BACKUP_ENV" ]]; then
        set -a
        # shellcheck disable=SC1090
        source "$BACKUP_ENV"
        set +a
        RUNS_DIR="${BACKUP_ROOT}/runs"
    fi

    if [[ -f "$MONITOR_ENV" ]]; then
        set -a
        # shellcheck disable=SC1090
        source "$MONITOR_ENV"
        set +a
    fi

    if [[ -z "$MONITOR_PUBLIC_URL" && -f deploy/env/production.env ]]; then
        MONITOR_PUBLIC_URL="$(grep -E '^APP_URL=' deploy/env/production.env | cut -d= -f2- | tr -d '\r"' || true)"
    fi

    if [[ -z "$MONITOR_LOCAL_URL" && -f deploy/env/production.env ]]; then
        local port
        port="$(grep -E '^HTTP_PORT=' deploy/env/production.env | cut -d= -f2- | tr -d '\r' || true)"
        port="${port:-8081}"
        MONITOR_LOCAL_URL="http://127.0.0.1:${port}"
    fi
}

log() {
    echo "[$(date -Iseconds)] [$LOG_TAG] $*"
}

fail() {
    FAILURES+=("$1")
    log "FAIL: $1"
}

warn() {
    WARNINGS+=("$1")
    log "WARN: $1"
}

pass() {
    log "OK: $1"
}

http_code() {
    curl -s -o /dev/null -w '%{http_code}' --connect-timeout 10 --max-time 20 "$1" 2>/dev/null || echo "000"
}

check_containers() {
    local running required service

    running="$("${COMPOSE[@]}" ps --services --filter status=running 2>/dev/null || true)"
    required=(nginx app mysql redis horizon scheduler)

    for service in "${required[@]}"; do
        if echo "$running" | grep -qx "$service"; then
            pass "container ${service} running"
        else
            fail "container not running: ${service}"
        fi
    done
}

check_http_up() {
    local label url code

    for label_url in "local ${MONITOR_LOCAL_URL}/up" "public ${MONITOR_PUBLIC_URL}/up"; do
        label="${label_url%% *}"
        url="${label_url#* }"

        if [[ "$label" == "public" && -z "$MONITOR_PUBLIC_URL" ]]; then
            continue
        fi

        code="$(http_code "$url")"
        if [[ "$code" == "200" ]]; then
            pass "${label} /up HTTP 200 (${url})"
        else
            fail "${label} /up HTTP ${code} (${url})"
        fi
    done
}

check_disk() {
    local mount pct

    while read -r pct mount; do
        pct="${pct%\%}"
        if [[ "$pct" -ge "$DISK_WARN_PERCENT" ]]; then
            warn "disk ${pct}% on ${mount} (threshold ${DISK_WARN_PERCENT}%)"
        else
            pass "disk ${pct}% on ${mount}"
        fi
    done < <(df -P / "$BACKUP_ROOT" 2>/dev/null | awk 'NR>1 {print $5, $6}' | sort -u)
}

check_backup_freshness() {
    local latest age_hours

    latest="$(find "$RUNS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sort | tail -n 1 || true)"
    if [[ -z "$latest" ]]; then
        warn "no backups under ${RUNS_DIR} (run ./deploy/backup-production.sh run)"
        return
    fi

    age_hours=$(( ( $(date +%s) - $(stat -c %Y "$latest" 2>/dev/null || stat -f %m "$latest") ) / 3600 ))
    if [[ "$age_hours" -gt "$BACKUP_MAX_AGE_HOURS" ]]; then
        fail "latest backup is ${age_hours}h old (${latest}; max ${BACKUP_MAX_AGE_HOURS}h)"
    else
        pass "latest backup ${age_hours}h old (${latest})"
    fi

    if [[ ! -s "${latest}/database.sql.gz" || ! -s "${latest}/storage-files.tgz" ]]; then
        fail "latest backup incomplete: ${latest}"
    fi
}

check_tls_expiry() {
    local host days

    if [[ -z "$MONITOR_PUBLIC_URL" ]]; then
        return
    fi

    host="$(printf '%s' "$MONITOR_PUBLIC_URL" | sed -E 's#^https?://([^/]+).*#\1#')"
    days="$(
        echo | openssl s_client -servername "$host" -connect "${host}:443" 2>/dev/null \
            | openssl x509 -noout -enddate 2>/dev/null \
            | xargs -I{} date -d {} +%s 2>/dev/null \
            || true
    )"

    if [[ -z "$days" ]]; then
        warn "could not read TLS certificate for ${host}"
        return
    fi

    days=$(( ( days - $(date +%s) ) / 86400 ))
    if [[ "$days" -lt "$TLS_WARN_DAYS" ]]; then
        warn "TLS certificate for ${host} expires in ${days} days (threshold ${TLS_WARN_DAYS})"
    else
        pass "TLS certificate for ${host} expires in ${days} days"
    fi
}

check_redis_memory() {
    local info used max pct

    info="$("${COMPOSE[@]}" exec -T redis redis-cli INFO memory 2>/dev/null || true)"
    if [[ -z "$info" ]]; then
        warn "redis INFO memory unavailable"
        return
    fi

    used="$(echo "$info" | awk -F: '/^used_memory:/{gsub(/\r/,"",$2); print $2; exit}')"
    max="$(echo "$info" | awk -F: '/^maxmemory:/{gsub(/\r/,"",$2); print $2; exit}')"

    if [[ -z "$used" ]]; then
        warn "redis used_memory unavailable"
        return
    fi

    if [[ -n "$max" && "$max" -gt 0 ]]; then
        pct=$(( used * 100 / max ))
        if [[ "$pct" -ge "$REDIS_MEMORY_WARN_PERCENT" ]]; then
            warn "redis memory ${pct}% of maxmemory (threshold ${REDIS_MEMORY_WARN_PERCENT}%)"
        else
            pass "redis memory ${pct}% of maxmemory"
        fi
    else
        pass "redis used_memory=${used} (no maxmemory limit set)"
    fi
}

check_queue_backlog() {
    local depth

    depth="$("${COMPOSE[@]}" exec -T redis redis-cli LLEN queues:default 2>/dev/null | tr -d '\r' || echo "")"
    if [[ ! "$depth" =~ ^[0-9]+$ ]]; then
        warn "queue depth unavailable"
        return
    fi

    if [[ "$depth" -ge "$QUEUE_BACKLOG_WARN" ]]; then
        warn "queue backlog ${depth} jobs (threshold ${QUEUE_BACKLOG_WARN})"
    else
        pass "queue backlog ${depth} jobs"
    fi
}

state_file() {
    printf '%s/monitor-state' "$STATE_DIR"
}

read_state() {
    local key="$1"
    if [[ -f "$(state_file)" ]]; then
        grep -E "^${key}=" "$(state_file)" 2>/dev/null | cut -d= -f2- || true
    fi
}

write_state() {
    local downtime alert_sent recovery_sent

    mkdir -p "$STATE_DIR"
    chmod 700 "$STATE_DIR" 2>/dev/null || true

    downtime="${1:-}"
    alert_sent="${2:-}"
    recovery_sent="${3:-}"

    cat > "$(state_file)" <<EOF
DOWNTIME_SINCE=${downtime}
LAST_ALERT_EPOCH=${alert_sent}
LAST_RECOVERY_EPOCH=${recovery_sent}
EOF
    chmod 600 "$(state_file)" 2>/dev/null || true
}

should_alert() {
    local now last epoch_diff

    if [[ ${#FAILURES[@]} -eq 0 ]]; then
        return 1
    fi

    if [[ -z "$ALERT_EMAIL" && -z "$ALERT_WEBHOOK_URL" ]]; then
        return 1
    fi

    now="$(date +%s)"
    last="$(read_state LAST_ALERT_EPOCH)"
    if [[ -n "$last" && "$last" =~ ^[0-9]+$ ]]; then
        epoch_diff=$(( (now - last) / 60 ))
        if [[ "$epoch_diff" -lt "$ALERT_COOLDOWN_MINUTES" ]]; then
            return 1
        fi
    fi

    # Uptime: wait until down long enough (public or local /up failure)
    local down_since uptime_min
    down_since="$(read_state DOWNTIME_SINCE)"
    if [[ -n "$down_since" && "$down_since" =~ ^[0-9]+$ ]]; then
        uptime_min=$(( (now - down_since) / 60 ))
        if [[ "$uptime_min" -lt "$UPTIME_ALERT_MINUTES" ]]; then
            return 1
        fi
    fi

    return 0
}

send_alert() {
    local subject body payload

    subject="[Cash Flow Summary] monitoring alert on $(hostname -s 2>/dev/null || hostname)"
    body="Cash Flow Summary monitoring detected issues on $(date -Iseconds)

Failures:
$(printf ' - %s\n' "${FAILURES[@]}")

Warnings:
$(if [[ ${#WARNINGS[@]} -gt 0 ]]; then printf ' - %s\n' "${WARNINGS[@]}"; else echo ' - (none)'; fi)

Host: $(hostname -f 2>/dev/null || hostname)
Public URL: ${MONITOR_PUBLIC_URL:-n/a}
Log: /var/log/cashflow-summary-monitor.log
"

    if [[ -n "$ALERT_EMAIL" ]] && command -v mail >/dev/null 2>&1; then
        printf '%s' "$body" | mail -s "$subject" "$ALERT_EMAIL" || log "WARN: mail command failed"
    fi

    if [[ -n "$ALERT_WEBHOOK_URL" ]]; then
        payload="$(SUBJECT="$subject" BODY="$body" python3 - <<'PY'
import json, os
print(json.dumps({"text": os.environ["SUBJECT"] + "\n\n" + os.environ["BODY"]}))
PY
)"
        curl -sS -X POST "$ALERT_WEBHOOK_URL" \
            -H 'Content-Type: application/json' \
            -d "$payload" >/dev/null || log "WARN: webhook POST failed"
    fi

    write_state "$(read_state DOWNTIME_SINCE)" "$(date +%s)" "$(read_state LAST_RECOVERY_EPOCH)"
}

send_recovery() {
    local subject body payload

    if [[ "$ALERT_ON_OK" != "true" ]]; then
        return 0
    fi

    if [[ -z "$ALERT_EMAIL" && -z "$ALERT_WEBHOOK_URL" ]]; then
        return 0
    fi

    subject="[Cash Flow Summary] monitoring recovered on $(hostname -s 2>/dev/null || hostname)"
    body="All Cash Flow Summary monitoring checks passed again at $(date -Iseconds).

Host: $(hostname -f 2>/dev/null || hostname)
Public URL: ${MONITOR_PUBLIC_URL:-n/a}
"

    if [[ -n "$ALERT_EMAIL" ]] && command -v mail >/dev/null 2>&1; then
        printf '%s' "$body" | mail -s "$subject" "$ALERT_EMAIL" || true
    fi

    if [[ -n "$ALERT_WEBHOOK_URL" ]]; then
        payload="$(SUBJECT="$subject" BODY="$body" python3 - <<'PY'
import json, os
print(json.dumps({"text": os.environ["SUBJECT"] + "\n\n" + os.environ["BODY"]}))
PY
)"
        curl -sS -X POST "$ALERT_WEBHOOK_URL" \
            -H 'Content-Type: application/json' \
            -d "$payload" >/dev/null || true
    fi

    write_state "" "$(read_state LAST_ALERT_EPOCH)" "$(date +%s)"
}

update_downtime_state() {
    local now down_since had_failures

    now="$(date +%s)"
    down_since="$(read_state DOWNTIME_SINCE)"
    had_failures=0

    for msg in "${FAILURES[@]}"; do
        if [[ "$msg" == *"/up HTTP"* ]]; then
            had_failures=1
            break
        fi
    done

    if [[ "$had_failures" -eq 1 ]]; then
        if [[ -z "$down_since" ]]; then
            write_state "$now" "$(read_state LAST_ALERT_EPOCH)" "$(read_state LAST_RECOVERY_EPOCH)"
        fi
    elif [[ -n "$down_since" ]]; then
        if [[ ${#FAILURES[@]} -eq 0 ]]; then
            send_recovery
        fi
        write_state "" "$(read_state LAST_ALERT_EPOCH)" "$(read_state LAST_RECOVERY_EPOCH)"
    fi
}

run_checks() {
    log "Starting checks (public=${MONITOR_PUBLIC_URL:-n/a}, local=${MONITOR_LOCAL_URL})"
    check_containers
    check_http_up
    check_disk
    check_backup_freshness
    check_tls_expiry
    check_redis_memory
    check_queue_backlog
}

cmd_status() {
    run_checks

    echo ""
    if [[ ${#FAILURES[@]} -eq 0 ]]; then
        log "Summary: OK (${#WARNINGS[@]} warning(s))"
        exit 0
    fi

    log "Summary: FAILED (${#FAILURES[@]} failure(s), ${#WARNINGS[@]} warning(s))"
    exit 1
}

cmd_check() {
    local had_down

    had_down="$(read_state DOWNTIME_SINCE)"
    run_checks
    update_downtime_state

    if should_alert; then
        send_alert
        log "Alert sent"
    fi

    if [[ ${#FAILURES[@]} -eq 0 ]]; then
        log "Summary: OK (${#WARNINGS[@]} warning(s))"
        exit 0
    fi

    log "Summary: FAILED (${#FAILURES[@]} failure(s), ${#WARNINGS[@]} warning(s))"
    exit 1
}

cmd_alert_test() {
    if [[ -z "$ALERT_EMAIL" && -z "$ALERT_WEBHOOK_URL" ]]; then
        echo "Set ALERT_EMAIL and/or ALERT_WEBHOOK_URL in deploy/env/monitor.env" >&2
        exit 1
    fi

    FAILURES=("Test alert from monitor-production.sh")
    WARNINGS=("This is a test — no action required")
    send_alert
    log "Test alert sent"
}

usage() {
    cat <<EOF
Usage: $(basename "$0") [command]

Commands:
  check        Run all checks; send alerts on failure (for cron)
  status       Run checks and print summary; no alerts
  alert-test   Send a test alert using monitor.env settings

Optional: deploy/env/monitor.env (see deploy/env/monitor.env.example)
Install cron: ./deploy/install-monitor-cron.sh
EOF
}

main() {
    load_config

    case "${1:-check}" in
        check)
            cmd_check
            ;;
        status)
            cmd_status
            ;;
        alert-test)
            cmd_alert_test
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
