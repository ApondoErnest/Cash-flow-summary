# Docker persistent volumes (Step 110)

[← Docker runbook](README.md) · [Deployment](../docs/operations/deployment.md) · [Backup scope](../docs/operations/backup-monitoring.md)

Docker keeps **application data separate from container images**. Rebuilding or recreating containers does **not** wipe these volumes unless you run `./deploy/compose.sh down -v`.

Local dev (`storage/` on disk + local MySQL) remains **independent** — these volumes belong to the Docker stack only.

---

## Named volumes

| Compose volume | Docker name | Mounted in | Persists |
|----------------|-------------|------------|----------|
| `mysql_data` | `cashflow-summary_mysql_data` | MySQL → `/var/lib/mysql` | Database (users, imports, versions, settings, …) |
| `redis_data` | `cashflow-summary_redis_data` | Redis → `/data` | Queue metadata, AOF (Horizon jobs survive Redis restarts) |
| `storage_data` | `cashflow-summary_storage_data` | `app` + `horizon` → `/var/www/html/storage` | Private CSVs, exports, logs, framework cache |

### What lives under `storage_data`

Laravel `local` disk root is `storage/app/private/`:

| Path (in container) | Purpose |
|---------------------|---------|
| `storage/app/private/imports/` | Committed import CSV files |
| `storage/app/private/temp/verifications/` | Verification uploads (TTL cleanup) |
| `storage/app/exports/` | Generated report exports (TTL cleanup) |
| `storage/logs/` | Application logs |
| `storage/framework/` | Cache, sessions (if file), compiled views |

WhatsApp credentials and org settings are in **MySQL**, not on disk.

---

## Commands

List project volumes:

```bash
docker volume ls --filter name=cashflow-summary_
```

Inspect a volume:

```bash
docker volume inspect cashflow-summary_storage_data
```

**Stop stack, keep data** (safe daily stop):

```bash
./deploy/compose.sh down
```

**Stop and delete all Docker data** (fresh Docker DB + empty storage):

```bash
./deploy/compose.sh down -v
```

After `-v`, run migrate/seed again (see [README.md](README.md) §5).

---

## Verify persistence (Step 110)

Automated check (stack must be running):

```bash
./deploy/verify-volumes.sh
```

Manual spot-check:

```bash
# Write marker via app
./deploy/compose.sh exec app sh -c 'echo ok > storage/app/private/.volume-test'

# Restart app + horizon
./deploy/compose.sh restart app horizon

# Marker should still exist
./deploy/compose.sh exec app cat storage/app/private/.volume-test
```

---

## Backup outline (full procedure in Step 115)

| Asset | Suggested command |
|-------|-------------------|
| MySQL | `./deploy/compose.sh exec mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup.sql` |
| Storage files | `docker run --rm -v cashflow-summary_storage_data:/data -v $(pwd):/backup alpine tar czf /backup/storage.tgz -C /data .` |

Restore is the inverse (Step 117 restore drill).

---

## VPS note

The `app` and `horizon` containers mount `./deploy/` read-only so smoke scripts update without rebuilding the image.

On production, keep the same volume **names** so backup scripts and documentation match across environments. Do not bind-mount host paths unless your ops runbook explicitly requires it — named volumes are the default for this project.
