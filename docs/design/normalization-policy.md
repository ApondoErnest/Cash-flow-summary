# Normalization Policy

[← Documentation hub](../README.md) | [business-rules.md](../product/business-rules.md) | [calculations.md](calculations.md)

**Active policy:** `field_specific_v1` (BR-016)

---

## Purpose

Deterministic canonical values for **exact duplicate** comparison (`exact_canonical_hash`). Separate **similarity fingerprint** for probable duplicates.

---

## Transport cleanup (all fields)

Before field-specific rules:

- Remove CSV quoting artifacts
- Strip BOM remnants
- Trim outer whitespace
- Strip control characters

---

## Field-specific rules (`field_specific_v1`)

| Field | Rules |
|-------|-------|
| licence_plate | Uppercase; remove spaces, hyphens, dots, slashes, underscores; keep letters and digits only |
| customer_name | Trim; collapse repeated spaces; uppercase; standardize apostrophes and dashes; preserve spelling |
| category_code | Trim only; display as imported |
| inspection_type_code | Trim; uppercase |
| registration_date, completion_date | Parse to `Y-m-d` |
| registration_time | Parse to `H:i:s` |
| net_amount, vat_amount, gross_amount | Integer XAF (no decimals in source) |

### Effect

`AB-123`, `ab 123`, `AB 123` → same canonical plate → **same exact record** if all other fields match.

Customer spelling differences after normalization → **different** exact records.

---

## Similarity fingerprint

More aggressive than exact hash — used for **probable duplicate** warnings only:

- Fuzzy customer tolerance
- Broader plate matching for review UI
- Same date + time + amounts + plate core

**Must not** auto-delete or ignore financial records (BR-017).

---

## Policy version storage

`master_cash_flow_records.normalization_policy_version` = `field_specific_v1`

Changing policy requires new version column value and migration plan for historical hashes.

---

## Database constraint

```text
UNIQUE (center_id, normalization_policy_version, exact_canonical_hash)
```

---

## Related

- [ADR 0005](../architecture/decisions/0005-exact-duplicate-ledger.md)
- [ADR 0008](../architecture/decisions/0008-probable-duplicates.md)
