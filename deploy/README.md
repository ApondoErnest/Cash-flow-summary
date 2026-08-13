# Docker runbook — Cash Flow Summary

[← Deployment docs](../docs/operations/deployment.md) · **Step 109**

## Environment isolation (important)

| Environment | Config file | Used by |
|-------------|-------------|---------|
| **Local dev** | `.env` (project root) | `php artisan serve`, local MySQL/Redis — **unchanged** |
| **Docker** | `deploy/env/docker.env` | Docker containers only (mounted as `/var/www/html/.env`) |
| **VPS / production** | `deploy/env/production.env` on server | Production containers only |

Root `.env` is **never** read or modified by Docker.

Always run Docker through **`./deploy/compose.sh`** (merges `docker-compose.yml` + `docker-compose.local.yml` with `deploy/env/docker.env`).

On the VPS, use **`./deploy/compose-production.sh`** (`docker-compose.yml` + `docker-compose.production.yml` only — never merge the local file).

---

## Compose files

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Shared services (no host ports) |
| `docker-compose.local.yml` | Local Docker: host port + `deploy/env/docker.env` mounts |
| `docker-compose.production.yml` | VPS: `127.0.0.1` bind + `deploy/env/production.env` mounts |

Local:

```bash
./deploy/compose.sh up -d
```

Production (VPS):

```bash
./deploy/compose-production.sh up -d
```

---

## 1. Prerequisites

- Docker Desktop / Engine running
- Port **8080** free (or change `HTTP_PORT` in `deploy/env/docker.env`)
- **No host Node, Composer, or PHP required** — builds run inside Docker

```bash
docker --version && docker compose version && docker info
```

---

## 2. One-time Docker env setup

```bash
./deploy/setup-docker-env.sh
```

This creates **`deploy/env/docker.env`** from the example. It does **not** touch `.env`.

Edit `deploy/env/docker.env`:

1. Set `DB_PASSWORD` and `DB_ROOT_PASSWORD` (replace `change-me-docker`)
2. Generate `APP_KEY`:

```bash
./deploy/compose.sh build app
./deploy/compose.sh run --rm --no-deps app php artisan key:generate
```

(`key:generate` writes into `deploy/env/docker.env` via the container mount.)

---

## 3. Build images

The **`Dockerfile`** is multi-stage:

1. **Composer** — PHP dependencies  
2. **Node 24** — `npm ci && npm run build` (Vite + Tailwind)  
3. **PHP 8.4 FPM** — runtime image with compiled assets  

```bash
./deploy/build.sh
```

Builds **app**, then **nginx**.

Optional host-only asset build for local Vite iteration (requires Node on your Mac):

```bash
./deploy/build-assets.sh
```

---

## 4. Start database and cache

```bash
./deploy/compose.sh up -d mysql redis
./deploy/compose.sh ps
```

Wait until **mysql** and **redis** are **healthy**.

---

## 5. Migrate and seed (Docker database)

Uses the **Docker MySQL volume**, separate from your local MySQL:

```bash
./deploy/compose.sh run --rm app php artisan migrate --force --seed
```

---

## 6. Start the full stack

```bash
./deploy/compose.sh up -d
./deploy/compose.sh ps
```

Open **http://localhost:8080** (or your `HTTP_PORT`).

---

## 7. Verify

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/up   # expect 200
```

- Login: http://localhost:8080/login  
- Seeded users: `owner` / `password` (see `SEED_*` in `deploy/env/docker.env`)

```bash
./deploy/compose.sh logs horizon --tail 20
./deploy/compose.sh logs scheduler --tail 20
```

---

## 8. Day-to-day commands

Use **`./deploy/compose.sh`** instead of `docker compose`:

```bash
./deploy/compose.sh ps
./deploy/compose.sh logs -f app horizon scheduler
./deploy/compose.sh exec app php artisan about
./deploy/compose.sh down          # stop, keep volumes
./deploy/compose.sh down -v       # ⚠ wipe Docker DB/storage volumes
./deploy/compose.sh up -d         # start again
```

---

## 9. After code changes

```bash
./deploy/build.sh
./deploy/compose.sh up -d
```

---

## 10. Services

| Service | Role |
|---------|------|
| `nginx` | HTTP on host port → container 80 |
| `app` | PHP 8.4 FPM + Laravel |
| `mysql` | MySQL 8 (internal) |
| `redis` | Redis 7 (internal) |
| `horizon` | Queue worker |
| `scheduler` | `php artisan schedule:work` — WhatsApp summaries, cleanups |

---

## 11. Troubleshooting

| Issue | Fix |
|-------|-----|
| `Missing deploy/env/docker.env` | `./deploy/setup-docker-env.sh` |
| Used `docker compose` directly | Use `./deploy/compose.sh` so Compose reads the right env file |
| Port in use | Change `HTTP_PORT` in `deploy/env/docker.env` |
| Build fails during `npm run build` | Check Docker build log; ensure network access for font CDN |
| Build fails: `public/build/manifest.json` missing | Re-run `./deploy/build.sh` — assets are built in the Docker image |
| Local dev broken after Docker | Local dev uses root `.env` only — they are independent |

---

## 13. Persistent volumes (Step 110)

Data survives container restarts and `./deploy/compose.sh down` (without `-v`):

| Volume | Holds |
|--------|--------|
| `cashflow-summary_mysql_data` | Database |
| `cashflow-summary_storage_data` | Imports, exports, logs |
| `cashflow-summary_redis_data` | Redis queue/cache AOF |

Full reference: **[deploy/volumes.md](volumes.md)**

Verify persistence:

```bash
chmod +x deploy/verify-volumes.sh
./deploy/verify-volumes.sh
```

---

## 14. Smoke tests (Step 111 / AC #34)

```bash
chmod +x deploy/smoke-test.sh deploy/smoke-check-app.php
./deploy/smoke-test.sh
```

Validates services, HTTP via nginx, in-container DB/Redis/config, Horizon, and scheduler.

---

## 15. Dockerization checklist (Steps 109–111)

- [ ] `deploy/env/docker.env` exists (root `.env` untouched)
- [ ] `./deploy/build.sh` succeeds
- [ ] `./deploy/compose.sh up -d` — **6** services up
- [ ] `./deploy/verify-volumes.sh` passes
- [ ] `./deploy/smoke-test.sh` passes (AC #34)
- [ ] App reachable at `http://localhost:${HTTP_PORT}` (e.g. `:8081`)

**Next:** Step 112 (VPS provisioning)

---

## VPS provisioning (Steps 112–114)

| Step | Doc | Scripts |
|------|-----|---------|
| **112** | [deploy/vps/PROVISION.md](vps/PROVISION.md) | `bootstrap-server.sh`, `verify-provision.sh` |
| **112+** | **[deploy/vps/HOSTINGER-SUBDOMAIN.md](vps/HOSTINGER-SUBDOMAIN.md)** | **cashflow.gsautobilan.com** on Hostinger VPS `89.117.37.202` |
| **113** | [HOSTINGER-SUBDOMAIN.md](vps/HOSTINGER-SUBDOMAIN.md) Phases 5–6 | Host nginx, Certbot TLS |
| **114** | [HOSTINGER-SUBDOMAIN.md](vps/HOSTINGER-SUBDOMAIN.md) Phases 3–4 | `compose-production.sh`, `build-production.sh`, `smoke-test-production.sh` |
| **115** | [backup-monitoring.md](../docs/operations/backup-monitoring.md) | `backup-production.sh`, `install-backup-cron.sh` |
| **116** | [backup-monitoring.md](../docs/operations/backup-monitoring.md) | `monitor-production.sh`, `install-monitor-cron.sh` |

Production secrets: **`deploy/env/production.env`** only (template: `production.env.example`).

Run on VPS with **`./deploy/compose-production.sh`**.

### Backups (Step 115)

| Script | Purpose |
|--------|---------|
| `./deploy/backup-production.sh run` | Daily backup (DB + storage + env) |
| `./deploy/backup-production.sh verify` | Validate latest backup |
| `./deploy/install-backup-cron.sh` | Install 02:00 daily + Sun 04:00 config cron |

Optional: `deploy/env/backup.env` from `backup.env.example` (off-site rsync, retention).

Back up before updates: **`./deploy/backup-production.sh run`**

### Monitoring and alerts (Step 116)

| Script | Purpose |
|--------|---------|
| `./deploy/monitor-production.sh status` | Run checks; print summary (no alerts) |
| `./deploy/monitor-production.sh check` | Run checks; alert on failure (cron) |
| `./deploy/monitor-production.sh alert-test` | Send a test alert |
| `./deploy/install-monitor-cron.sh` | Install `*/5` monitoring cron |

Optional: `deploy/env/monitor.env` from `monitor.env.example` (`ALERT_EMAIL`, `ALERT_WEBHOOK_URL`, thresholds).

---

## Related

- [docs/operations/deployment.md](../docs/operations/deployment.md)
- [docs/operations/setup.md](../docs/operations/setup.md) — local (non-Docker) dev
- `deploy/env/vps.env.example` — production env template (VPS, later steps)
