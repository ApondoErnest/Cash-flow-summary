# ADR 0008: Probable duplicate review

**Status:** Accepted  
**Date:** 2026-06-27

## Context

Exact duplicates (ADR 0005) prevent double-counting. Similar records — same plate and amounts with different customer spelling — must not be silently merged.

## Decision

- **similarity_fingerprint** separate from `exact_canonical_hash`
- Rows matching fingerprint but not exact hash → **probable duplicate**
- Shown in verification summary and import result as **warnings**
- **Informational only** — not auto-ignored (BR-017)
- All roles see probable duplicate details for their center scope
- May feed `anomalies` table

## Consequences

- `SimilarityFingerprintService` and UI warning counts
- Tests for probable vs exact classification

## Related

- [normalization-policy.md](../../design/normalization-policy.md)
- ADR 0005
