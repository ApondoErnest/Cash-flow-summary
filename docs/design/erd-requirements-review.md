# ERD Requirements Review

[← Documentation hub](../README.md) | [data-model.md](data-model.md) | [requirements.md](../product/requirements.md)

**Step 25 deliverable** — traceability review of [data-model.md](data-model.md) against SRS, business rules, and plan.md §21.

| | |
|---|---|
| **Review date** | 2026-07-01 |
| **Reviewer** | Lead developer (implementation sequence Step 25) |
| **Outcome** | **Approved with documented amendments** |
| **Schema version** | data-model.md (post-review) |

---

## 1. Scope

This review covers:

- Entity coverage for **REQ-001 through REQ-103** and **NFR-004** (schema-relevant items only)
- Business rules **BR-001 through BR-022** that imply persistence
- Core constraints, indexes, and migration wave ordering in [data-model.md](data-model.md)
- Gaps between the published ERD diagram and the full table catalogue

Out of scope: migration PHP, Eloquent models, and application-layer enforcement (Steps 26–31+).

---

## 2. Summary outcome

| Area | Result |
|------|--------|
| Wave 1 administration (organizations → audit_logs) | **Pass** — supports REQ-001, REQ-002, REQ-003, REQ-022 |
| Owner active-center session model | **Pass** — session keys documented; not in ERD (by design) |
| CSV verification & import pipeline | **Pass** — `import_verifications`, `imports`, `import_rows`, `import_errors` |
| Duplicate detection & master ledger | **Pass** — hashes, unique constraints per REQ-060–066 |
| Daily versioning & reports | **Pass** — `daily_versions`, `active_daily_snapshots`, summaries |
| WhatsApp & notifications | **Pass** — idempotency, webhook events |
| Audit | **Pass** — `audit_logs`; rejected CSV content excluded (REQ-101) |
| Organization / WhatsApp settings | **Amended** — `organization_settings` table added (see §5) |
| ERD diagram completeness | **Noted** — diagram shows core financial graph; admin tables in appendix |

**Verdict:** Schema is fit for Wave 1 migrations (Steps 26–31). No blocking gaps remain after amendments in this review.

---

## 3. Wave 1 traceability (Steps 25–31)

| Requirement | Data-model support | Notes |
|-------------|-------------------|-------|
| REQ-001 Three roles | `roles`, Spatie permission tables; seed `owner`, `center_manager`, `cashier` | Step 29–31 |
| REQ-002 Owner `center_id` null | `users.center_id` nullable + check documented | Enforced in app + optional DB check Step 28 |
| REQ-003 Manager/Cashier one center | `users.center_id` FK required when role assigned | Spatie role + non-null center |
| REQ-022 Centers deactivated not deleted | `centers.is_active` boolean | No hard delete on centers with records |
| REQ-008 Owner 2FA | `users.two_factor_secret`, `two_factor_recovery_codes` | Step 28 |
| REQ-009 Force password change | `users.must_change_password` | Step 28 |
| REQ-021 Operating calendar | `center_operating_calendars`, `center_calendar_exceptions` | BR-009, BR-010 |
| REQ-023 Submission deadline | `centers.submission_deadline` | BR-008 |
| REQ-100 Audit shell | `audit_logs` | Full event list in Step 102+ |
| REQ-102 / REQ-103 | Laravel `sessions` table (Wave 1) | Rate limit in app layer Step 33 |

---

## 4. Full schema traceability by domain

### 4.1 Roles and access

| Requirement | Entity / constraint |
|-------------|---------------------|
| REQ-004, REQ-005, REQ-024j | No center picker column — scope via `users.center_id` + session `active_center_id` |
| REQ-024–024i | Session keys in data-model § Owner active-center session |
| REQ-024f, BR-022 | `imports.center_id`, `import_verifications.center_id` — jobs read DB not session |

### 4.2 CSV ingestion

| Requirement | Entity / constraint |
|-------------|---------------------|
| REQ-030–036 | `imports` / `import_verifications`: encoding, delimiter, `file_hash`, private paths |
| REQ-036 | Unique `(center_id, file_hash)` on `imports` |
| REQ-037 | `import_mode` enum: operational, historical, correction |
| REQ-038 | `header_aliases.created_by`; Owner approval in app layer |
| REQ-041–047 | `import_verifications`: token, status, `expires_at`, JSON summaries |
| REQ-048 | `import_verifications.notify_owner` boolean |
| REQ-101 | Rejected verifications: temp path deleted; no row in `imports` / masters |

### 4.3 Parsing, normalization, duplicates

| Requirement | Entity / constraint |
|-------------|---------------------|
| REQ-050–057 | `import_rows.original_values`, `canonical_values`, `validation_errors` |
| REQ-060–069 | `exact_canonical_hash`, `similarity_fingerprint`, `raw_row_checksum` on rows/masters |
| REQ-066 | Unique `(center_id, normalization_policy_version, exact_canonical_hash)` on masters |
| REQ-067–068 | `duplicate_type`, `duplicate_summary` JSON; probable duplicates informational |

### 4.4 Versioning and reports

| Requirement | Entity / constraint |
|-------------|---------------------|
| REQ-070–073 | `import_day_comparisons`, `daily_versions.status`, `approved_by` |
| REQ-072, REQ-086 | `active_daily_snapshots` unique per `(center_id, business_date)` |
| REQ-083, REQ-085 | `export_requests`, `daily_summaries` (regeneratable cache) |
| REQ-007 | `daily_versions.approved_by`, `rejected_reason` |

### 4.5 WhatsApp and settings

| Requirement | Entity / constraint |
|-------------|---------------------|
| REQ-090–094 | `whatsapp_messages`, `whatsapp_webhook_events`, `idempotency_key` unique |
| REQ-095, BR-018 | **`organization_settings`** — Owner phone, API credentials (encrypted) |
| REQ-048 | `import_verifications.notify_owner` |

### 4.6 Audit and security

| Requirement | Entity / constraint |
|-------------|---------------------|
| REQ-100 | `audit_logs` — no rejected CSV body in `old_values`/`new_values` |
| REQ-101 | Verification reject → no permanent import rows |

---

## 5. Gaps found and resolutions

| ID | Gap | Resolution | Migration step |
|----|-----|------------|----------------|
| G-01 | No table for org/WhatsApp settings (REQ-095) | Added **`organization_settings`** to data-model.md | Step 30 (with audit_logs) |
| G-02 | ERD diagram omits Wave 1 admin entities | Added **Administrative ERD** appendix in data-model.md | Documentation only |
| G-03 | `daily_summaries` / `summary_breakdowns` columns not fully specified | Accepted — detail deferred to Step 64 implementation | Wave 3 |
| G-04 | Spatie permission table names not repeated in ERD | Referenced in Wave 1 list; standard Spatie schema | Step 29 |
| G-05 | `users.email` in Laravel default vs username login | data-model uses `username` unique; migration Step 28 replaces email-as-login | Step 28 |

No open **blocking** gaps.

---

## 6. Business rules cross-check

| Rule | Schema support |
|------|----------------|
| BR-004 Negative amounts | `import_rows.validation_errors`; invalid rows excluded |
| BR-005 Zero TTC valid | `master_cash_flow_records.financial_status` enum |
| BR-008 Submission deadline | `centers.submission_deadline` |
| BR-009–010 Calendar | `center_operating_calendars`, `center_calendar_exceptions` |
| BR-014 Historical WhatsApp | `import_verifications.notify_owner` |
| BR-016 field_specific_v1 | `normalization_policy_version` on rows/masters |
| BR-019–021 Owner active center | Session storage documented |
| BR-022 Queue center scope | `imports.center_id` denormalized on jobs |

---

## 7. Index and constraint verification

All constraints listed in data-model § Core constraints summary were checked against requirements:

- Owner vs Manager/Cashier center binding — **OK**
- Master uniqueness — **OK** (REQ-066)
- Active snapshot uniqueness — **OK** (REQ-072)
- File hash per center — **OK** (REQ-036)
- Verification token UUID — **OK** (REQ-041)
- WhatsApp idempotency — **OK** (REQ-093)

Indexing strategy covers report queries, duplicate lookup, cleanup jobs, and audit — **OK**.

---

## 8. Migration order confirmation

Wave order in data-model.md matches dependency rules in implementation-sequence.md:

1. organizations → centers → users → permissions → calendars → audit_logs → **organization_settings**
2. csv_format_versions → header_aliases → import_verifications
3. imports → masters → rows → versions → snapshots → supporting tables

**Approved** for Steps 26–31 execution.

---

## 9. Sign-off

| Role | Status | Date |
|------|--------|------|
| Schema review (Step 25) | Complete | 2026-07-01 |
| Business Owner UAT | Pending | — |

**Next step:** [Step 26](../implementation-sequence.md) — Migration: organizations

---

## Related

| Document | Purpose |
|----------|---------|
| [data-model.md](data-model.md) | Authoritative schema (updated post-review) |
| [requirements.md](../product/requirements.md) | REQ-xxx source |
| [business-rules.md](../product/business-rules.md) | BR-xxx source |
| [import-statuses.md](import-statuses.md) | Enum values for status columns |
