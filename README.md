# Cash Flow Summary

Professional financial consolidation and reporting for vehicle technical inspection centers.

**Status:** Step 18 complete — **Step 19** (Midnight Finance tokens) is next

## Local development

Laravel **13.18** scaffolded (Step 13). From project root:

```bash
composer install   # if needed
cp .env.example .env && php artisan key:generate   # first-time only; use .env.local for DB creds
php artisan serve
```

See [docs/operations/setup.md](docs/operations/setup.md).

## Purpose

Each inspection center uses an external operational application that exports cashier-statement CSV files in French or English. Cash Flow Summary lets authorized users verify and import those files, prevent duplicate financial records, preserve import history, generate statistics, and send WhatsApp summaries to the Owner.

## Key capabilities (planned)

- Multi-center consolidation with strict center isolation
- **3-step CSV flow:** Select → Verify → Import or Reject
- Bilingual CSV import with automatic header detection
- Field-specific exact duplicate detection (`field_specific_v1`)
- Probable duplicate review (informational)
- Daily versioning, revision comparison, and Owner approval
- Compact role-based dashboards (Midnight Finance)
- Owner **active-center** workflow — one center at a time for operations
- Reports from **active daily snapshots only**
- WhatsApp notifications via Meta Cloud API (no email)
- Full audit trail and immutable import evidence

## Documentation

| Document | Description |
|----------|-------------|
| [docs/implementation-sequence.md](docs/implementation-sequence.md) | **Chronological build order** — start here for development |
| [plan.md](plan.md) | Master spec (sections 1–43) |
| [docs/README.md](docs/README.md) | Documentation hub and reading order |
| [docs/product/requirements.md](docs/product/requirements.md) | Traceable requirements (REQ-xxx) |
| [docs/design/owner-active-center.md](docs/design/owner-active-center.md) | Owner active-center architecture |
| [docs/design/design-system.md](docs/design/design-system.md) | Midnight Finance theme |
| [docs/design/data-model.md](docs/design/data-model.md) | Database schema |

## Tech stack (confirmed)

- **Backend:** Laravel 13, Livewire 4, Flux UI 2, PHP 8.3+, Pest 4, Horizon 5
- **Frontend:** Flux UI + Tailwind 4 + Vite + Heroicons (via Flux) + Chart.js (later)
- **Data:** MySQL 8, Redis, Horizon
- **Deploy:** Local → Docker Compose → secured Ubuntu VPS

## Roles

| Role | Scope |
|------|-------|
| Owner | All centers (admin); **one active center** for operations |
| Center Manager | One assigned center |
| Cashier | One assigned center; simplified upload |

## License

[MIT](LICENSE)
