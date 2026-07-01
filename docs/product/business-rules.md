# Business Rules

[← Documentation hub](../README.md) | [normalization-policy.md](../design/normalization-policy.md)

**Status:** Confirmed for v2 documentation (2026-06-27)

---

## Decision register

| ID | Rule | Decision |
|----|------|----------|
| BR-001 | Inspection type `C` | Standard initial technical inspection; may be zero TTC |
| BR-002 | Inspection type `CV` | Counter-visit after failure; usually zero TTC |
| BR-003 | Category (`Cat.`) | Display exactly as imported; no lookup table in v1 |
| BR-004 | Negative amounts | Invalid — row rejected |
| BR-005 | Completed + zero TTC | Valid (e.g. failed counter-visit) |
| BR-006 | Report periods | Show days with data or explicitly covered by import |
| BR-007 | Closed days omitted | No missing-submission alert for configured closures |
| BR-008 | Submission deadline | Per center in center settings |
| BR-009 | Opening days | Per center operating calendar |
| BR-010 | Holidays/closures | Owner configures calendar exceptions |
| BR-011 | Cashier historical imports | Allowed |
| BR-012 | Manager corrections | Manager uploads and submits; Owner approves |
| BR-013 | Revision approval | Single step; required approve/reject reason |
| BR-014 | Historical WhatsApp | Suppressed by default; optional "Notify Owner" on upload |
| BR-015 | Mixed-language headers | Reject file |
| BR-016 | Exact duplicate normalization | **`field_specific_v1`** — see normalization-policy.md |
| BR-017 | Probable duplicates | All roles see details; informational only |
| BR-018 | Owner WhatsApp number | Admin settings at deployment; not in Git |
| BR-019 | Owner active center | One selected center at a time; no All Centers operational view |
| BR-020 | Owner login flow | Login → Center Selection → Open Center → dashboard |
| BR-021 | Owner CSV scope | Active session center; no center picker on CSV page |
| BR-022 | Queue job center | Use `import.center_id` from DB; not Owner session |

---

## CSV workflow

Three steps only: **Select → Verify → Import or Reject**. No permanent financial data until Import.

## Owner active-center

Full specification: [owner-active-center.md](../design/owner-active-center.md)

---

| Mode | Purpose |
|------|---------|
| Operational | Normal daily reporting |
| Historical | Backfill past periods |
| Correction | Changed source data; triggers revision workflow |

---

## Inspection type reference

| Code | Meaning | Typical TTC |
|------|---------|-------------|
| `C` | Standard initial inspection | Any including zero |
| `CV` | Counter-visit | Usually zero |

---

## Related documents

| Topic | Document |
|-------|----------|
| Permissions | [permission-matrix.md](permission-matrix.md) |
| Verification UX | [csv-verification-flow.md](../design/csv-verification-flow.md) |
| Owner active center | [owner-active-center.md](../design/owner-active-center.md) |
| Calculations | [calculations.md](../design/calculations.md) |
