# Deployment

[← Documentation hub](../README.md) | **Sprint 8**

---

## Rollout stages

1. Local development and UAT (S1–S7)
2. Local stabilisation (S8)
3. Dockerization
4. Production VPS

Provision: **[deploy/vps/PROVISION.md](../../deploy/vps/PROVISION.md)** (Step 112).

**Hostinger coexistence (gs-autobilan + subdomain):** **[deploy/vps/HOSTINGER-SUBDOMAIN.md](../../deploy/vps/HOSTINGER-SUBDOMAIN.md)** — full runbook for **`https://cashflow.gsautobilan.com`** on `89.117.37.202`.

---

## Docker Compose services

| Service | Image / role |
|---------|----------------|
| nginx | Reverse proxy, TLS termination |
| app | PHP-FPM + Laravel |
| mysql | MySQL 8 |
| redis | Redis 7 |
| horizon | `php artisan horizon` |
| scheduler | `php artisan schedule:work` — required for scheduled WhatsApp activity summaries (Steps 121–125) |

### Persistent volumes

See **[deploy/volumes.md](../../deploy/volumes.md)** (Step 110) for paths, backup outline, and verification.

| Volume | Docker name | Purpose |
|--------|-------------|---------|
| `mysql_data` | `cashflow-summary_mysql_data` | MySQL data directory |
| `storage_data` | `cashflow-summary_storage_data` | Laravel `storage/` (private imports, exports, logs) |
| `redis_data` | `cashflow-summary_redis_data` | Redis AOF (queue/cache persistence) |

Verify: `./deploy/verify-volumes.sh`

### Private (not exposed)

- MySQL port
- Redis port
- PHP-FPM

### Public

- HTTP → redirect HTTPS
- HTTPS (443)

---

## Environment (production)

Secrets via environment files on the server — never in Git:

| Environment | File (on server / dev machine) |
|-------------|--------------------------------|
| Local dev | `.env` (project root) |
| Docker | `deploy/env/docker.env` (gitignored) |
| VPS production | `deploy/env/production.env` (gitignored, on VPS only) |

Required secrets: `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD` (if set).

WhatsApp credentials are stored in **Owner admin settings** (encrypted), not in Git:

| Setting | Local / staging (Meta test number) | Production |
|---------|-----------------------------------|------------|
| Owner phone, phone number ID, access token | Required | Required |
| Webhook verify token | **Optional** — leave blank for outbound-only testing | **Required** — must match Meta webhook configuration |

Configure Meta webhook URL and verify token on the production VPS only. See [api/README.md](../api/README.md) and REQ-096.

---

## VPS requirements (minimum)

| Resource | Minimum |
|----------|---------|
| CPU | 2 vCPU |
| RAM | 4 GB |
| Disk | 40 GB SSD |
| OS | Ubuntu 22.04+ LTS |

### Hardening

- SSH key auth only
- Non-root deploy user in `docker` group
- UFW: 22, 80, 443 only
- Certbot or Caddy for TLS
- Unattended security updates

---

## Deploy procedure (outline)

```bash
git pull origin main
./deploy/build.sh
./deploy/compose.sh run --rm app php artisan migrate --force
./deploy/compose.sh up -d
./deploy/compose.sh exec app php artisan config:cache
./deploy/compose.sh exec app php artisan route:cache
./deploy/compose.sh exec app php artisan view:cache
```

Document exact commands in [deploy/README.md](../../deploy/README.md).

---

## Smoke tests (post-deploy)

Run the automated suite (Step 111 / AC #34):

```bash
./deploy/smoke-test.sh
```

Checks: all services up, queue env flags, `/up` and `/login` via nginx, in-container DB/Redis/config (`deploy/smoke-check-app.php`), Horizon running.

Manual checklist (staging / production extras):

- [ ] Owner login in browser
- [ ] Verify sample CSV end-to-end on staging
- [ ] *(Recommended)* Import a multi-thousand-row CSV; confirm result page leaves `processing` and masters are created
- [ ] WhatsApp test message (Meta test number or staging — phone number ID + access token; webhook optional)
- [ ] *(Production only)* Meta webhook verified; delivery status updates observed

---

## Rollback

1. `docker compose down`
2. Restore DB snapshot if migration ran
3. Checkout previous Git tag
4. `docker compose up -d`

---

## Related

- [backup-monitoring.md](backup-monitoring.md)
- [setup.md](setup.md)
