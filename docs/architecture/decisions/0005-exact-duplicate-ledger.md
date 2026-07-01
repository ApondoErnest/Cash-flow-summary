# ADR 0005: Exact duplicate ledger

**Status:** Accepted  
**Date:** 2026-06-27  
**Policy:** `field_specific_v1`

## Context

Re-imported CSVs overlap daily, monthly, and historical periods. Duplicate revenue must be impossible across all time per center.

## Decision

- Ten-field canonical equality after `field_specific_v1` normalization
- Deterministic `exact_canonical_hash` + `normalization_policy_version`
- `master_cash_flow_records` with **UNIQUE (center_id, normalization_policy_version, exact_canonical_hash)**
- All import rows preserved; duplicates link to master without new insert
- On hash match, recompare canonical fields before confirming

## Policy

Active: **`field_specific_v1`** — plate uppercase/strip separators; customer conservative normalize.

## Consequences

- `AB-123` and `ab 123` → same record if other fields match
- Similar non-exact records handled via similarity fingerprint (ADR 0008)

## Related

- [normalization-policy.md](../../design/normalization-policy.md)
- [calculations.md](../../design/calculations.md)
