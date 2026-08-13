# Backup and Monitoring

[← Documentation hub](../README.md) | **Sprint 8**

---

## Backup scope

| Asset | Method | Frequency |
|-------|--------|-----------|
| MySQL database | `mysqldump` → `database.sql.gz` | Daily 02:00 |
| Import CSVs + exports + logs | `storage-files.tgz` from Docker volume | Daily 02:00 |
| Application `.env` | `production.env.snapshot` in each run | Daily (on change between runs) |
| Docker compose + nginx config | `backup-production.sh config` | Weekly (Sunday 04:00) |

### Production scripts (VPS)

| Script | Purpose |
|--------|---------|
| `./deploy/backup-production.sh run` | Full backup + retention + optional off-site |
| `./deploy/backup-production.sh verify` | Check latest backup artifacts |
| `./deploy/backup-production.sh retention` | Apply retention only |
| `./deploy/backup-production.sh offsite` | rsync to `BACKUP_OFFSITE_RSYNC_DEST` |
| `./deploy/backup-production.sh config` | Snapshot compose + host nginx |
| `./deploy/install-backup-cron.sh` | Install cron for daily + weekly jobs |

Optional settings: **`deploy/env/backup.env`** (template: `deploy/env/backup.env.example`).

Default backup root: **`/var/backups/cashflow-summary/`** (`runs/`, optional `weekly/`, `monthly/`, `config/`).

### Local VPS disk policy (default)

On the server, backups auto-prune so disk does not pile up:

| Setting | Default | Effect |
|---------|---------|--------|
| `RETENTION_DAILY` | **28** | Delete daily runs older than 28 days (~4 weeks) |
| `RETENTION_WEEKLY` | **0** | No extra weekly copies on VPS |
| `RETENTION_MONTHLY` | **0** | No extra monthly copies on VPS |

At steady state you keep **at most ~28 daily snapshots** under `runs/` (one per day if cron runs daily). Each snapshot is one DB gzip + one storage tarball — size grows with import/export volume, so monitor `df -h`.

Enable `RETENTION_WEEKLY` / `RETENTION_MONTHLY` only when copying to **off-server** storage (see `BACKUP_OFFSITE_RSYNC_DEST`). Long-term tiers (7 / 4 / 12) in the table below apply to **off-site** archives, not the default VPS layout.

Log file: **`/var/log/cashflow-summary-backup.log`**

See also [deploy/volumes.md](../../deploy/volumes.md) and [deploy/vps/HOSTINGER-SUBDOMAIN.md](../../deploy/vps/HOSTINGER-SUBDOMAIN.md) § Scheduled backups.

### Retention

| Tier | Retention | Where |
|------|-----------|--------|
| Daily | 28 days (default on VPS) | `/var/backups/cashflow-summary/runs/` |
| Weekly full | 4 weeks | Off-server only (optional; set `RETENTION_WEEKLY=4`) |
| Monthly | 12 months | Off-server only (optional; set `RETENTION_MONTHLY=12`) |

Off-server backups encrypted at rest. **Do not** enable weekly/monthly promotion on a small VPS unless you sync to external storage — each tier is a full copy and uses extra disk.

---

## Restore test

Quarterly restore drill (Step **117**, AC **#35**):

1. Validate backup artifacts (`verify-backup`)
2. Restore MySQL dump + storage files to an **isolated** Docker stack (`cashflow-restore-drill_*` volumes, port **8082**)
3. Run smoke tests (`./deploy/smoke-test.sh` via restore drill compose)
4. Write a report under `deploy/reports/restore-drill-*.txt` (duration + backup path)
5. Tear down the drill stack when finished

**Production is never modified** — restore drill uses separate volumes from `cashflow-summary_*`.

### Restore drill scripts

| Script | Purpose |
|--------|---------|
| `./deploy/restore-drill.sh verify-backup` | Check gzip/tar + optional manifest sha256 |
| `./deploy/restore-drill.sh run` | Full drill: restore + smoke tests + report |
| `./deploy/restore-drill.sh run --teardown` | Same, then remove drill stack automatically |
| `./deploy/restore-drill.sh smoke` | Re-run smoke tests on running drill stack |
| `./deploy/restore-drill.sh teardown` | Stop drill stack and delete `cashflow-restore-drill_*` volumes |
| `./deploy/compose-restore-drill.sh` | Compose wrapper for isolated stack |

Uses `production.env.snapshot` from the backup run as `deploy/env/restore-drill.env` (gitignored).

### VPS example

```bash
cd /var/www/cashflow-summary

./deploy/restore-drill.sh verify-backup
./deploy/restore-drill.sh run
# Optional: open http://127.0.0.1:8082/login on the VPS (SSH tunnel)

./deploy/restore-drill.sh teardown
```

Production keeps running on `127.0.0.1:8081` / `https://cashflow.gsautobilan.com` throughout the drill.

See [deploy/vps/HOSTINGER-SUBDOMAIN.md](../../deploy/vps/HOSTINGER-SUBDOMAIN.md) § Restore drill.

---

## Monitoring

Production checks run every **5 minutes** via cron (`monitor-production.sh check`).

| Script | Purpose |
|--------|---------|
| `./deploy/monitor-production.sh check` | Run all checks; alert on failure (cron) |
| `./deploy/monitor-production.sh status` | Print checks only; no alerts |
| `./deploy/monitor-production.sh alert-test` | Send a test alert |
| `./deploy/install-monitor-cron.sh` | Install `*/5` monitoring cron |

Optional settings: **`deploy/env/monitor.env`** (template: `deploy/env/monitor.env.example`).

State file: **`/var/lib/cashflow-summary/monitor-state`** (downtime + alert cooldown).

Log file: **`/var/log/cashflow-summary-monitor.log`**

| Signal | Check | Alert if |
|--------|-------|----------|
| Docker stack | 6 services running (`nginx`, `app`, `mysql`, `redis`, `horizon`, `scheduler`) | Any container down |
| Application uptime | `GET /up` (local `:8081` + public URL) | HTTP ≠ 200 for **> 2 min** |
| Backup freshness | Latest run under `/var/backups/cashflow-summary/runs/` | Missing, incomplete, or **> 26 h** old |
| Server disk | `df` on `/` and backup root | **> 85%** (warning in alert body) |
| TLS certificate | `openssl s_client` on public host | **< 14 days** to expiry (warning) |
| Redis memory | `INFO memory` in container | **> 80%** of `maxmemory` (warning) |
| Queue backlog | `LLEN queues:default` | **≥ 100** jobs (warning) |

Alert destinations (set at least one on the VPS): **`ALERT_EMAIL`** and/or **`ALERT_WEBHOOK_URL`** (Slack-compatible JSON `{"text": "..."}`).

Duplicate alerts for the same incident are suppressed for **`ALERT_COOLDOWN_MINUTES`** (default 30). Send **`ALERT_ON_OK=true`** recovery messages when checks pass again after downtime.

See [deploy/vps/HOSTINGER-SUBDOMAIN.md](../../deploy/vps/HOSTINGER-SUBDOMAIN.md) § Monitoring and alerts.

### Application signals (future)

These remain documented for Horizon/log review; not yet wired into `monitor-production.sh`:

| Signal | Alert if |
|--------|----------|
| Failed imports | > 3 in 1 hour |
| Verification failures | Spike vs baseline |
| Reconciliation failures | Any in production |
| Pending revisions | > N days (configurable) |
| WhatsApp failures | Any failed after max retries |
| MySQL disk | > 80% |

---

## Log retention

- Application logs: 30 days
- Audit logs: 2 years (DB)
- Horizon failed jobs: review weekly

---

## Scheduled tasks

| Task | Schedule |
|------|----------|
| Expire import verifications | Every 15 minutes |
| Daily summary regeneration | Nightly |
| Database backup | 02:00 daily (`backup-production.sh run`) |
| File backup | 02:00 daily (same run as DB) |
| Config snapshot | Sunday 04:00 (`backup-production.sh config`) |
| Monitoring checks | Every 5 min (`monitor-production.sh check`) |
| Certificate check | Weekly (`certbot renew --dry-run` on host) |

---

## Health endpoint

`GET /up` → Laravel health check (HTTP 200 when app, database, and Redis are OK).

Used by Docker healthcheck, smoke tests, and **`monitor-production.sh`**.

---

## Related

- [deployment.md](deployment.md)
- [risk-register.md](../governance/risk-register.md)
