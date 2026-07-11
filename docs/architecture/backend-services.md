# Backend Services

[← Documentation hub](../README.md) | [overview.md](overview.md)

Application service catalogue. One service class per bounded context; injected via Laravel container.

---

## Authentication module

| Service | Responsibility |
|---------|----------------|
| `LoginService` | Authenticate username/password, rate limit |
| `TwoFactorService` | Owner TOTP enroll/verify |
| `SessionService` | Timeout, invalidate other sessions |

---

## Centers module

| Service | Responsibility |
|---------|----------------|
| `CenterService` | CRUD, deactivate |
| `OperatingCalendarService` | Days, hours, exceptions |
| `SubmissionStatusService` | Missing submission detection |
| `ActiveCenterContextService` | Owner session active center get/set/validate/switch |
| `OwnerPreferredCenterService` | Preferred center persistence; login bootstrap; auto-select when single center |
| `CenterSelectionService` | List/search active org centers for Center Selection page |

---

## Users module

| Service | Responsibility |
|---------|----------------|
| `UserService` | CRUD, reassign center, deactivate, temporary password on create |
| `PasswordService` | Reset, must-change flag, assign temporary password |

---

## CsvVerification module

| Service | Responsibility |
|---------|----------------|
| `VerificationService` | Create token, store temp file, dispatch verify job |
| `VerificationCleanupService` | Expire/reject cleanup (scheduled) |
| `CsvInspectionService` | Encoding, delimiter, BOM, language detection |
| `HeaderMappingService` | Alias lookup; reject mixed language |
| `CsvParsingService` | Stream rows, parse amounts/dates |
| `FooterReaderService` | Extract footer totals |
| `ReconciliationService` | Compare parsed vs footer |
| `DuplicatePreviewService` | Exact + probable counts without commit |

**Verify pipeline:** Inspection → Mapping → Parse → Footer → Reconcile → Normalize → Duplicate preview → Update `import_verifications` JSON.

**Job:** `ProcessVerificationJob` (queued unless `CSV_VERIFICATION_SYNC=true`). Timeout from `CSV_VERIFICATION_JOB_TIMEOUT` (default **600s**). Horizon supervisor timeout should match (≥ job timeout; `retry_after` > timeout).

---

## CsvImports module

| Service / job | Responsibility |
|---------------|----------------|
| `ImportService` | `commitFromVerification(token)` — create import, promote file; `finalizeQueuedCommit()` — rows/masters/versions |
| `ProcessImportJob` | Queued finalize for large files (center-scoped); timeout from `csv_imports.job_timeout_seconds` |
| `ImportRowService` | Stream CSV → chunked bulk `import_rows` insert |
| `FileStorageService` | Temp → permanent private path |
| `ImportHistoryService` | Query imports for role scope |

**Commit pipeline:**

1. Lock token → exact-file-dupe check → create `imports` (`processing`) → promote file → mark verification imported
2. **`ProcessImportJob`** (or inline when `csv_imports.process_synchronously`): chunked rows → chunked master ledger → day comparisons → versions → summaries → terminal status
3. Result UI polls while `processing`

| Config | Default | Purpose |
|--------|---------|---------|
| `CSV_IMPORTS_SYNC` | `true` in local/testing | Finalize commit inline |
| `CSV_IMPORTS_JOB_TIMEOUT` | `600` | Job timeout (seconds) |
| `CSV_IMPORTS_ROW_CHUNK` | `500` | Bulk insert batch size |
| `CSV_IMPORTS_LEDGER_CHUNK` | `500` | Ledger `chunkById` size |

Scheduled WhatsApp summaries are dispatched separately by the scheduler (not during commit).

**Large-file note:** Stream parsing + queued/chunked commit supports 10k+ rows. Smoke (local, 2026-07-11): **3 000 rows** verified in ~0.2 s and committed in ~14 s.
---

## Normalization module

| Service | Responsibility |
|---------|----------------|
| `NormalizationService` | Apply `field_specific_v1` per field |
| `CanonicalHashService` | `exact_canonical_hash` + `raw_row_checksum` |
| `SimilarityFingerprintService` | Probable duplicate fingerprint |

---

## DuplicateDetection module

| Service | Responsibility |
|---------|----------------|
| `ExactDuplicateService` | Match against in-file and master ledger (optional preloaded historical map) |
| `ProbableDuplicateService` | Similarity matches for warnings |
| `MasterLedgerService` | Chunked ledger processing; batched historical hash lookup; unique-constraint-safe inserts |

---

## DailyVersions module

| Service | Responsibility |
|---------|----------------|
| `DailyDatasetService` | Build dataset per center+date |
| `VersionComparisonService` | New / unchanged / revision required |
| `RevisionService` | Propose, approve, reject |
| `ActiveSnapshotService` | Activate approved version |
| `SummaryGenerationService` | Regenerate daily_summaries |

---

## Dashboards module

| Service | Responsibility |
|---------|----------------|
| `OwnerDashboardService` | Selected active-center stats |
| `ManagerDashboardService` | Center-scoped stats |
| `CashierDashboardService` | Compact today stats |

All query **active daily snapshots** only.

---

## Reports module

| Service | Responsibility |
|---------|----------------|
| `ReportQueryService` | Daily/weekly/monthly/yearly/custom — active center for Owner |
| `ExportService` | Queue CSV/Excel/PDF generation |

---

## WhatsApp module

| Service | Responsibility |
|---------|----------------|
| `WhatsAppNotificationService` | Build scheduled summary payload, idempotency key, queue send |
| `WhatsAppScheduledSummaryService` | Resolve due cadences, aggregate period stats |
| `DispatchScheduledWhatsAppSummariesCommand` | Minute scheduler entry point |
| `OperatingCalendarService` / `SubmissionStatusService` | `isOperatingDay` gate for **daily** sends only |
| `WhatsAppCloudApiClient` | Meta API HTTP |
| `WebhookProcessorService` | Delivery status updates — **only when** webhook verify token is configured (REQ-096) |

---

## AuditLogging module

| Service | Responsibility |
|---------|----------------|
| `AuditService` | Record events per plan.md §33 |

---

## SystemSettings module

| Service | Responsibility |
|---------|----------------|
| `SettingsService` | Org, WhatsApp number, defaults |
| `HeaderAliasService` | Owner-approved alias CRUD |

---

## Cross-cutting

| Concern | Implementation |
|---------|----------------|
| Center scope | `CenterScope` Eloquent global scope + middleware |
| Authorization | Policies per model |
| Money | `Brick\Money` or decimal casts |
| Idempotency | DB unique constraints + application locks |

---

## Related

- [data-model.md](../design/data-model.md)
- [csv-verification-flow.md](../design/csv-verification-flow.md)
