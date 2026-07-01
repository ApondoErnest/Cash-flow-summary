# Application modules

Modular monolith layout per [ADR 0002](../../docs/architecture/decisions/0002-modular-monolith.md) and [architecture overview](../../docs/architecture/overview.md).

**Namespace:** `App\Modules\{ModuleName}\...`  
**Path:** `app/Modules/{ModuleName}/`

## Subfolders (per module)

| Folder | Purpose |
|--------|---------|
| `Services/` | Application services ([backend-services.md](../../docs/architecture/backend-services.md)) |
| `Models/` | Eloquent models scoped to the module |
| `Livewire/` | Livewire components |
| `Jobs/` | Queued jobs |

Shared HTTP middleware stays in `app/Http/Middleware/`. Shared policies may live in `app/Policies/` until module-specific policies are added.

## Modules

| Module | Responsibility |
|--------|----------------|
| Authentication | Login, 2FA, sessions, password policy |
| Centers | CRUD, operating calendar, active-center context |
| Users | CRUD, role assignment, center binding |
| CsvVerification | Temp storage, token, verify pipeline |
| CsvImports | Permanent import commit, history |
| Normalization | `field_specific_v1` canonical values |
| DuplicateDetection | Exact hash, similarity fingerprint |
| DailyVersions | Versioning, snapshots, revisions |
| Dashboards | Role-specific aggregate queries |
| Reports | Active-snapshot reports, exports |
| WhatsApp | Cloud API, idempotent queue |
| AuditLogging | Immutable audit events |
| SystemSettings | Org, WhatsApp, header aliases |

Add code to the module that owns the bounded context — avoid cross-module imports except through documented service interfaces.
