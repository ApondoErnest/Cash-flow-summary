# Cash Flow Summary — Master Plan

**Version:** 2.0.0  
**Last updated:** 2026-07-01  
**Status:** Step 33 complete — Step 34 (Owner 2FA) next

[README.md](README.md) | [docs/README.md](docs/README.md)

---

# 1. Document purpose

This document defines the complete chronological implementation plan for the Cash Flow Summary application.

The system will be developed from project documentation through:

1. Local environment setup
2. Laravel project creation
3. Visual design system
4. Database design
5. Shared backend foundation
6. Owner interface
7. Center Manager interface
8. Cashier interface
9. CSV verification and import
10. Duplicate detection
11. Daily financial versioning
12. Compact dashboards
13. Reports and exports
14. WhatsApp notifications
15. Security and audit controls
16. Testing
17. Dockerization
18. VPS deployment
19. Backup and monitoring

Each step must be completed and tested before dependent steps begin.

---

# 2. Application overview

## 2.1 Application name

**Cash Flow Summary**

## 2.2 Business purpose

Consolidate cash-flow information of several vehicle technical inspection centers owned by one organization.

Each center uses an external operational application generating cashier-statement CSV files in French or English.

Authorized users will:

- Select a CSV file
- Verify structure and footer summary
- Accept or reject the file
- Import and store valid records
- Ignore exact duplicate records
- Preserve source file and import history
- Generate daily, weekly, monthly, yearly, and custom statistics
- Monitor each center from the Owner account (one active center at a time)
- Send WhatsApp summaries after successful imports

---

# 3. Final user roles

Exactly three roles:

1. Owner or Super Administrator
2. Center Manager
3. Cashier

**Excluded from v1:** Central Administrator, Auditor, read-only user, public registration, customer account.

---

# 4. Role and center rules

## 4.1 Owner

Can: create/edit/deactivate centers; create users; assign roles; assign Managers/Cashiers to centers; reset passwords; activate/deactivate users; reassign users; **select one active working center**; operate within that center (dashboard, CSV, imports, records, versions, revisions, reports, anomalies, WhatsApp history); approve financial revisions for active center; organization admin (centers, users, settings, audit).

Owner has authority over all centers but **does not work with all centers simultaneously**. After login → Center Selection → active center session. No `All Centers` operational option. No consolidated financial dashboard.

See [docs/design/owner-active-center.md](docs/design/owner-active-center.md).

## 4.2 Center Manager

One center only — automatically known after login.

Can: center dashboard; verify/import CSV; import history; duplicates; center records; center reports; submit corrections for Owner approval; review anomalies.

Cannot: select another center; create users; approve revisions; global settings; consolidated reports.

## 4.3 Cashier

One center only — automatically known after login.

Can: simplified dashboard; verify/import CSV; reject file; recent imports; results; duplicate counts; error report downloads.

Cannot: other centers; create users; approve corrections; modify records; organization reports.

---

# 5. User creation process

Only Owner creates users. Center must exist first.

For Manager/Cashier: full name, username, telephone, optional email, role, assigned center, temporary password, active status.

Center mandatory for Manager/Cashier; not assigned to Owner.

No center-selection field for Manager or Cashier anywhere.

---

# 6. Confirmed CSV structure

## 6.1 Languages

French or English — auto-detected from headers. **Reject mixed-language headers.**

## 6.2 French headers

Date Enregistrement, Heure d'enregistrement, Date de fin d'inspection, Client, Cat., Type, Immatriculation, Montant Hors Taxe, Montant de la TVA, Montant TTC

## 6.3 English headers

Regitration date, Regitration hour, Inspection completion date, Customer, Cat., Type, Licence plate, Amount Ex. VAT, Amount of VAT, Amount Inc. VAT

Support misspellings: Regitration/Registration date/hour/time variants.

## 6.4 Characteristics

UTF-8 BOM; semicolon delimiter; ten business columns; footer row; whole-number XAF; space thousands separators; source count; HT/VAT/TTC totals.

## 6.5 Footer

French: Nombre total d'inspections, Total. English: Total number of inspections, Total.

Footer provides declared count, HT, VAT, TTC — **not imported as a financial record**.

See [docs/design/csv-specification.md](docs/design/csv-specification.md).

---

# 7. CSV verification and import workflow

**Not a seven-step wizard.** Three steps only:

1. Select CSV file
2. Verify CSV file
3. Import or Reject

See [docs/design/csv-verification-flow.md](docs/design/csv-verification-flow.md).

---

# 8. CSV interface layout

One compact professional card. States: initial (heading, center, format info, file control, Verify); file selected (filename, size, time, remove, Verify); verification summary (sections 9–10).

**Owner:** active center shown as read-only (**Importing for: {center}** or **Active Center: {name}** in header) — no center dropdown on CSV card. **Manager/Cashier:** fixed assigned center.

---

# 9. CSV verification summary

File info; footer summary table (TTC most prominent); validation status (structure, count, HT, VAT, TTC); compact stats (completed, unfinished, revenue, zero-value, exact duplicates, new unique, invalid rows); warnings (non-blocking).

---

# 10. Import and Reject actions

**Import:** emerald primary; loading/disabled/double-click protection; permanent storage pipeline (section 28).

**Reject:** no financial records; delete temp file; minimal audit event without CSV content.

**Invalid:** Import disabled; clear explanation; allow new file selection.

---

# 11. Import result page

Compact result: status, center, file, period, rows, new/duplicate/invalid counts, active days, unchanged days, revisions pending, HT/VAT/TTC, WhatsApp status. Actions: dashboard, import details, import another.

---

# 12. Temporary verification data

Short-lived: temp file, verification token, metadata, footer summary, validation totals, duplicate summary. Rejected = immediate delete. Abandoned = expire after retention period. Never in current reports.

Entity: `import_verifications` — see [docs/design/data-model.md](docs/design/data-model.md).

---

# 13. Application architecture

Modular Laravel monolith. Modules: Authentication, Centers, Users, CSV verification, CSV imports, Record normalization, Duplicate detection, Daily versions, Dashboards, Reports, WhatsApp, Audit logging, System settings.

One deployable app; strong transactions; no microservices.

---

# 14. Technology stack

**Backend:** Laravel, PHP 8.2+, Composer, Eloquent, queues, scheduler, HTTP client, Pest

**Frontend:** Livewire, Blade, Tailwind, Flux UI, Vite, Chart.js, Laravel localization

**Data:** MySQL 8, Redis, Horizon, private filesystem

**Production:** Nginx, PHP-FPM, Docker, Docker Compose, Ubuntu, HTTPS, GitHub

---

# 15. Icon library

**Primary:** Heroicons through Flux UI — no separate icon package at project start. Flux integrates Heroicons natively with buttons, inputs, navigation, breadcrumbs, badges, and menus.

**Variants:**

- **Outline** — sidebar navigation, tables, filters, normal actions
- **Mini / micro** — compact buttons, badges, table actions
- **Solid** — selected navigation, important status cards, primary dashboard highlights

**Coverage target:** Heroicons for ≥ 90% of icons. Do not mix outline/solid on the same control or multiple libraries on the same page.

**Secondary:** Individual Lucide icons via `php artisan flux:icon <name>` only when Heroicons has no suitable equivalent — never the full Lucide collection.

**Brand:** Custom SVG components for WhatsApp logo only.

**Not used:** Font Awesome, Bootstrap Icons, Material Symbols, or multiple full icon libraries.

Full mapping and implementation checklist: [docs/design/design-system.md](docs/design/design-system.md).

---

# 16. Visual design system — Midnight Finance

| Token | Value | Use |
|-------|-------|-----|
| Midnight Navy | `#14213D` | Sidebar, nav, headings |
| Emerald | `#0F766E` | Primary buttons, active nav, success |
| Warm Gold | `#D6A756` | Owner accents, key totals |
| Background | `#F5F7FA` | App background |
| Surface | `#FFFFFF` | Cards, tables |

Typography: Manrope headings, Inter body, tabular numerals for money.

Buttons: Primary emerald; Secondary white/navy border; Owner approval navy+gold; Reject destructive red.

---

# 17. Compact dashboard principles

12-column grid; four summary cards; one chart; one status panel; one activity table. Not crowded — useful above the fold. Details in reports/record pages.

---

# 18. Owner dashboard (selected center)

**Not** an organization-wide consolidated dashboard. Scoped to **active working center** only.

Title example: **NACHO Yaounde Cash-Flow Dashboard**

Header: active center (switchable dropdown), period, date filter, Import CSV, export, last import.

**Row 1:** TTC | HT | VAT | Active unique records  
**Row 2:** Completed | Unfinished | Zero-value | Duplicates ignored  
**Row 3:** Revenue trend (daily/weekly/monthly/yearly) + submission/alerts panel  
**Row 4:** Recent imports (active center only)

Alerts: reconciliation failure, revision pending, probable duplicate, missing report, failed import, WhatsApp failure.

Full spec: [docs/design/owner-active-center.md](docs/design/owner-active-center.md).

---

# 19. Center Manager dashboard

Fixed center header; upload button; today/week/month TTC; active records today; trend chart; submission status; recent imports; alerts (correction, invalid import, probable duplicates).

---

# 20. Cashier dashboard

Most compact: center, date, import button; today's TTC/records/last status/duplicates; submission card; short recent imports list.

---

# 21. Database design

**Administrative:** organizations, centers, users, roles, permissions, center_operating_calendars, center_calendar_exceptions

**CSV config:** csv_format_versions, header_aliases

**Verification:** import_verifications (temporary)

**Permanent import:** imports, import_rows, import_errors, import_day_comparisons

**Financial integrity:** master_cash_flow_records, anomalies, daily_versions, daily_version_memberships, active_daily_snapshots

**Reporting:** daily_summaries, summary_breakdowns, export_requests

**Notifications:** whatsapp_messages, whatsapp_webhook_events, internal_notifications, audit_logs

Full schema: [docs/design/data-model.md](docs/design/data-model.md).

---

# 22. Exact duplicate strategy

## 22.1 Ten canonical fields

Registration date, time, completion date, customer, category, type, licence plate, HT, VAT, TTC — same center.

## 22.2 Field-specific normalization (`field_specific_v1`)

**Licence plate:** uppercase; remove spaces, hyphens, dots, slashes, underscores.

**Customer:** trim; collapse spaces; uppercase; standardize apostrophes/dashes; preserve spelling.

**Dates/times:** canonical parse. **Amounts:** whole-number XAF.

## 22.3 Identity layers

raw_row_checksum, exact_canonical_hash, similarity_fingerprint, daily_dataset_hash.

## 22.4 Constraint

Unique `(center_id, normalization_policy_version, exact_canonical_hash)`.

## 22.5 Treatment

Duplicates not inserted as masters; not double-counted; preserved in import_rows; in duplicate statistics.

---

# 23. Daily financial versioning

Per center + business date: New, Unchanged, Revision required, Covered without rows, Invalid.

New → activate. Unchanged → keep active. Revised → proposed version; **Owner approves only**.

Reports use active daily snapshots only.

---

# 24. Chronological interface implementation

1. Owner
2. Center Manager
3. Cashier

Shared `CsvVerificationCard` Livewire component — see ADR 0010.

---

# 25–27. Role interface scope

**Owner (25):** Auth + 2FA; dashboard; centers; users; settings; CSV (select center); imports; revisions; reports; audit.

**Manager (26):** Fixed center; dashboard; CSV; imports; revision submission; records search; reports.

**Cashier (27):** Fixed center; compact dashboard; CSV; import history; error downloads.

Detail: [docs/design/ux-overview.md](docs/design/ux-overview.md), [docs/product/user-stories.md](docs/product/user-stories.md).

---

# 28. Backend processing sequence

**Verify:** authorize → create token → temp store → inspect → map headers → parse → footer → reconcile → normalize → duplicates → summary → return.

**Import:** validate token → re-authorize → lock → permanent store → rows → masters → duplicates → daily versions → summaries → dashboards → WhatsApp queue → complete.

**Reject:** validate token → delete temp → invalidate token → empty state.

---

# 29. Queue processing

Redis for large parsing, duplicates, daily datasets, summaries, WhatsApp, exports. Livewire polls verification token / import ID — **never holds parsed rows in browser state** (ADR 0009).

---

# 30. Reports and exports

Active daily snapshots only. Exclude temp verification, rejected files, duplicate occurrences, superseded/rejected versions.

Reports: daily, weekly, monthly, yearly, custom, duplicate, submission — **active center only** (no center-comparison report in v1).

Exports: CSV, Excel, PDF, print.

---

# 31. WhatsApp integration

Meta WhatsApp Cloud API. Events: successful import, with duplicates, duplicate-only, revision pending/approved, mismatch, missing submission, delivery failure, consolidated daily summary.

Aggregate totals only — no customer/plate lists. Idempotency keys required. Owner number in admin settings.

**Outbound (required to send):** owner phone, phone number ID, access token. Sufficient for Meta test number / local testing.

**Inbound webhooks (production):** webhook verify token optional during testing (Meta test number does not provide one); required in production for delivered / read / failed status updates (REQ-096, ADR 0007).

Historical imports: WhatsApp suppressed by default; optional "Notify Owner" checkbox.

---

# 32. Security requirements

Strong passwords; temp password change; rate limiting; session timeout; Owner 2FA.

Center isolation: middleware, policies, query scopes, services, exports, downloads, queue context.

Files: private storage, size limits, validation, controlled downloads, temp cleanup.

Financial: fixed-precision money, unique constraints, transactions, immutable imports, versioned corrections, Owner approval, audit.

---

# 33. Audit logging

Login, failed login, center/user CRUD, password reset, reassignment, deactivation, verification failure, import, exact file duplicate, rejection after verification, import failure, revision submit/approve/reject, export, WhatsApp resend, settings. Rejected CSV content not retained.

---

# 34. Automated testing

Interface: dashboards, role center rules, Verify/Import/Reject, button states.

CSV: FR/EN, missing footer/column, invalid amount/date, in-file/historical/file duplicate, mixed records, revised date, mixed-language reject.

Security: cross-center access, altered center, unauthorized download/approval, reused/expired token, double Import.

Financial: footer reconciliation, HT+VAT=TTC, duplicate exclusion, active-version reporting.

See [docs/testing/test-strategy.md](docs/testing/test-strategy.md).

---

# 35. Dockerization and production

Docker: Nginx, PHP-FPM, MySQL, Redis, Horizon, Scheduler.

VPS: SSH keys, non-root deploy user, firewall, HTTPS, secrets, security updates.

See [docs/operations/deployment.md](docs/operations/deployment.md).

---

# 36. Backup and monitoring

Daily DB + file backup; weekly full; monthly retention; off-server encrypted.

Monitor: uptime, failed imports/verifications, reconciliation failures, pending revisions, queue backlog, WhatsApp failures, DB/Redis/disk, backups, certificate expiry.

See [docs/operations/backup-monitoring.md](docs/operations/backup-monitoring.md).

---

# 37. Chronological implementation sequence

**Authoritative build order:** [docs/implementation-sequence.md](docs/implementation-sequence.md) — **Steps 1–120**, strict one-step-at-a-time.

**Rule:** Complete Step N before starting Step N+1. Pass each checkpoint before continuing past a step group.

Summary:

| Step block | Content |
|------------|---------|
| 1–8 | Documentation (complete) |
| 9–18 | Project setup, Laravel stack |
| 19–24 | Frontend design system & shell |
| 25–42 | Database Waves 1–2 |
| 32–39, 43–51, 65–71 | Backend: auth, verification, financial |
| 52–58, 72–89 | Frontend: Owner admin → Owner ops → Manager → Cashier |
| 59–64 | Database Wave 3 |
| 90–98 | Reports, exports, WhatsApp |
| 99–120 | Security, tests, UAT, Docker, VPS, rollout |

### Legacy checklist (66 items) — mapped to steps

See [implementation-sequence.md](docs/implementation-sequence.md#map-steps--original-checklist-planmd-37). REQ/NFR traceability: [requirements.md](docs/product/requirements.md#appendix--reqnfr-by-step-group-phase-rollup) (step groups in [Appendix A](docs/implementation-sequence.md#appendix-a--step-groups-phase-rollup)).

**Important:** Original checklist grouped "Owner UI" before "Financial backend". **Steps correct this:** verification backend (43–51) and financial backend (65–71) precede operational UI (72–79).

---

# 38. Delivery sprints (planning view)

| Sprint | Steps | Gate |
|--------|-------|------|
| **S1 — Foundation** | 9–39 | [implementation-sequence.md](docs/implementation-sequence.md) checkpoint after Step 39 |
| **S2 — Verification backend** | 40–51 | Checkpoint after Step 51 |
| **S3 — Owner admin** | 52–58 | Checkpoint after Step 58 |
| **S4 — Owner financial UI** | 72–79 (+43–51, 65–71) | Checkpoint after Step 79 |
| **S5 — Financial core** | 59–71, 90–93 | Checkpoint after Step 71 |
| **S6 — Manager** | 80–85 | Checkpoint after Step 85 |
| **S7 — Cashier** | 86–89 | Checkpoint after Step 89 |
| **S8 — Production** | 94–120 | Checkpoint after Step 120 |

**Step order is strict** — no parallel work streams. Sprints are planning rollups only.

---

# 39. Step checkpoints

Each step group in [implementation-sequence.md](docs/implementation-sequence.md) ends with a **checkpoint** — criteria that must pass before the next step group. Work **one step at a time** within and across groups. Production sign-off: [acceptance-criteria.md](docs/testing/acceptance-criteria.md).

---

# 40. Sprint gates (summary)

See step checkpoints in [implementation-sequence.md](docs/implementation-sequence.md) Appendix C and [acceptance-criteria.md](docs/testing/acceptance-criteria.md).

---

# 41. Documentation map

See [docs/README.md](docs/README.md) for all files and reading order.

---

# 42. Explicit v1 exclusions

- Public user registration
- Email notifications (any purpose)
- Microservices or separate import API
- Customer or plate lists in WhatsApp messages
- Manual editing of imported financial records
- Central Administrator, Auditor, or read-only roles
- Seven-step import wizard
- Consolidated Owner operational dashboard or `All Centers` view
- Center-comparison report (v1)
- Retaining rejected CSV content in permanent storage

---

# 43. Owner active-center architecture

Owner selects **one active working center** after login. All operational pages scoped to that center. No consolidated financial dashboard.

Full specification: [docs/design/owner-active-center.md](docs/design/owner-active-center.md) | ADR [0011](docs/architecture/decisions/0011-owner-active-center.md)

---

## Next steps

1. **Development:** **Step 33** — Session, rate limiting, password policy ([docs/architecture/security-privacy.md](docs/architecture/security-privacy.md))
2. Follow steps **in order** (1 → 120); do not build CSV UI before Step 51 (verification backend)
