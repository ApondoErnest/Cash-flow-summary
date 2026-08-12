# Production runbook — cashflow.gsautobilan.com on Hostinger VPS

[← Deploy hub](../README.md) · Steps **112–114** (provision, TLS, deploy)

Deploy **Cash Flow Summary** on **`https://cashflow.gsautobilan.com`** on VPS **`89.117.37.202`**, **alongside** the existing **gs-autobilan** Docker stack — without touching the main site.

**Copy commands from the bash blocks below and paste into your terminal.** Use plain text (e.g. `%{http_code}`, `*.sh`, `cashflow.gsautobilan.com`). Some Markdown viewers add backslash escapes when copying — remove those before running.

---

## Target architecture

```
Internet
   │
   ▼
Host Nginx (:80 / :443)          ← TLS + routing by server_name
   │
   ├── gsautobilan.com (main)    ──► 127.0.0.1:8080  gs-autobilan-nginx  (unchanged)
   │
   └── cashflow.gsautobilan.com  ──► 127.0.0.1:8081  cashflow-summary-nginx  (new)
                                          │
                                          ├── app (PHP-FPM)
                                          ├── horizon (queues)
                                          ├── scheduler (Laravel schedule)
                                          ├── mysql (cashflow_summary DB)
                                          └── redis
```

| Item | Value |
|------|--------|
| VPS IP | `89.117.37.202` |
| SSH user | `ernesto` |
| Subdomain | **`cashflow.gsautobilan.com`** |
| DNS | Hostinger `A` record `cashflow` → `89.117.37.202` |
| Cash Flow Docker port | **`127.0.0.1:8081`** only (localhost — not public) |
| gs-autobilan port | **`127.0.0.1:8080`** (already in use — do not conflict) |
| App directory | `/var/www/cashflow-summary` |
| Git repo | `https://github.com/ApondoErnest/Cash-flow-summary.git` |
| Production secrets | **`deploy/env/production.env`** (mode `600`, never commit) |

### Environment isolation (critical)

| Environment | Config file | Where |
|-------------|-------------|--------|
| Laptop local dev | `.env` | Your Mac — unchanged |
| Laptop Docker | `deploy/env/docker.env` | Local Docker only |
| **VPS production** | **`deploy/env/production.env`** | Server only |
| gs-autobilan | *(its own env)* | Separate — never mix |

### Scripts on the VPS

| Use on VPS | Never use on VPS |
|------------|------------------|
| `./deploy/compose-production.sh` | `./deploy/compose.sh` |
| `./deploy/build-production.sh` | Root `.env` |
| `./deploy/smoke-test-production.sh` | `deploy/env/docker.env` |
| `./deploy/backup-production.sh` | `./deploy/install-backup-cron.sh` (Step 115 cron) |

**VPS host needs only:** Docker, Docker Compose, host nginx, certbot, git, curl. Frontend and Composer build **inside Docker**.

---

## Phase 0 — Prerequisites

SSH in:

```bash
ssh ernesto@89.117.37.202
```

### 0.1 DNS

```bash
dig +short cashflow.gsautobilan.com
```

Expected: **`89.117.37.202`**

### 0.2 Port audit

```bash
sudo ss -tulpn | grep -E ':80|:443|:8080|:8081' || true
sudo ss -tulpn | grep ':8081' || echo "OK: 8081 is free"
sudo grep -R "8080\|gsautobilan" /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null || true
```

If `:8081` is taken, pick another port (e.g. `8082`) and set `HTTP_PORT=8082` in `production.env`.

### 0.3 gs-autobilan health baseline

Record this **before** any nginx changes — recheck after Phases 5–6:

```bash
docker ps --format "table {{.Names}}\t{{.Ports}}"
curl -s -o /dev/null -w "gs-autobilan baseline: %{http_code}\n" http://127.0.0.1:8080/
sudo nginx -t
sudo systemctl status nginx --no-pager
```

Expected: `gs-autobilan-nginx` with `127.0.0.1:8080->80/tcp`; curl not `000`; nginx active.

### 0.4 Docker access

```bash
docker --version
docker compose version
docker ps
```

If `permission denied`:

```bash
sudo usermod -aG docker "$USER"
exit
ssh ernesto@89.117.37.202
docker ps
```

### 0.5 RAM and disk

```bash
free -h
df -h /var/www
```

Recommended: ≥ **8 GB RAM**, ≥ **20 GB free disk**.

### 0.6 Verify Docker build (no host Node required)

Frontend assets and Composer dependencies are built **inside the Docker image** (multi-stage `Dockerfile`). You do **not** install Node.js on the VPS.

After Phase 1, optional sanity check:

```bash
grep -E 'FROM node:|npm run build' /var/www/cashflow-summary/Dockerfile
```

### 0.7 Host Nginx + Certbot (if not already installed)

```bash
sudo apt-get update
sudo apt-get install -y nginx certbot python3-certbot-nginx
sudo systemctl enable nginx
sudo systemctl status nginx --no-pager
```

### 0.8 Firewall check

```bash
sudo ufw status verbose
```

Public ports should be **22, 80, 443** only. MySQL (`3306`), Redis (`6379`), and app ports (`8080`, `8081`) must **not** be public. Docker binds Cash Flow to `127.0.0.1:8081` via `docker-compose.production.yml`.

**Checkpoint 0**

- [ ] DNS → `89.117.37.202`
- [ ] `:8081` free
- [ ] gs-autobilan baseline OK on `:8080`
- [ ] `nginx -t` OK and nginx running
- [ ] Docker works
- [ ] UFW reviewed

---

## Phase 1 — Clone the repository

Inspect existing apps under `/var/www` — **do not change ownership of `/var/www` itself**:

```bash
ls -la /var/www
ls -la /var/www/cashflow-summary 2>/dev/null || echo "cashflow-summary does not exist yet"
```

Create **only** the Cash Flow folder:

```bash
sudo mkdir -p /var/www/cashflow-summary
sudo chown -R "$USER:$USER" /var/www/cashflow-summary
```

Clone or update:

```bash
if [ ! -d /var/www/cashflow-summary/.git ]; then
  git clone https://github.com/ApondoErnest/Cash-flow-summary.git /var/www/cashflow-summary
fi

cd /var/www/cashflow-summary
git checkout main
git pull origin main

if [ -d deploy ]; then
  find deploy -type f -name "*.sh" -exec chmod +x {} \;
else
  echo "ERROR: deploy directory missing" >&2
  exit 1
fi

test -f composer.json && test -f docker-compose.yml && echo "OK: repo ready"
test -x deploy/compose-production.sh && echo "OK: production compose helper ready"
test -x deploy/build-production.sh && echo "OK: production build helper ready"
test -x deploy/backup-production.sh && echo "OK: backup helper ready"
```

**Do not run:** `sudo chown -R "$USER:$USER" /var/www` (protects gs-autobilan and other apps).

**Checkpoint 1**

- [ ] `composer.json` and `docker-compose.yml` exist
- [ ] `deploy/compose-production.sh` and `deploy/build-production.sh` executable

---

## Phase 2 — Production environment file

Create **`deploy/env/production.env`** — the **only** env file Cash Flow uses on the VPS.

**First install only** — do not overwrite an existing file (it would destroy `APP_KEY`, DB passwords, WhatsApp secrets):

```bash
cd /var/www/cashflow-summary

if [ ! -f deploy/env/production.env ]; then
  cp deploy/env/production.env.example deploy/env/production.env
else
  echo "production.env already exists; not overwriting"
fi
```

Set values with **`nano`** (recommended) or `sed`. Use **two different** strong passwords for app user and MySQL root:

```bash
nano deploy/env/production.env
```

Required values:

| Variable | Value |
|----------|--------|
| `APP_URL` | `https://cashflow.gsautobilan.com` |
| `HTTP_PORT` | `8081` |
| `DB_PASSWORD` | Strong app-user password |
| `DB_ROOT_PASSWORD` | Different strong MySQL root password |

Or with `sed` (replace placeholders before running):

```bash
sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=REPLACE_WITH_APP_USER_PASSWORD|' deploy/env/production.env
sed -i 's|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=REPLACE_WITH_MYSQL_ROOT_PASSWORD|' deploy/env/production.env
sed -i 's|^APP_URL=.*|APP_URL=https://cashflow.gsautobilan.com|' deploy/env/production.env
sed -i 's|^HTTP_PORT=.*|HTTP_PORT=8081|' deploy/env/production.env
```

Lock down permissions and verify **no placeholders remain**:

```bash
chmod 600 deploy/env/production.env
ls -l deploy/env/production.env
```

`docker-compose.production.yml` injects this file via **`env_file`** (and PHP-FPM `clear_env=no`). **Do not bind-mount** it as `/var/www/html/.env` — a mode-600/640 mount breaks PHP-FPM even when env vars are set. Keep **`chmod 600`** on the host file.

```bash
grep -E '^(DB_PASSWORD|DB_ROOT_PASSWORD)=' deploy/env/production.env
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|HTTP_PORT|DB_HOST|DB_DATABASE|DB_USERNAME|QUEUE_CONNECTION|CACHE_STORE|SESSION_DRIVER|APP_KEY)=' deploy/env/production.env
```

Must **not** show `REPLACE_WITH_...` or `change-me-production`. Expected includes:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cashflow.gsautobilan.com
HTTP_PORT=8081
APP_KEY=
```

### Do NOT

- Copy your laptop `.env`
- Copy `deploy/env/docker.env`
- Commit `production.env` to Git
- Re-run `cp ... production.env.example` on an existing server

**Checkpoint 2**

- [ ] `production.env` mode `600`
- [ ] `HTTP_PORT=8081`
- [ ] `DB_PASSWORD` ≠ `DB_ROOT_PASSWORD` (no placeholders)
- [ ] `APP_KEY=` still empty (Phase 3)

---

## Phase 3 — Build images and generate APP_KEY

Build frontend assets and PHP dependencies **inside Docker** (first run: **10–20 minutes**):

```bash
cd /var/www/cashflow-summary
./deploy/build-production.sh
```

Generate **`APP_KEY`** — one-off mount writes into `deploy/env/production.env`:

```bash
./deploy/compose-production.sh run --rm --no-deps \
  -v ./deploy/env/production.env:/var/www/html/.env \
  app php artisan key:generate
grep '^APP_KEY=' deploy/env/production.env
docker images | grep cashflow-summary
```

**Stop if `APP_KEY` is still empty** — do not continue to Phase 4:

```bash
if ! grep -q '^APP_KEY=base64:' deploy/env/production.env; then
  echo "ERROR: APP_KEY not written to deploy/env/production.env" >&2
  exit 1
fi
echo "OK: APP_KEY set"
```

**Checkpoint 3**

- [ ] `./deploy/build-production.sh` exits 0
- [ ] `APP_KEY=base64:...` in `production.env`
- [ ] `cashflow-summary-app` and `cashflow-summary-nginx` images exist

---

## Phase 4 — Start database, migrate, bring up stack

```bash
cd /var/www/cashflow-summary
./deploy/compose-production.sh up -d mysql redis
```

Wait until MySQL is healthy:

```bash
until ./deploy/compose-production.sh ps | grep -i "mysql" | grep -q "healthy"; do
  echo "Waiting for MySQL to become healthy..."
  sleep 5
done
./deploy/compose-production.sh ps
```

### First install — migrate and seed

Creates default owner user (`owner` / `password` — change in Phase 7):

```bash
./deploy/compose-production.sh run --rm app php artisan migrate --force --seed
```

### Start full stack

```bash
./deploy/compose-production.sh up -d
./deploy/compose-production.sh ps
```

All **6** services should be **Up**: `nginx`, `app`, `mysql`, `redis`, `horizon`, `scheduler`.

`storage:link` runs automatically on container start (`docker/entrypoint.sh`).

### Verify localhost

```bash
curl -s -o /dev/null -w "8081/up: %{http_code}\n" http://127.0.0.1:8081/up
curl -s -o /dev/null -w "8081/login: %{http_code}\n" http://127.0.0.1:8081/login
curl -s -o /dev/null -w "gs-autobilan: %{http_code}\n" http://127.0.0.1:8080/
./deploy/smoke-test-production.sh
```

If `:8081/up` is not **200**, check logs immediately:

```bash
./deploy/compose-production.sh logs --tail=100 nginx
./deploy/compose-production.sh logs --tail=100 app
./deploy/compose-production.sh logs --tail=100 mysql
./deploy/compose-production.sh logs --tail=100 redis
./deploy/compose-production.sh logs --tail=100 horizon
./deploy/compose-production.sh logs --tail=100 scheduler
```

**Checkpoint 4**

- [ ] 6 services up
- [ ] `http://127.0.0.1:8081/up` → 200
- [ ] Smoke tests pass
- [ ] gs-autobilan on `:8080` still OK

---

## Phase 5 — Host Nginx (subdomain routing)

Add a **new** site only — **do not edit** gs-autobilan's nginx config.

Inspect the config — **must** point to `127.0.0.1:8081`, not `8080` or bare `:80`:

```bash
cd /var/www/cashflow-summary
cat deploy/vps/nginx-host/cashflow.gsautobilan.com.conf
grep -E 'server_name|proxy_pass' deploy/vps/nginx-host/cashflow.gsautobilan.com.conf
```

Expected:

```text
server_name cashflow.gsautobilan.com;
proxy_pass http://127.0.0.1:8081;
```

Install and enable:

```bash
sudo cp deploy/vps/nginx-host/cashflow.gsautobilan.com.conf \
        /etc/nginx/sites-available/cashflow-summary.conf

sudo ln -sf /etc/nginx/sites-available/cashflow-summary.conf \
           /etc/nginx/sites-enabled/

sudo nginx -t
sudo systemctl reload nginx
```

Recheck gs-autobilan:

```bash
curl -s -o /dev/null -w "gs-autobilan after nginx reload: %{http_code}\n" http://127.0.0.1:8080/
```

Test Cash Flow routing:

```bash
curl -s -o /dev/null -w "host header test: %{http_code}\n" \
  -H "Host: cashflow.gsautobilan.com" \
  http://127.0.0.1/up

curl -s -o /dev/null -w "http subdomain: %{http_code}\n" http://cashflow.gsautobilan.com/up
curl -I http://cashflow.gsautobilan.com/up
```

Browser: **http://cashflow.gsautobilan.com/login**

**Checkpoint 5**

- [ ] `sudo nginx -t` succeeds
- [ ] Subdomain `/up` → 200
- [ ] gs-autobilan on `:8080` still OK

---

## Phase 6 — HTTPS (TLS)

Back up nginx **before** Certbot:

```bash
sudo cp -a /etc/nginx /etc/nginx.backup.$(date +%Y%m%d-%H%M%S)
sudo certbot --nginx -d cashflow.gsautobilan.com
```

Choose **redirect HTTP → HTTPS** when prompted.

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo certbot renew --dry-run

cd /var/www/cashflow-summary
grep '^APP_URL=' deploy/env/production.env
./deploy/compose-production.sh up -d app horizon

curl -s -o /dev/null -w "https subdomain: %{http_code}\n" https://cashflow.gsautobilan.com/up
curl -s -o /dev/null -w "gs-autobilan after TLS: %{http_code}\n" http://127.0.0.1:8080/
```

Browser: **https://cashflow.gsautobilan.com/login**

**Done when:** HTTPS works and the login page opens successfully with CSS.

**Checkpoint 6**

- [ ] HTTPS valid
- [ ] Login page loads
- [ ] gs-autobilan still OK

---

## Phase 7 — Hardening (before WhatsApp)

1. Log in at **https://cashflow.gsautobilan.com/login** as `owner` / `password`
2. **Change password immediately**
3. Enable **Owner 2FA** (Security settings)

```bash
cd /var/www/cashflow-summary

grep '^APP_DEBUG=' deploy/env/production.env
git status --short
sudo ufw status verbose
docker ps --format "table {{.Names}}\t{{.Ports}}"
./deploy/compose-production.sh ps
./deploy/smoke-test-production.sh
```

**Checkpoint 7**

- [ ] Default `owner` / `password` changed
- [ ] 2FA enabled
- [ ] `APP_DEBUG=false`
- [ ] `production.env` not tracked by Git
- [ ] Smoke tests pass

---

## Scheduled backups (Step 115)

Daily automated backups protect MySQL, import/export files, and `production.env`.

### One-time setup on the VPS

```bash
cd /var/www/cashflow-summary
git pull origin main

chmod +x deploy/backup-production.sh deploy/install-backup-cron.sh

# Optional off-site / retention overrides
cp deploy/env/backup.env.example deploy/env/backup.env
nano deploy/env/backup.env

sudo mkdir -p /var/backups/cashflow-summary
sudo chown "$USER:$USER" /var/backups/cashflow-summary
chmod 700 /var/backups/cashflow-summary

# Manual test
./deploy/backup-production.sh run
./deploy/backup-production.sh verify

# Install cron (02:00 daily backup, Sun 04:00 config snapshot)
./deploy/install-backup-cron.sh
```

Backups are stored under **`/var/backups/cashflow-summary/`**:

| Path | Contents |
|------|----------|
| `runs/YYYYMMDD-HHMMSS/` | Daily MySQL gzip, storage tarball, env snapshot, manifest (~28 days kept) |
| `weekly/YYYY-WWW/` | Optional — only when `RETENTION_WEEKLY>0` |
| `monthly/YYYY-MM/` | Optional — only when `RETENTION_MONTHLY>0` |
| `config/` | Host nginx + compose snapshots |

Log: **`/var/log/cashflow-summary-backup.log`**

Optional off-site copy — set `BACKUP_OFFSITE_RSYNC_DEST` in `deploy/env/backup.env`, then re-run a backup or wait for cron.

Restore procedure: Step **117** ([backup-monitoring.md](../../docs/operations/backup-monitoring.md)).

---

## Phase 8 — WhatsApp (Meta)

Configure only after **https://cashflow.gsautobilan.com** is fully working.

In **Owner → Settings → WhatsApp**: phones, Phone number ID, access token, webhook verify token.

Meta Developer Console:

| Field | Value |
|-------|--------|
| Callback URL | `https://cashflow.gsautobilan.com/api/webhooks/whatsapp` |
| Verify token | Same as in app settings |

Optional:

```bash
nano deploy/env/production.env
# WHATSAPP_APP_SECRET=your_meta_app_secret
chmod 600 deploy/env/production.env
./deploy/compose-production.sh up -d
./deploy/compose-production.sh logs --tail=100 horizon
```

Send a test message from WhatsApp settings in the app.

---

## Later deploys (updates only)

Back up **before** pulling code (or rely on nightly cron from Step 115):

```bash
cd /var/www/cashflow-summary
./deploy/backup-production.sh run
./deploy/backup-production.sh verify
```

Then deploy:

```bash
git pull origin main
./deploy/build-production.sh
./deploy/compose-production.sh up -d --force-recreate app nginx horizon scheduler
./deploy/compose-production.sh exec app php artisan config:clear
./deploy/compose-production.sh exec app php artisan route:clear
./deploy/compose-production.sh exec app php artisan view:clear
./deploy/compose-production.sh exec app php artisan migrate --force
./deploy/smoke-test-production.sh
./deploy/compose-production.sh exec app php deploy/diagnose-login-production.php
```

No `--seed` on updates. See [../volumes.md](../volumes.md) for storage volume backups.

---

## Rollback

```bash
cd /var/www/cashflow-summary
./deploy/compose-production.sh down

git checkout PREVIOUS_TAG_OR_COMMIT
./deploy/build-production.sh
./deploy/compose-production.sh up -d
```

Restore nginx if the main site broke:

```bash
sudo cp -a /etc/nginx.backup.YYYYMMDD-HHMMSS/* /etc/nginx/
sudo nginx -t && sudo systemctl reload nginx
```

**Never** run `./deploy/compose-production.sh down -v` unless you intend to **wipe** Cash Flow's database and files.

---

## Final verification checklist

Run after Phase 6 (and again after Phase 7):

```bash
dig +short cashflow.gsautobilan.com
curl -s -o /dev/null -w "8081/up: %{http_code}\n" http://127.0.0.1:8081/up
curl -s -o /dev/null -w "8081/login: %{http_code}\n" http://127.0.0.1:8081/login
curl -s -o /dev/null -w "host header test: %{http_code}\n" \
  -H "Host: cashflow.gsautobilan.com" \
  http://127.0.0.1/up
curl -s -o /dev/null -w "https subdomain: %{http_code}\n" https://cashflow.gsautobilan.com/up
curl -s -o /dev/null -w "gs-autobilan after TLS: %{http_code}\n" http://127.0.0.1:8080/
```

All Cash Flow checks should return **200**; gs-autobilan should not be `000`.

---

## Persistent data

| Volume | Contents |
|--------|----------|
| `cashflow-summary_mysql_data` | Database |
| `cashflow-summary_storage_data` | CSV imports, exports, logs |
| `cashflow-summary_redis_data` | Queue/cache AOF |

See [../volumes.md](../volumes.md).

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Bind for 127.0.0.1:8080 failed` | Set `HTTP_PORT=8081` in `production.env` |
| `502` on subdomain | `./deploy/compose-production.sh ps`; `logs app` |
| `502` but `:8081/up` works | Check `/etc/nginx/sites-enabled/cashflow-summary.conf` |
| Wrong site on subdomain | `proxy_pass` must be `127.0.0.1:8081` |
| Main site broken | Restore gs-autobilan nginx config or `/etc/nginx.backup.*` |
| Login without CSS | Re-run `./deploy/build-production.sh` |
| CSV import hangs | `./deploy/compose-production.sh logs horizon` |
| Session issues after HTTPS | `APP_URL=https://cashflow.gsautobilan.com` |
| `/login` or `/up` **500**, log `Uninitialized string offset` in `Request.php` | Docker nginx passed empty `X-Forwarded-Host` to PHP-FPM. Pull latest (removes that header) and rebuild nginx: `./deploy/build-production.sh && ./deploy/compose-production.sh up -d --force-recreate nginx app` |
| Login button spins forever (no error) | 1) Default seed user is **`owner`** / **`password`**. 2) Clear caches (`config:clear`, `route:clear`). 3) **`/login` 500 via browser but diagnose passes** — PHP-FPM strips Docker env by default; rebuild app image (`docker/php-fpm/zz-laravel-env.conf`) or interim `chmod 644 deploy/env/production.env`. 4) Host nginx `:443` needs `proxy_set_header X-Forwarded-Proto $scheme;` |
| Empty `APP_KEY` after Phase 3 | Do not continue; re-run `key:generate` and verify mount |
| Out of memory | `free -h`; upgrade VPS |

```bash
curl -s http://127.0.0.1:8081/up
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/
./deploy/compose-production.sh logs app --tail 50
./deploy/compose-production.sh exec app php artisan horizon:status
```

---

## Full first-time copy-paste

Run on **`ernesto@89.117.37.202`**. Edit passwords in `nano` before starting MySQL.

```bash
ssh ernesto@89.117.37.202

# Phase 0
dig +short cashflow.gsautobilan.com
sudo ss -tulpn | grep -E ':80|:443|:8080|:8081' || true
sudo ss -tulpn | grep ':8081' || echo "OK: 8081 is free"
docker ps --format "table {{.Names}}\t{{.Ports}}"
curl -s -o /dev/null -w "gs-autobilan baseline: %{http_code}\n" http://127.0.0.1:8080/
sudo nginx -t
sudo systemctl status nginx --no-pager
free -h && df -h /var/www
sudo ufw status verbose

# Phase 1
ls -la /var/www
sudo mkdir -p /var/www/cashflow-summary
sudo chown -R "$USER:$USER" /var/www/cashflow-summary
if [ ! -d /var/www/cashflow-summary/.git ]; then
  git clone https://github.com/ApondoErnest/Cash-flow-summary.git /var/www/cashflow-summary
fi
cd /var/www/cashflow-summary
git checkout main && git pull origin main
if [ -d deploy ]; then find deploy -type f -name "*.sh" -exec chmod +x {} \; else echo "ERROR: deploy missing" >&2; exit 1; fi
test -x deploy/compose-production.sh && test -x deploy/build-production.sh && echo "OK: deploy helpers ready"

# Phase 2 — first install only; edit passwords in nano
if [ ! -f deploy/env/production.env ]; then
  cp deploy/env/production.env.example deploy/env/production.env
fi
nano deploy/env/production.env
chmod 600 deploy/env/production.env
grep -E '^(DB_PASSWORD|DB_ROOT_PASSWORD|APP_URL|HTTP_PORT|APP_KEY)=' deploy/env/production.env

# Phase 3
./deploy/build-production.sh
./deploy/compose-production.sh run --rm --no-deps \
  -v ./deploy/env/production.env:/var/www/html/.env \
  app php artisan key:generate
grep '^APP_KEY=' deploy/env/production.env
if ! grep -q '^APP_KEY=base64:' deploy/env/production.env; then echo "ERROR: APP_KEY missing" >&2; exit 1; fi

# Phase 4
./deploy/compose-production.sh up -d mysql redis
until ./deploy/compose-production.sh ps | grep -i "mysql" | grep -q "healthy"; do
  echo "Waiting for MySQL..."
  sleep 5
done
./deploy/compose-production.sh run --rm app php artisan migrate --force --seed
./deploy/compose-production.sh up -d
curl -s -o /dev/null -w "8081/up: %{http_code}\n" http://127.0.0.1:8081/up
curl -s -o /dev/null -w "gs-autobilan: %{http_code}\n" http://127.0.0.1:8080/
./deploy/smoke-test-production.sh

# Phase 5
grep -E 'server_name|proxy_pass' deploy/vps/nginx-host/cashflow.gsautobilan.com.conf
sudo cp deploy/vps/nginx-host/cashflow.gsautobilan.com.conf /etc/nginx/sites-available/cashflow-summary.conf
sudo ln -sf /etc/nginx/sites-available/cashflow-summary.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
curl -s -o /dev/null -w "gs-autobilan after reload: %{http_code}\n" http://127.0.0.1:8080/
curl -s -o /dev/null -w "host header: %{http_code}\n" -H "Host: cashflow.gsautobilan.com" http://127.0.0.1/up
curl -s -o /dev/null -w "subdomain: %{http_code}\n" http://cashflow.gsautobilan.com/up

# Phase 6
sudo cp -a /etc/nginx /etc/nginx.backup.$(date +%Y%m%d-%H%M%S)
sudo certbot --nginx -d cashflow.gsautobilan.com
sudo nginx -t && sudo systemctl reload nginx
sudo certbot renew --dry-run
./deploy/compose-production.sh up -d app horizon
curl -s -o /dev/null -w "https: %{http_code}\n" https://cashflow.gsautobilan.com/up
curl -s -o /dev/null -w "gs-autobilan after TLS: %{http_code}\n" http://127.0.0.1:8080/
```

Then: **Phase 7** (change password, 2FA) → **Phase 8** (WhatsApp).

---

## Related docs

- [../README.md](../README.md) — local Docker runbook
- [PROVISION.md](PROVISION.md) — generic VPS bootstrap
- [../volumes.md](../volumes.md) — backups and volumes
- [../../docs/operations/deployment.md](../../docs/operations/deployment.md) — deployment overview
