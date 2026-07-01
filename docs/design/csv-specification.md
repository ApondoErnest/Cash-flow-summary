# CSV Specification

[← Documentation hub](../README.md) | [csv-verification-flow.md](csv-verification-flow.md)

Authoritative format for cashier-statement files from the external inspection application.

---

## File format

| Property | Value |
|----------|-------|
| Encoding | UTF-8 with BOM |
| Delimiter | Semicolon (`;`) |
| Line ending | CRLF or LF |
| Business columns | 10 |
| Footer | 1 final row |
| Currency | XAF whole numbers |
| Thousands separator | Space in source (e.g. `1 500`) |

---

## Canonical fields

| # | Canonical | French header | English header |
|---|-----------|---------------|----------------|
| 1 | registration_date | Date Enregistrement | Regitration date / Registration date |
| 2 | registration_time | Heure d'enregistrement | Regitration hour / Registration hour / Registration time |
| 3 | completion_date | Date de fin d'inspection | Inspection completion date |
| 4 | customer_name | Client | Customer |
| 5 | category_code | Cat. | Cat. |
| 6 | inspection_type_code | Type | Type |
| 7 | licence_plate | Immatriculation | Licence plate |
| 8 | net_amount | Montant Hors Taxe | Amount Ex. VAT |
| 9 | vat_amount | Montant de la TVA | Amount of VAT |
| 10 | gross_amount | Montant TTC | Amount Inc. VAT |

Misspelling **Regitration** must be accepted alongside correct **Registration** spellings.

---

## Language detection

Match headers against `header_aliases` for active `csv_format_version`.

- File must be **consistently French OR English** — mixed headers **rejected** (BR-015)
- User does not manually select language

### Header normalization (matching only)

Remove BOM; trim; lowercase; collapse whitespace; normalize accents for comparison. **Does not alter stored business values.**

---

## Footer structure

### French

- Label row contains: `Nombre total d'inspections`, `Total`
- Provides: record count, HT total, VAT total, TTC total

### English

- Label row contains: `Total number of inspections`, `Total`
- Same four values

**Footer is never imported as a financial record.**

---

## Amount parsing

- Strip spaces (thousands separators)
- Parse as integer XAF
- **Negative values → invalid row** (BR-004)
- Validate HT + VAT = TTC per row

---

## Dates and completion

| Source | Canonical |
|--------|-----------|
| Valid date | `Y-m-d` |
| `-`, empty, invalid | `null` (unfinished) |

---

## Category and type

- **Cat.** displayed as imported (BR-003)
- **Type:** `C` = standard inspection; `CV` = counter-visit (BR-001, BR-002)

---

## Unknown headers

If required canonical field cannot be mapped:

1. Stop processing
2. Show unknown header
3. Suggest matches
4. Only Owner may approve new alias (REQ-038)

---

## Multi-day files

One file may contain multiple registration dates. Verification shows actual record period vs filename-implied period (warning if differ).

---

## File-level duplicate

Compute SHA-256 of entire file. If `(center_id, file_hash)` exists, show prior import reference — user may still proceed if business allows.

---

## Related

- [normalization-policy.md](normalization-policy.md) — exact duplicate rules
- [calculations.md](calculations.md) — reconciliation formulas
- [data-model.md](data-model.md) — `header_aliases`, `csv_format_versions`
