# ADR 0011: Owner active-center session

**Status:** Accepted  
**Date:** 2026-06-27

## Context

v2 documentation initially described an Owner consolidated dashboard with center filters and center-comparison reports. Product direction changed: Owner has authority over all centers but must **work within one selected active center** at a time — no `All Centers` operational view.

## Decision

1. After login, Owner selects center on **Center Selection** page
2. **Active working center** stored in server session (`active_center_id`, `organization_id`, `selected_at`)
3. All **operational** pages scoped to active center automatically
4. **Administrative** pages (manage centers/users, org settings) remain organization-wide
5. Header shows **Active Center: {name}** with dropdown to switch — no All Centers option
6. **`EnsureOwnerActiveCenter` middleware** on operational routes
7. Services receive `ActiveCenterContext` — never trust request `center_id` alone
8. Queue jobs use `import.center_id` from DB record, not session
9. **Remove** consolidated Owner dashboard and center-comparison reports from v1 scope

## Consequences

- New Center Selection page and session management (S3)
- Owner CSV page shows "Importing for: {center}" — no center dropdown on import card
- `OwnerDashboardService` becomes selected-center scoped
- Remove `CenterComparisonService` from v1
- Export requests always tied to a center for operational exports

## Related

- [owner-active-center.md](../../design/owner-active-center.md)
- ADR 0010 (owner-first delivery — still valid; add center selection before dashboard)
- REQ-110–REQ-120
