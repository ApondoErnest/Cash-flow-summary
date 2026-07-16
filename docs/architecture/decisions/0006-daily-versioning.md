# ADR 0006: Daily versioning

**Status:** Accepted  
**Date:** 2026-06-27

## Context

Source data can change for a business day after initial import. Reports must show one authoritative state per center per day.

## Decision

- `daily_versions` per center + business_date with version_number
- `active_daily_snapshots` points to exactly one active version per center + date
- Outcomes: New, Unchanged, Revision required, Covered without rows, Invalid
- Changed data on a **past** business day (or any **correction** import) → **proposed** version; **Owner approves** before snapshot updates
- **Same-day operational/historical** imports that extend or amend the active day (center local date = registration/business date) **auto-activate** without Owner approval
- Reports query active snapshots only

## Consequences

- Revision approval UI required for Owner
- Manager submits corrections; cannot self-approve

## Related

- [calculations.md](../../design/calculations.md)
- REQ-070–REQ-073
