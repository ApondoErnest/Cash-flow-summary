# Permission Matrix

[← Documentation hub](../README.md) | [owner-active-center.md](../design/owner-active-center.md)

Legend: ✓ allowed | ✗ denied | △ limited | **AC** = active center scope (Owner)

---

## Owner active center

| Action | Owner | Manager | Cashier |
|--------|-------|---------|---------|
| Center Selection after login | ✓ | ✗ | ✗ |
| Switch active center (header dropdown) | ✓ | ✗ | ✗ |
| Operational pages without active center | ✗ redirect | n/a | n/a |
| See Owner center dropdown | ✓ | ✗ | ✗ |

---

## Administration

| Action | Owner | Manager | Cashier |
|--------|-------|---------|---------|
| Create/edit/deactivate centers | ✓ org-wide | ✗ | ✗ |
| Open Center (set active) | ✓ | ✗ | ✗ |
| Create/edit/deactivate users | ✓ org-wide | ✗ | ✗ |
| Reset passwords | ✓ | ✗ | ✗ |
| Application / WhatsApp settings | ✓ | ✗ | ✗ |
| Header alias approval | ✓ | ✗ | ✗ |

---

## CSV import

| Action | Owner | Manager | Cashier |
|--------|-------|---------|---------|
| Verify / Import / Reject | ✓ **AC** | ✓ fixed center | ✓ fixed center |
| Operational import | ✓ | ✓ | ✓ |
| Historical import | ✓ | ✓ | ✓ |
| Correction import | ✓ | ✓ | ✓ |
| Center picker on CSV card | ✗ | ✗ | ✗ |

---

## Imports and records

| Action | Owner | Manager | Cashier |
|--------|-------|---------|---------|
| View imports | ✓ **AC** | ✓ own center | △ recent |
| Import detail / duplicates | ✓ **AC** | ✓ | △ |
| Download original / errors | ✓ **AC** | ✓ | ✓ |
| Search records | ✓ **AC** | ✓ | ✗ |
| Probable duplicates | ✓ **AC** | ✓ | ✓ |

---

## Revisions

| Action | Owner | Manager | Cashier |
|--------|-------|---------|---------|
| Submit correction | ✓ **AC** | ✓ | ✗ |
| Approve/reject | ✓ **AC** | ✗ | ✗ |
| View pending | ✓ **AC** | △ own | ✗ |

---

## Reports and exports

| Action | Owner | Manager | Cashier |
|--------|-------|---------|---------|
| Center reports | ✓ **AC** | ✓ | ✗ |
| Consolidated / all-centers report | ✗ v1 | ✗ | ✗ |
| Center comparison report | ✗ v1 | ✗ | ✗ |
| Export CSV/Excel/PDF | ✓ **AC** | ✓ | ✗ |

---

## Audit

| Action | Owner | Manager | Cashier |
|--------|-------|---------|---------|
| Audit logs (admin) | ✓ org-wide | ✗ | ✗ |
| Operational audit filter | ✓ **AC** | ✗ | ✗ |

---

## Implementation notes

- Owner operational scope: `ActiveCenterContext` from session via `EnsureOwnerActiveCenter`
- Manager/Cashier: `users.center_id` via `EnsureAssignedCenter`
- Queue jobs: always `import.center_id` from record
- Manage Centers list is admin — no combined financial totals
