# Test Strategy

[← Documentation hub](../README.md) | [implementation-sequence.md](../implementation-sequence.md) | [requirements.md](../product/requirements.md)

**Framework:** Pest (PHPUnit)

---

## Tests by implementation step

| Steps | Test focus | Fixtures / targets |
|-------|------------|-------------------|
| 32–39 | Auth, center isolation smoke | Cross-center URL, policies |
| 43–51 | Verification backend | All CSV fixtures below; AC #6–21 |
| 65–71 | Import commit, duplicates, versions | duplicate_historical, concurrency; AC #18–19, #22–23 |
| 72–89 | CSV UI, role flows | Verify/Import/Reject; AC #15–16 |
| 90–93 | Reports reconcile | AC #26, #45 |
| 94–98 | WhatsApp idempotency; outbound send without webhook token (Meta test number) | AC #30–32; REQ-096 |
| 103–105 | Full suite + CI | All fixtures; NFR-005 |
| 106–108 | UAT | Real-format samples (private storage) |

---

## Test layers

| Layer | Scope |
|-------|-------|
| Unit | Normalization, hashing, footer parsing, amount/date parsers |
| Feature | HTTP/Livewire flows, policies, center isolation |
| Integration | DB constraints, transactions, queue jobs |
| E2E (optional) | Browser tests for critical CSV flow |

---

## Unit test targets

- Amount parsing (space separators, zero rows, negatives invalid)
- Date parsing (FR/EN formats, unfinished dash)
- Footer detection and extraction
- HT + VAT = TTC validation
- `field_specific_v1` plate and customer normalization
- `exact_canonical_hash` determinism
- `similarity_fingerprint` classification
- Verification token expiry logic
- Daily dataset hash
- WhatsApp idempotency key generation

---

## CSV fixture catalogue

Store under `tests/fixtures/csv/` — **sanitized only**. Primary gate: **Steps 43–51** (verify); import commit: **Steps 65–71**.

| Fixture | Purpose |
|---------|---------|
| `sample_fr_valid.csv` | French headers, footer match |
| `sample_fr_production_footer.csv` | Production footer layout (`;Nombre total d'inspections :;…;;;;Total :;…`) |
| `sample_en_valid.csv` | English headers incl. Regitration typo |
| `sample_real_patterns.csv` | Anonymized subset: B1/CV/zero/unfinished/plate variants from UAT sample |
| `duplicate_in_file.csv` | Same row twice |
| `duplicate_historical.csv` | Matches seeded master |
| `all_duplicate.csv` | No new masters |
| `missing_footer.csv` | Footer error |
| `missing_header.csv` | Block processing |
| `invalid_date.csv` | Row errors |
| `invalid_amount.csv` | Negative amounts |
| `financial_mismatch.csv` | HT+VAT≠TTC |
| `zero_value_rows.csv` | Valid zeros incl. CV |
| `mixed_headers.csv` | Mixed FR/EN — must reject |
| `probable_duplicate_customer.csv` | Similarity match, not exact |
| `multi_day_period.csv` | Multiple registration dates |

Real samples remain in private storage; parity tests in UAT.

---

## Feature tests — interface

- Compact dashboards render role-appropriate stats
- Owner sees **active center** on import; Manager/Cashier do not; Owner has no center picker on CSV card
- Owner Center Selection and active-center redirect tests
- Verify button triggers verification record
- Import disabled when validation fails
- Import double-click sends once
- Reject returns to empty state

---

## Feature tests — security

- Cross-center import URL → 403
- Tampered `center_id` in request → 403
- Unauthorized file download → 403
- Cashier cannot approve revision
- Reused verification token → error
- Import after Reject → error
- Expired token → error

---

## Feature tests — financial

- Footer reconciliation pass/fail
- Duplicate exclusion from new master count
- Active snapshot reporting excludes superseded
- Concurrent import duplicate → one master (integration)

---

## CI pipeline (Step 18+)

Workflow: [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)

On push/PR to `main`:

1. `composer install`
2. `cp .env.example .env` + `php artisan key:generate`
3. `php artisan test` (Pest; sqlite `:memory:` per `phpunit.xml`; manual fixture generator excluded — run `./vendor/bin/pest tests/Feature/_GenerateCsvFixturesTest.php` when catalogue changes)
4. `npm ci` + `npm run build`

---

## Coverage goals

| Area | Target |
|------|--------|
| Normalization / hash | 100% critical paths |
| Center isolation policies | 100% |
| CSV verify pipeline | All fixtures |
| Overall | ≥ 80% services |

---

## UAT (S8)

| Step | Audience | Runbook |
|------|----------|---------|
| 106 | Owner | [uat-owner-staging.md](uat-owner-staging.md) |
| 107 | Manager / Cashier reps | [uat-manager-cashier-staging.md](uat-manager-cashier-staging.md) |
| 108 | Owner sign-off | [acceptance-criteria.md](acceptance-criteria.md) |

Owner + Manager + Cashier reps execute [user-stories.md](../product/user-stories.md) on staging with sanitized real-format files.

---

## Related

- [acceptance-criteria.md](acceptance-criteria.md)
