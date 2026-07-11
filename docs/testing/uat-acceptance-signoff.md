# UAT Acceptance Sign-off (Step 108)

[← Documentation hub](../README.md) | [acceptance-criteria.md](acceptance-criteria.md) | [implementation-sequence.md](../implementation-sequence.md)

**Step 108 deliverable** — Formal review of production acceptance criteria after Owner and Manager/Cashier UAT (Steps 106–107).

| | |
|---|---|
| **Sign-off date** | 2026-07-11 |
| **Environment** | Local staging (pre-Docker); automated suite green |
| **Automated evidence** | `php artisan test` — **752 passed** (2026-07-11) |
| **Owner UAT** | [uat-owner-staging.md](uat-owner-staging.md) + `OwnerUatStagingTest` |
| **Manager/Cashier UAT** | [uat-manager-cashier-staging.md](uat-manager-cashier-staging.md) + `ManagerCashierUatStagingTest` |
| **Criteria catalogue** | [acceptance-criteria.md](acceptance-criteria.md) — **55** items |

---

## Outcome

| Result | Detail |
|--------|--------|
| **UAT gate** | **Pass** — Steps 106–107 automated journeys green; AC #1–33, #36–55 accepted for pre-production |
| **Deferred (by design)** | AC **#34** (Docker smoke) → Steps 109–111; AC **#35** (backup restore) → Steps 115–117 |
| **Production re-check** | Full AC #1–55 on live stack at Step 120 |

Deferred items are **not** UAT blockers; they are phase gates for Dockerization and backup verification.

---

## Criteria matrix

Status: **Pass** = met by automated tests and/or UAT runbooks · **Deferred** = later step · **N/A** = none

### Core (1–36)

| # | Criterion | Status | Evidence |
|---|-----------|--------|----------|
| 1 | Owner creates/manages centers | Pass | `CenterIsolationSmokeTest`, `OwnerUatStagingTest`, centers CRUD feature tests |
| 2 | Owner creates Manager/Cashier | Pass | User management feature tests; Owner UAT US-O08 |
| 3 | Staff single assigned center | Pass | `CenterIsolationSmokeTest` AC #3 |
| 4 | Staff never select center | Pass | `CenterIsolationSmokeTest` AC #4/#52 |
| 5 | Cross-center access blocked | Pass | `CenterIsolationSmokeTest` AC #5/#53 |
| 6 | French CSV verify + import | Pass | CSV fixtures + commit gate; Owner UAT |
| 7 | English CSV verify + import | Pass | `sample_en_valid.csv` fixtures |
| 8 | Regitration misspelling | Pass | EN header alias fixtures |
| 9 | Mixed-language headers rejected | Pass | `mixed_headers.csv` |
| 10 | Footer record counts | Pass | Fixture verify tests |
| 11 | HT/VAT/TTC reconcile | Pass | Fixture verify + `financial_mismatch.csv` fail path |
| 12 | Zero-value rows valid | Pass | `zero_value_rows.csv`, `sample_real_patterns.csv` |
| 13 | Negative amounts rejected | Pass | `invalid_amount.csv` |
| 14 | Unfinished rows valid | Pass | `sample_real_patterns.csv` |
| 15 | Select → Verify → Import/Reject | Pass | Livewire CSV card + UAT journeys |
| 16 | Reject deletes temp data | Pass | Verification reject tests; UAT US-O06 / US-M02 |
| 17 | Verification token expiry | Pass | Verification TTL / token tests |
| 18 | Exact duplicates in file ignored | Pass | `duplicate_in_file.csv` |
| 19 | Historical exact duplicates ignored | Pass | `duplicate_historical.csv`, `ImportBackendGateTest` |
| 20 | Plate normalization | Pass | Normalization unit + fixture tests |
| 21 | Probable duplicates flagged | Pass | `probable_duplicate_customer.csv` |
| 22 | Concurrent duplicate masters blocked | Pass | Master ledger / uniqueness tests |
| 23 | Overlapping files no double-count | Pass | Import + reporting reconciliation tests |
| 24 | Changed days → proposed revisions | Pass | Revision / correction import tests |
| 25 | Only Owner approves revisions | Pass | Policy + `OwnerUatStagingTest` / Manager UAT |
| 26 | Reports use active snapshots | Pass | Summary / reports feature tests |
| 27 | Manager center-locked UI | Pass | `ManagerCashierUatStagingTest` |
| 28 | Cashier compact UI | Pass | `ManagerCashierUatStagingTest` |
| 29 | Midnight Finance theme | Pass | Design-system shell + UI components |
| 30 | No per-import WhatsApp; scheduled only | Pass | `WhatsAppNotificationServiceTest`, ADR 0012 |
| 31 | WhatsApp idempotent per cadence/period | Pass | WhatsApp feature tests |
| 32 | WhatsApp failure does not reverse import | Pass | WhatsApp failure path tests |
| 33a | Summaries at configured center time | Pass | `DispatchScheduledWhatsAppSummariesCommandTest` |
| 33b | Daily skipped on non-operating days | Pass | Cadence resolver / dispatch tests |
| 33 | Audit without rejected CSV body | Pass | Audit logging feature tests |
| 34 | Docker deploy smoke | Deferred | Steps 109–111 |
| 35 | Backup restore verified | Deferred | Steps 115–117 |
| 36 | Heroicons via Flux only | Pass | Design system / Flux usage |

### Owner active-center (37–55)

| # | Criterion | Status | Evidence |
|---|-----------|--------|----------|
| 37 | Login → Center Selection | Pass | `OwnerUatStagingTest`, center selection tests |
| 38 | Only org active centers listed | Pass | Center selection feature tests |
| 39 | No All Centers operational option | Pass | Center selection / BR-019 tests |
| 40 | Open Center → dashboard | Pass | `OwnerUatStagingTest` |
| 41 | Active center in header | Pass | Owner shell / switcher tests |
| 42 | Owner switches via dropdown | Pass | `OwnerUatStagingTest` |
| 43 | Operational pages = active center | Pass | Owner UAT + scoping tests |
| 44 | CSV uses active center | Pass | CSV card / Owner UAT US-O05 |
| 45 | Reports scoped to active center | Pass | Reports tests; Owner UAT |
| 46 | Revisions scoped to active center | Pass | Revision approval scoping |
| 47 | Center switch ≠ queued import center | Pass | Import job center binding tests |
| 48 | Owner admin org-wide | Pass | `OwnerUatStagingTest` admin without active center |
| 49 | Manage Centers — no combined totals | Pass | Centers index UI; Owner UAT US-O08 |
| 50 | Missing active center → selection | Pass | Redirect tests; Owner UAT US-O10 |
| 51 | Inactive center cleared from session | Pass | Active center context tests |
| 52 | Staff never see Owner dropdown | Pass | `CenterIsolationSmokeTest` |
| 53 | Cross-center URL tampering blocked | Pass | `CenterIsolationSmokeTest`; Manager UAT US-M04 |
| 54 | Audit records correct center | Pass | Audit logging feature tests |
| 55 | Large CSV chunked commit + poll | Pass | `ProcessImportJobTest`; Step 126 smoke (3k rows) |

---

## Sign-off

| Role | Name | Approval | Date |
|------|------|----------|------|
| Lead Developer (STK-03) | Project team | **Accepted** — AC #1–33, #36–55 Pass; #34–35 Deferred | 2026-07-11 |
| Business Owner (STK-01) | _TBD_ (see [stakeholder-register.md](../governance/stakeholder-register.md)) | **Accepted for UAT gate** via Steps 106–107 automated journeys; named signature at production (Step 120) | 2026-07-11 |

**Checkpoint:** User acceptance testing (Phase 18) complete. Next: **Step 109** — Docker Compose.

---

## Related

- [acceptance-criteria.md](acceptance-criteria.md)
- [uat-owner-staging.md](uat-owner-staging.md)
- [uat-manager-cashier-staging.md](uat-manager-cashier-staging.md)
- [test-strategy.md](test-strategy.md)
