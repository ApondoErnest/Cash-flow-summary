# Glossary

[← Documentation hub](../README.md) | [calculations.md](../design/calculations.md)

---

## Reporting terms

| Term | Definition |
|------|------------|
| **Source row** | Data row in CSV excluding footer |
| **Unique master record** | Source row not matching existing exact record for center |
| **Duplicate occurrence** | Row matching existing exact record — not new master |
| **Probable duplicate** | Similarity fingerprint match; not exact — informational |
| **Exact duplicate** | All ten canonical fields match per `field_specific_v1` |
| **Completed record** | Valid (non-null) completion date |
| **Unfinished record** | No valid completion date |
| **Zero-value record** | HT, VAT, TTC all zero — valid when rules allow |
| **Active record** | Master in currently approved daily version for its date |

## Financial terms

| Term | Definition |
|------|------------|
| **HT** | Amount excluding VAT |
| **VAT** | Tax amount (TVA) |
| **TTC** | Amount including VAT — primary cash-flow metric |
| **XAF** | Central African CFA franc |

## Import terms

| Term | Definition |
|------|------------|
| **Verification token** | Short-lived ID linking temp file to verification summary |
| **Import verification** | Pre-commit state in `import_verifications` |
| **Canonical field** | Internal normalized field (10 business fields) |
| **Header alias** | Source CSV header → canonical mapping |
| **Footer** | Final row with count and HT/VAT/TTC totals |
| **exact_canonical_hash** | Hash of ten fields after `field_specific_v1` |
| **raw_row_checksum** | Hash of row as physically supplied |
| **similarity_fingerprint** | Aggressive match for probable duplicates |
| **File hash** | Hash of entire file — detects re-upload |

## Versioning terms

| Term | Definition |
|------|------------|
| **Daily dataset** | Unique masters for one center + registration date |
| **Daily version** | Accepted or proposed snapshot for a business day |
| **Active daily snapshot** | Pointer to approved version for center + date |
| **Revision** | Proposed version differing from active |

## Roles

| Term | Definition |
|------|------------|
| **Owner** | Super Administrator; all centers (one active at a time for operations) |
| **Center Manager** | One assigned center |
| **Cashier** | One assigned center; upload-focused |

## Design terms

| Term | Definition |
|------|------------|
| **Midnight Finance** | Application design system (navy, emerald, gold) |
| **Active working center** | Owner session-selected center for all operational pages |
| **Center Selection** | Post-login page where Owner picks active center |
| **CsvVerificationCard** | Shared Livewire import component |
