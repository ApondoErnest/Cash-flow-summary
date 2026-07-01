# Deployment

[← Documentation hub](../README.md) | **Sprint 8**

---

## Rollout stages

1. Local development and UAT (S1–S7)
2. Local stabilisation (S8)
3. Dockerization
4. Production VPS

---

## Docker Compose services

| Service | Image / role |
|---------|----------------|
| nginx | Reverse proxy, TLS termination |
| app | PHP-FPM + Laravel |
| mysql | MySQL 8 |
| redis | Redis 7 |
| horizon | `php artisan horizon` |
| scheduler | `php artisan schedule:work` |

### Persistent volumes

- `mysql_data`
- `storage_data` (private imports, exports)
- `redis_data` (optional persistence)

### Private (not exposed)

- MySQL port
- Redis port
- PHP-FPM

### Public

- HTTP → redirect HTTPS
- HTTPS (443)

---

## Environment (production)

Secrets via `.env` on server — never in Git:

- `APP_KEY`
- `DB_PASSWORD`
- `REDIS_PASSWORD` (if set)
- WhatsApp Cloud API token (or DB settings)
- Meta webhook verify token

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
docker compose pull
docker compose build app
docker compose run --rm app php artisan migrate --force
docker compose up -d
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

Document exact commands in `deploy/` when implemented.

---

## Smoke tests (post-deploy)

- [ ] Health endpoint returns OK
- [ ] Owner login
- [ ] Horizon dashboard accessible (protected)
- [ ] Verify sample CSV end-to-end on staging
- [ ] WhatsApp test message (staging number)

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
