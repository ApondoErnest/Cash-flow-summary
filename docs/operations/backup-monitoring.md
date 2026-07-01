# Backup and Monitoring

[← Documentation hub](../README.md) | **Sprint 8**

---

## Backup scope

| Asset | Method | Frequency |
|-------|--------|-----------|
| MySQL database | mysqldump / snapshot | Daily |
| Accepted CSV files | `storage/app/private/imports/` sync | Daily |
| Exports | `storage/app/exports/` | Daily |
| Application `.env` | Encrypted off-server copy | On change |
| Docker compose + nginx config | Git + server backup | Weekly |

### Retention

| Tier | Retention |
|------|-----------|
| Daily | 7 days |
| Weekly full | 4 weeks |
| Monthly | 12 months |

Off-server backups encrypted at rest.

---

## Restore test

Quarterly restore to staging:

1. Restore MySQL dump
2. Restore file storage
3. Run smoke tests
4. Document duration and issues

---

## Monitoring

| Signal | Alert if |
|--------|----------|
| Application uptime | Down > 2 min |
| Failed imports | > 3 in 1 hour |
| Verification failures | Spike vs baseline |
| Reconciliation failures | Any in production |
| Pending revisions | > N days (configurable) |
| Queue backlog | > 100 jobs 15 min |
| WhatsApp failures | Any failed after max retries |
| MySQL disk | > 80% |
| Redis memory | > 80% maxmemory |
| Server disk | > 85% |
| Backup job | Failed |
| TLS certificate | < 14 days to expiry |

Alert channel: Owner WhatsApp or email to deploy admin (operational, not app email feature).

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
| Database backup | 02:00 daily |
| File backup | 03:00 daily |
| Certificate check | Weekly |

---

## Health endpoint

`GET /health` → `{ "status": "ok", "database": "ok", "redis": "ok" }`

Used by Docker healthcheck and external uptime monitor.

---

## Related

- [deployment.md](deployment.md)
- [risk-register.md](../governance/risk-register.md)
