# Software Requirements Specification

[← Documentation hub](../README.md) | [implementation-sequence.md](../implementation-sequence.md) | [business-rules.md](business-rules.md)

Requirements use `REQ-xxx` (functional) and `NFR-xxx` (non-functional).

**Delivery order:** [implementation-sequence.md](../implementation-sequence.md) **Steps 1–120** (one step at a time). **Phase** column = step group rollup. **Step wins over Phase/Sprint** when they conflict.

---

## 1. Roles and access

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-001 | Exactly three roles: Owner, Center Manager, Cashier | Must | 4–5 | S1 |
| REQ-002 | Owner has no assigned center (`center_id` null) | Must | 4–5 | S1 |
| REQ-003 | Manager and Cashier have exactly one center | Must | 4–5 | S1 |
| REQ-004 | Manager/Cashier never select center in UI | Must | 5 | S1 |
| REQ-005 | Cross-center access blocked on all routes, exports, downloads | Must | 5 | S1 |
| REQ-006 | Only Owner creates users | Must | 8 | S3 |
| REQ-007 | Only Owner approves financial revisions | Must | 11 | S4 |
| REQ-008 | Owner 2FA available | Must | 5, 8 | S3 |
| REQ-009 | Temporary password forces change on first login | Must | 5, 8 | S3 |

---

## 2. Centers and organization

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-020 | Owner CRUD centers | Must | 8 | S3 |
| REQ-021 | Per-center operating calendar and exceptions | Should | 8 | S3 |
| REQ-022 | Centers with records deactivated not deleted | Must | 4, 8 | S3 |
| REQ-023 | Per-center submission deadline | Should | 8 | S3 |
| REQ-024 | Owner active center in session after Center Selection | Must | 8 | S3 |
| REQ-024a | Owner login redirects to Center Selection when centers exist | Must | 8 | S3 |
| REQ-024b | No All Centers operational option | Must | 8 | S3 |
| REQ-024c | Operational routes require active center; redirect if missing | Must | 8 | S3 |
| REQ-024d | Active center shown in header with switch dropdown | Must | 8 | S3 |
| REQ-024e | CSV import uses active center automatically | Must | 11 | S4 |
| REQ-024f | Queue jobs use import.center_id not Owner session | Must | 10 | S5 |
| REQ-024g | Manage Centers has Open Center action | Must | 8 | S3 |
| REQ-024h | Inactive active center cleared from session | Must | 8 | S3 |
| REQ-024i | EnsureOwnerActiveCenter middleware on operational routes | Must | 5, 8 | S3 |
| REQ-024j | Manager/Cashier never see Owner center dropdown | Must | 5 | S1 |

---

## 3. CSV — file level

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-030 | UTF-8 CSV with BOM, semicolon delimiter | Must | 7 | S2 |
| REQ-031 | Auto-detect FR/EN; reject mixed-language headers | Must | 7 | S2 |
| REQ-032 | Support Regitration/Registration spelling variants | Must | 6–7 | S2 |
| REQ-033 | Reject unknown required headers | Must | 7 | S2 |
| REQ-034 | Extract footer count, HT, VAT, TTC | Must | 7 | S2 |
| REQ-035 | Private file storage | Must | 7 | S2 |
| REQ-036 | File hash per center; detect exact file re-upload | Must | 7 | S2 |
| REQ-037 | Import modes: operational, historical, correction | Must | 11 | S4 |
| REQ-038 | Only Owner approves new header aliases | Must | 8 | S3 |
| REQ-039 | Cashier cannot change column mappings | Must | 11–13 | S4 |

---

## 4. CSV — verification flow

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-040 | 3-step flow: Select → Verify → Import/Reject | Must | 11–13 | S4 |
| REQ-041 | Verify creates `import_verification` with token | Must | 7 | S2 |
| REQ-042 | No permanent financial data until Import | Must | 7 | S2 |
| REQ-043 | Reject deletes temp file and invalidates token | Must | 7 | S2 |
| REQ-044 | Verification summary shows footer totals prominently | Must | 11–13 | S4 |
| REQ-045 | Import button disabled when validation fails | Must | 11–13 | S4 |
| REQ-046 | Import: loading, disabled, double-click protection | Must | 11–13 | S4 |
| REQ-047 | Abandoned verifications expire and cleanup | Must | 7 | S2 |
| REQ-048 | Historical mode: WhatsApp off by default; opt-in checkbox | Must | 11, 15 | S4 |
| REQ-049 | Shared `CsvVerificationCard` for all roles | Must | 11 | S4 |

---

## 5. CSV — row level

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-050 | Parse ten canonical fields | Must | 7 | S2 |
| REQ-051 | Preserve source row number and raw values | Must | 10 | S5 |
| REQ-052 | Dash/empty completion → unfinished (not rejected) | Must | 7 | S2 |
| REQ-053 | Zero HT/VAT/TTC valid per BR-005 | Must | 7 | S2 |
| REQ-053a | Negative amounts → invalid row | Must | 7 | S2 |
| REQ-054 | Validate HT + VAT = TTC per row | Must | 7 | S2 |
| REQ-055 | Reconcile parsed totals with footer | Must | 7 | S2 |
| REQ-056 | Invalid rows excluded from activation; preserved in errors | Must | 7, 10 | S2 |
| REQ-057 | Stream-parse large files | Should | 7 | S2 |
| REQ-057a | Commit large files via queued/chunked pipeline (≥10k rows) | Should | 10 | S5 |

---

## 6. Duplicate detection

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-060 | Exact duplicate = 10 fields per `field_specific_v1` | Must | 7, 10 | S2 |
| REQ-061 | Deterministic exact_canonical_hash | Must | 7, 10 | S2 |
| REQ-062 | Duplicates within file detected | Must | 7 | S2 |
| REQ-063 | Historical duplicates against master ledger | Must | 7, 10 | S2 |
| REQ-064 | No duplicate inserted as new master | Must | 10 | S5 |
| REQ-065 | Duplicate occurrences in import_rows with master link | Must | 10 | S5 |
| REQ-066 | DB unique (center, policy_version, exact_hash) | Must | 9–10 | S5 |
| REQ-067 | Similarity fingerprint; probable duplicates informational | Must | 7 | S2 |
| REQ-068 | Probable duplicates visible to all roles | Must | 11–13 | S4 |
| REQ-069 | raw_row_checksum stored | Must | 7, 10 | S2 |

---

## 7. Daily versioning

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-070 | Per date: New, Unchanged, Revision required, etc. | Must | 10 | S5 |
| REQ-071 | Changed day → proposed version; Owner approves (backend Phase 10, UI Phase 11) | Must | 10–11 | S4 |
| REQ-072 | Only active snapshots affect current reports | Must | 10, 14 | S5 |
| REQ-073 | Manager submits correction; cannot activate | Must | 12 | S6 |

---

## 8. Dashboards and reports

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-080 | Compact Owner dashboard for **active center** per ux-overview | Must | 11 | S4 |
| REQ-081 | Compact Manager dashboard | Must | 12 | S6 |
| REQ-082 | Compact Cashier dashboard | Must | 13 | S7 |
| REQ-083 | Reports: daily, weekly, monthly, yearly, custom — **active center** for Owner | Must | 14 | S5 |
| REQ-085 | Exports: CSV, Excel, PDF, print — scoped to active center | Must | 14 | S5 |
| REQ-086 | Reports exclude temp/rejected/superseded data | Must | 14 | S5 |

---

## 9. WhatsApp

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-090 | Meta WhatsApp Cloud API | Must | 15 | S8 |
| REQ-091 | Scheduled summary cadences (daily / weekly / monthly / yearly) | Must | 15+ | S8+ |
| REQ-092 | No customer/plate lists in messages | Must | 15 | S8 |
| REQ-093 | Idempotency prevents duplicate sends | Must | 15 | S8 |
| REQ-094 | Failure does not roll back import | Must | 15 | S8 |
| REQ-095 | Owner number in admin settings | Must | 8, 15 | S3 |
| REQ-096 | Webhook verify token optional for outbound-only testing; required in production for delivery status webhooks | Must | 15 | S8 |
| REQ-097 | No per-import WhatsApp; summaries only at scheduled times | Must | 15+ | S8+ |
| REQ-098 | Per-center summary send time configurable by Owner | Must | 15+ | S8+ |
| REQ-099 | Scheduler dispatches due summaries (minute granularity) | Must | 15+ | S8+ |
| REQ-104 | Daily WhatsApp only on center operating days | Must | 15+ | S8+ |

---

## 10. Audit and security

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| REQ-100 | Audit events per plan.md §33 (shell Phase 8; complete Phase 16) | Must | 8, 16 | S3 |
| REQ-101 | Rejected CSV content not retained | Must | 7 | S2 |
| REQ-102 | Login rate limiting | Must | 5 | S1 |
| REQ-103 | Session timeout | Must | 5 | S1 |

---

## Non-functional

| ID | Requirement | Priority | Phase | Sprint |
|----|-------------|----------|-------|--------|
| NFR-001 | Midnight Finance design system | Must | 3 | S1 |
| NFR-002 | Heroicons through Flux UI; Lucide per-icon via `flux:icon` when needed | Must | 3 | S1 |
| NFR-003 | Livewire never holds full parsed rows | Must | 7, 11 | S2 |
| NFR-004 | Modular monolith structure | Must | 2 | S1 |
| NFR-005 | Pest test suite | Must | 17 | S8 |
| NFR-006 | Docker deploy from docs | Must | 19 | S8 |
| NFR-007 | Center isolation tests on every phase gate | Must | 5+ | All |
| NFR-008 | Import/verify jobs tolerate large CSVs (chunked I/O, ≥600s worker timeout) | Should | 7, 10 | S5 |

---

## Appendix — REQ/NFR by step group (phase rollup)

| Steps | Phase | Requirements |
|-------|-------|----------------|
| 13–18 | 2 | NFR-004 |
| 19–24 | 3 | NFR-001, NFR-002 |
| 25–31 | 4 | REQ-001, REQ-002, REQ-003, REQ-022 |
| 32–39 | 5 | REQ-001–005, REQ-024i, REQ-024j, REQ-008, REQ-009, REQ-102, REQ-103 |
| 40–42 | 6 | REQ-032 (seeds) |
| 43–51 | 7 | REQ-030–036, REQ-041–043, REQ-047, REQ-050–057, REQ-060–063, REQ-067, REQ-069, REQ-101, NFR-003, NFR-008 |
| 52–58 | 8 | REQ-006, REQ-008–009, REQ-020–024h, REQ-024g, REQ-038, REQ-095, REQ-100 (shell) |
| 59–64 | 9 | REQ-066 (schema) |
| 65–71 | 10 | REQ-024f, REQ-051, REQ-056, REQ-057a, REQ-060–066, REQ-069, REQ-070–072, REQ-071 (backend), NFR-008 |
| 72–79 | 11 | REQ-007, REQ-024e, REQ-037, REQ-039–049, REQ-068, REQ-071 (UI), REQ-080, NFR-003 (UI) |
| 80–85 | 12 | REQ-073, REQ-039, REQ-040–046, REQ-049, REQ-068, REQ-081 |
| 86–89 | 13 | REQ-039–046, REQ-049, REQ-068, REQ-082 |
| 90–93 | 14 | REQ-072, REQ-083, REQ-085, REQ-086 |
| 94–98 | 15 | REQ-048, REQ-090–096 |
| 99–102 | 16 | REQ-100 (complete) |
| 103–105 | 17 | NFR-005, NFR-007 |
| 109–111 | 19 | NFR-006 |
