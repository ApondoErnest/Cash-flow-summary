# Import and Verification Statuses

[← Documentation hub](../README.md) | [data-model.md](data-model.md)

---

## Import verification statuses (`import_verifications.status`)

| Status | Description |
|--------|-------------|
| pending | Token created; awaiting verify job |
| processing | Verify job running |
| ready | Summary available; Import/Reject enabled |
| imported | Committed to permanent import |
| rejected | User rejected; temp deleted |
| expired | TTL passed; cleaned up |
| failed | Verify or commit error |

---

## Import modes (`imports.import_mode`)

| Mode | Description |
|------|-------------|
| operational | Normal current reporting |
| historical | Backfill past periods |
| correction | Source data changed; revision workflow |

---

## Import statuses (`imports.status`)

| Status | Description |
|--------|-------------|
| processing | Permanent import in progress |
| completed | Successfully finished |
| completed_with_duplicates | Finished; exact duplicates ignored |
| completed_with_warnings | Non-blocking warnings |
| exact_file_duplicate | Entire file hash matched prior import |
| awaiting_owner_approval | Revision(s) pending |
| failed | Processing error |
| cancelled | Cancelled |

---

## Import row statuses (`import_rows.row_status`)

| Status | Description |
|--------|-------------|
| new | Parsed, not yet classified |
| accepted | Valid unique master candidate |
| duplicate_within_file | Exact duplicate of earlier row in file |
| historical_duplicate | Exact match to existing master |
| probable_duplicate | Similarity match; not exact |
| invalid | Validation failed |
| ignored | Excluded by policy |

---

## Duplicate types (`import_rows.duplicate_type`)

| Type | Description |
|------|-------------|
| within_file | Same exact hash earlier in file |
| historical | Matches center master ledger |
| probable | Similarity only |
| null | Not a duplicate |

---

## Daily version statuses (`daily_versions.status`)

| Status | Description |
|--------|-------------|
| proposed | Awaiting Owner approval |
| active | Currently approved snapshot |
| superseded | Replaced by newer active |
| rejected | Owner rejected |
| invalid | Cannot activate |

---

## Daily comparison outcomes (`import_day_comparisons.comparison_result`)

| Result | Description |
|--------|-------------|
| new | No active version for date |
| unchanged | Dataset identical to active |
| revision_required | Differs from active |
| covered_without_rows | Date covered but no rows in file |
| invalid | Cannot process |

---

## WhatsApp message statuses (`whatsapp_messages.status`)

| Status | Description |
|--------|-------------|
| queued | Awaiting send |
| sent | Accepted by API |
| delivered | Delivered to device |
| read | Read receipt |
| failed | Send failed after retries |

---

## Export request statuses (`export_requests.status`)

| Status | Description |
|--------|-------------|
| pending | Queued |
| processing | Generating |
| completed | File ready |
| failed | Error |
| expired | Download link expired |
