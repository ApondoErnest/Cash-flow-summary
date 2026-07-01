# ADR 0010: Owner-first interface delivery

**Status:** Accepted (amended 2026-06-27)  
**Date:** 2026-06-27

## Context

Three roles need distinct UIs. Admin functions are Owner-only. Owner operational model uses **active-center session** (ADR 0011), not consolidated dashboard.

## Decision

Build interfaces in order:

1. **Owner foundation** — login, 2FA, **Center Selection**, active-center session, header dropdown, **selected-center dashboard**, switching
2. **Owner administration** — manage centers (Open Center), users, settings
3. **Owner operational** — CSV (active center), imports, records, versions, revisions, reports — shared `CsvVerificationCard`
4. **Manager** — fixed center; reuse CSV component
5. **Cashier** — minimal; reuse CSV component

**Backend dependency:** S2 verification backend before S4 Owner CSV UI.

## Amended by ADR 0011

- Removed consolidated Owner dashboard from scope
- Center Selection precedes operational dashboard
- No center picker on CSV card for Owner

## Related

- [owner-active-center.md](../../design/owner-active-center.md)
- ADR 0011
- [roadmap.md](../../product/roadmap.md)
