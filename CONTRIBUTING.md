# Contributing

## Repository

- Default branch: **`main`**
- Initialized: Step 10 (2026-07-01)
- Never commit: `.env`, real CSV files, WhatsApp tokens, or credentials (see `.gitignore`)

## Documentation phase

Steps 1–9 were documentation-only. Application code begins at Step 13.

## When application code begins

Sprint 1 application code started at **Step 13** (Laravel scaffold).

1. Branch from `main` for each sprint or feature
2. Link PRs to `REQ-xxx` IDs where applicable
3. Update migrations only with matching [data-model.md](docs/design/data-model.md) changes
4. Run Pest before opening PR
5. Never commit secrets, real CSV samples, or `.env`

## Pull requests

Use [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md).

## Doc change rules

| Change type | Update |
|-------------|--------|
| Business rule | [business-rules.md](docs/product/business-rules.md), [requirements.md](docs/product/requirements.md), [calculations.md](docs/design/calculations.md) |
| CSV format | [csv-specification.md](docs/design/csv-specification.md), fixtures in test-strategy |
| Schema | [data-model.md](docs/design/data-model.md), ADR if architectural |
| UX / theme | [design-system.md](docs/design/design-system.md), [ux-overview.md](docs/design/ux-overview.md) |
| Permission | [permission-matrix.md](docs/product/permission-matrix.md) |
| Irreversible decision | New or updated ADR in [docs/architecture/decisions/](docs/architecture/decisions/) |

## Sensitive data

- Real cashier CSV files stay in **private storage** only
- Test fixtures must be **sanitized** (`tests/fixtures/csv/`)
- WhatsApp tokens and Owner phone number in **admin settings**, not Git
