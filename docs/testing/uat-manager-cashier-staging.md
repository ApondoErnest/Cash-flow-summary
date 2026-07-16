# Manager & Cashier UAT — Staging (Step 107)

[← Documentation hub](../README.md) | [implementation-sequence.md](../implementation-sequence.md) | [personas.md](../product/personas.md)

**Step 107 deliverable** — Center Manager and Cashier representative sessions on **local staging** (pre-Docker).

| | |
|---|---|
| **Audience** | Center Manager rep (STK-04), Cashier rep (STK-05) |
| **Duration** | ~60 minutes each (or one combined 90-minute session) |
| **Environment** | Same as [uat-owner-staging.md](uat-owner-staging.md) — local app, not production |
| **Automated gate** | `php artisan test tests/Feature/ManagerCashierUatStagingTest.php` |
| **Prerequisite** | Step 106 Owner UAT complete; Manager and Cashier test accounts created per center |

---

## Prerequisites

1. Stack running (see [uat-owner-staging.md](uat-owner-staging.md) § Prerequisites).
2. **Two centers** with one Manager and one Cashier each (Owner creates via Manage Users in Step 106 or before this session).
3. Force password change completed on first login for each rep account.
4. Sanitized CSV files under `tests/fixtures/csv/` (same catalogue as Owner UAT).

---

## Test files

| File | Manager session | Cashier session |
|------|-----------------|-----------------|
| `sample_fr_production_footer.csv` | Happy-path import | Happy-path import |
| `sample_real_patterns.csv` | Row pattern sanity check | Footer TTC visible in summary |
| `financial_mismatch.csv` | Verify **Failed** | Verify **Failed** |
| `invalid_amount.csv` | Hard-fail (negative amount) | Error report download on failed verification |

---

## Manager session checklist (US-M01 – US-M04)

| ID | Story | Steps | Pass? | Notes |
|----|-------|-------|-------|-------|
| US-M01 | Fixed center, no selector | Dashboard shows assigned center name; header has **Assigned center**, not **Switch center** | | AC 4, 27, 52 |
| US-M02 | Verify and import CSV | Import CSV → upload `sample_fr_production_footer.csv` → Verify → **Import** → result page success | | AC 15, 27 |
| US-M02 | Reject flow | Repeat with `sample_fr_valid.csv` → **Reject** → no import in history | | AC 16 |
| US-M03 | Submit correction | Select **Correction** mode → import changed day file → status **Awaiting Owner approval**; Revisions link on result page | | AC 24, 25 (partial) |
| US-M04 | Cross-center blocked | Append `?center_id=` for another center on Import CSV URL → **403** | | AC 5, 53 |
| — | Import history | Imports list shows only assigned center files | | AC 27 |
| — | Reports | Reports page scoped to assigned center | | AC 27 |

---

## Cashier session checklist (US-C01 – US-C03)

| ID | Story | Steps | Pass? | Notes |
|----|-------|-------|-------|-------|
| US-C01 | Compact dashboard | Dashboard shows today TTC, assigned center, **Import CSV** action; no Reports / Records / Revisions nav | | AC 28 |
| US-C02 | Verify and import | Import CSV → upload file → Verify → summary shows **footer TTC** total → **Import** → success | | AC 15, 28 |
| US-C02 | Reject flow | Verify file → **Reject** → returns to empty card | | AC 16 |
| US-C03 | No Owner center UI | Confirm no **Switch center** or **Active center** dropdown anywhere | | AC 52 |
| US-C04 | Cross-center blocked | Tampered `center_id` on Import CSV → **403** | | AC 5, 53 |
| — | Recent imports | Imports list shows own uploads only | | AC 28 |

*(Cashier correction mode is available but Owner approval still required — optional demo only.)*

---

## Session log

| Item | Manager rep | Cashier rep |
|------|-------------|-------------|
| Name | | |
| Center | | |
| Date | | |
| Facilitator | | |
| Defects found | | |
| Outcome | ☐ Pass / ☐ Rework | ☐ Pass / ☐ Rework |

**Combined outcome:** ☐ Proceed to Step 108 sign-off &nbsp; ☐ Rework required

---

## Related

- [user-stories.md](../product/user-stories.md) — US-M01–M04, US-C01–C03
- [uat-owner-staging.md](uat-owner-staging.md) — Owner session (Step 106)
- [acceptance-criteria.md](acceptance-criteria.md) — full AC list (Step 108)
