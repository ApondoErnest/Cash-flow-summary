# ADR 0009: Verification token flow

**Status:** Accepted  
**Date:** 2026-06-27

## Context

v1 originally specified a 7-step wizard holding row previews in Livewire. v2 uses a **3-step flow** (Select → Verify → Import/Reject) with one summary card. Large CSVs cannot fit in browser component state.

## Decision

1. **Verify** creates `import_verifications` row with UUID token
2. Parsing runs server-side (queue for large files)
3. Summary stored as JSON on verification record
4. Livewire component holds **token only** (+ polling state)
5. **Import** consumes token single-use → `ImportService::commitFromVerification()`
6. **Reject** deletes temp file, marks rejected, invalidates token
7. UI polls `status` until `ready` or `failed`

## Replaces

Seven-step stateless wizard pattern from v1 documentation.

## Consequences

- `import_verifications` table and cleanup scheduler required
- Security tests for token reuse/expiry
- Shared `CsvVerificationCard` component for all roles

## Related

- [csv-verification-flow.md](../../design/csv-verification-flow.md)
- [data-model.md](../../design/data-model.md#import_verifications)
- NFR-003
