# Owner UAT — Staging (Step 106)

[← Documentation hub](../README.md) | [implementation-sequence.md](../implementation-sequence.md) | [user-stories.md](../product/user-stories.md)

**Step 106 deliverable** — Business Owner acceptance session on **local staging** (pre-Docker; Docker staging follows Steps 109–111).

| | |
|---|---|
| **Audience** | Business Owner (STK-01) |
| **Duration** | ~90 minutes |
| **Environment** | Local app (`php artisan serve` + Horizon) or shared dev host — **not production** |
| **Automated gate** | `php artisan test tests/Feature/OwnerUatStagingTest.php` |
| **Full sign-off** | Step 108 — [acceptance-criteria.md](acceptance-criteria.md) |

---

## Prerequisites

1. Application built and migrated:

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
npm ci && npm run build
php artisan horizon    # separate terminal
php artisan serve
```

2. Owner credentials from `.env` seed (`SEED_OWNER_USERNAME` / `SEED_OWNER_PASSWORD`; default `owner` / `password` — change locally).
3. Owner completes **2FA enrollment** on first login (US-O01).
4. Sanitized CSV files under `tests/fixtures/csv/` (see below). Optional: private real-format sample in team storage (not in Git) for parity check per [test-strategy.md](test-strategy.md).

---

## Test files

| File | Use in session |
|------|----------------|
| `sample_fr_valid.csv` | Happy-path French import |
| `sample_fr_production_footer.csv` | Production footer layout |
| `sample_en_valid.csv` | English headers |
| `sample_real_patterns.csv` | B1/CV/zero/unfinished/plate variants |
| `financial_mismatch.csv` | Verify **Failed** — footer does not reconcile |
| `missing_footer.csv` | Verify **Failed** — missing footer |

Do **not** use production customer data in shared environments.

---

## Owner session checklist

Mark **Pass / Fail / N/A** and note defects in the session log (bottom).

### Authentication and navigation

| ID | Story | Steps | Pass? | Notes |
|----|-------|-------|-------|-------|
| US-O01 | Login with 2FA | Log in as Owner; complete 2FA if prompted | | AC 37 (partial) |
| US-O02 | Center Selection after login | Confirm redirect to Center Selection when no active center | | AC 37, 50 |
| US-O11 | First center empty state | If no centers exist, create first center from selection empty state | | AC 1 |
| US-O03 | Open center → dashboard | Select center; dashboard title includes center name | | AC 40, 41 |
| US-O04 | Switch center from header | Switch via header dropdown; dashboard reloads for new center | | AC 42, 43 |
| US-O10 | No operational pages without active center | Clear session / new browser; confirm operational URLs redirect to Center Selection | | AC 50 |

### Administration (org-wide, no active center required)

| ID | Story | Steps | Pass? | Notes |
|----|-------|-------|-------|-------|
| US-O08 | Manage centers | Open Manage Centers; confirm **no combined financial totals** across centers | | AC 48, 49 |
| — | Manage users | Create Manager and Cashier accounts for two centers | | AC 2 |
| US-O09 | WhatsApp settings | Open Settings → WhatsApp; save phone number ID + access token (webhook token optional on staging) | | REQ-096 |

### Operational (active center required)

| ID | Story | Steps | Pass? | Notes |
|----|-------|-------|-------|-------|
| US-O05 | Import for active center | Import CSV page shows “Importing for: {center}”; no center picker | | AC 44 |
| US-O06 | Reject verified file | Verify `sample_fr_valid.csv` → **Reject** → confirm no import record | | AC 15, 16 |
| — | Import happy path | Verify + **Import** `sample_fr_production_footer.csv` → result page shows success | | AC 6, 10, 11, 15 |
| US-O07 | Approve revision | After manager correction import, open Revisions → approve proposed version | | AC 24, 25, 46 |
| — | Reports scoped | Reports page totals match active center only after switch | | AC 45 |
| US-X02 | URL tampering | *(Optional)* Append `?center_id=` for another center on dashboard → 403 | | AC 53 |

---

## WhatsApp (staging)

| Check | Expected |
|-------|----------|
| Outbound template after import | Message queued/sent when credentials configured |
| Meta test number | Webhook verify token may be blank; delivery webhooks optional |
| Import not reversed on send failure | Import remains completed if API fails |

See [api/README.md](../api/README.md).

---

## Session log

| Item | Value |
|------|-------|
| Date | |
| Owner name | |
| Facilitator | |
| Environment URL | |
| Browser | |
| Defects found | |
| Blockers for Step 107 | |

**Owner session outcome:** ☐ Proceed to Manager/Cashier UAT (Step 107) &nbsp; ☐ Rework required

*(Formal AC 1–54 sign-off remains Step 108.)*

---

## Related

- [user-stories.md](../product/user-stories.md) — US-O01–O11
- [acceptance-criteria.md](acceptance-criteria.md) — full checklist
- [test-strategy.md](test-strategy.md) — UAT phase overview
- [deployment.md](../operations/deployment.md) — staging before Docker
