# UX Overview

[← Documentation hub](../README.md) | [design-system.md](design-system.md) | [owner-active-center.md](owner-active-center.md)

Text specification for implementation. Wireframes optional in design tool.

---

## Global layout

- **Sidebar** (midnight navy): role-based navigation
- **Top bar:** active center (Owner) or assigned center (Manager/Cashier), user menu
- **Content area:** app-bg, white cards
- **Mobile:** collapsible sidebar; stacked cards

Navigation icons per [design-system.md](design-system.md).

---

## Owner: active-center model

After login → **Center Selection** → **Open Center** → selected-center dashboard.

- Header always shows **Active Center: {name}** with switch dropdown
- No `All Centers` operational option
- Operational nav requires active center; admin nav separate
- See [owner-active-center.md](owner-active-center.md)

---

## Role navigation

### Owner — operational (active center required)

| Item | Notes |
|------|-------|
| Dashboard | Selected center only |
| Import CSV | Uses active center |
| Imports | Active center |
| Records | Active center |
| Daily Versions | Active center |
| Revisions | Active center |
| Reports | Active center only |
| Anomalies | Active center |
| WhatsApp History | Active center |

### Owner — administrative (separate sidebar section)

| Item | Notes |
|------|-------|
| Manage Centers | All centers; **Open Center** action |
| Manage Users | Organization-wide; filterable |
| Organization Settings | |
| WhatsApp Settings | |
| Security | |
| Audit Logs | Organization-wide |

### Manager / Cashier

| Item | Manager | Cashier |
|------|---------|---------|
| Dashboard | ✓ fixed center | ✓ compact |
| Import CSV | ✓ | ✓ |
| Imports / History | ✓ | ✓ recent |
| Records | ✓ | ✗ |
| Reports | ✓ center | ✗ |
| Revisions | submit only | ✗ |

**Manager/Cashier never see Owner center dropdown.**

---

## Center Selection page (Owner only)

- Heading: **Select a Center**
- Searchable dropdown of active centers
- **Open Center** (emerald primary)
- Empty state → **Create Center**

---

## Compact dashboard pattern

1. Compact header (title includes center name for Owner)
2. Four summary cards
3. One chart (Owner/Manager; not Cashier)
4. Status/alerts panel
5. Recent activity table

---

## Owner dashboard (selected center)

**Title:** `{Center Name} Cash-Flow Dashboard`

**Header:** active-center dropdown | period | date filter | Import CSV | export | last import

**Row 1:** TTC | HT | VAT | Unique records

**Row 2:** Completed | Unfinished | Zero-value | Duplicates ignored

**Row 3:** Revenue trend (8 cols) | Submission & alerts (4 cols)

**Row 4:** Recent imports table

**Alerts:** reconciliation failure, revision pending, probable duplicate, missing report, failed import, WhatsApp failure

**Removed:** Multi-center comparison table, organization-wide TTC rollup

---

## Manager dashboard

Fixed center header; upload button; today/week/month TTC; trend chart; submission status; alerts; recent imports.

---

## Cashier dashboard

Most compact: center, date, import button; today's stats; submission card; short recent imports.

---

## CSV import page

`CsvVerificationCard` — see [csv-verification-flow.md](csv-verification-flow.md).

- **Owner:** **Importing for: {active center}** — no center picker on card
- **Manager/Cashier:** **Center: {name}** read-only

---

## Owner admin pages

### Manage Centers

Table: name, code, location, user count, status, Edit, **Open Center**. No combined financial totals.

### Manage Users

Filter by center, role, status, name, username.

---

## Revision approval (Owner)

Active center scope. Old vs new totals; approve/reject with reason.

---

## Reports

Filter + results + export. **Owner reports = active center only** (no comparison report in v1).

---

## Empty states

| Context | Message |
|---------|---------|
| No centers (Owner) | Create your first center to continue |
| No active center | Redirect to Center Selection |
| No imports | No imports yet — upload a CSV |

---

## Accessibility

Focus visible; labels; status icons + text; tabular numerals for money.

---

## Delivery order

Owner: login → Center Selection → dashboard → admin → operational (S3–S4) → Manager (S6) → Cashier (S7). ADR 0010, 0011.
