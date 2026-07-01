# Documentation Hub

**Doc set version:** 2.0.15  
**Last reviewed:** 2026-07-01  
**Project phase:** Steps 1–18 complete — **Step 19** (design tokens) next | Doc v2.0.15

[README](../README.md) | [plan.md](../plan.md)

---

## Reading order

### For building the application (chronological)

1. **[implementation-sequence.md](implementation-sequence.md)** — start here; **Steps 1–120** in order (one step at a time)
2. [setup.md](operations/setup.md) — when starting Step 11
3. Step-specific docs linked inside each step (design-system → Steps 19–24, data-model → Steps 25+, etc.)

### For understanding the product (reference)

1. [plan.md](../plan.md) — master spec
2. [owner-active-center.md](design/owner-active-center.md) — Owner active-center model
3. [governance/project-charter.md](governance/project-charter.md) — scope
4. [product/business-rules.md](product/business-rules.md) — confirmed rules
5. [product/requirements.md](product/requirements.md) — REQ-xxx
6. [design/design-system.md](design/design-system.md) — Midnight Finance
7. [design/csv-verification-flow.md](design/csv-verification-flow.md) — import UX
8. [design/data-model.md](design/data-model.md) — database
9. [architecture/overview.md](architecture/overview.md) — architecture
10. [testing/acceptance-criteria.md](testing/acceptance-criteria.md) — sign-off

### Legacy topic order (optional deep dive)

---

## Documentation map

### Governance

| Document | Description |
|----------|-------------|
| [project-charter.md](governance/project-charter.md) | Charter, scope, risks |
| [change-control.md](governance/change-control.md) | Change request procedure |
| [stakeholder-register.md](governance/stakeholder-register.md) | Roles and responsibilities |
| [risk-register.md](governance/risk-register.md) | Project risks |

### Product

| Document | Description |
|----------|-------------|
| [vision.md](product/vision.md) | Problem, goals, non-goals |
| [personas.md](product/personas.md) | Owner, Manager, Cashier |
| [requirements.md](product/requirements.md) | Functional and non-functional requirements |
| [user-stories.md](product/user-stories.md) | Role-based user stories |
| [permission-matrix.md](product/permission-matrix.md) | Granular permissions |
| [business-rules.md](product/business-rules.md) | Confirmed business rules |
| [glossary.md](product/glossary.md) | Domain terminology |
| [roadmap.md](product/roadmap.md) | Sprint summary → links to implementation-sequence |
| [implementation-sequence.md](implementation-sequence.md) | **Chronological build order (Steps 1–120)** |

### Design

| Document | Description |
|----------|-------------|
| [owner-active-center.md](design/owner-active-center.md) | Owner active-center session and UI |
| [design-system.md](design/design-system.md) | Midnight Finance — colors, typography, icons |
| [csv-verification-flow.md](design/csv-verification-flow.md) | Select → Verify → Import/Reject |
| [ux-overview.md](design/ux-overview.md) | Layout, dashboards, navigation |
| [csv-specification.md](design/csv-specification.md) | Headers, footer, encoding |
| [normalization-policy.md](design/normalization-policy.md) | Exact duplicate field rules |
| [import-statuses.md](design/import-statuses.md) | Verification, import, row enums |
| [data-model.md](design/data-model.md) | Tables, constraints, migration waves |
| [calculations.md](design/calculations.md) | Financial rules, hashing |

### Architecture

| Document | Description |
|----------|-------------|
| [overview.md](architecture/overview.md) | Layers, modules, queues |
| [backend-services.md](architecture/backend-services.md) | Service catalogue |
| [security-privacy.md](architecture/security-privacy.md) | Auth, isolation, files |
| [decisions/](architecture/decisions/) | ADRs 0001–0011 |

### Operations

| Document | Description |
|----------|-------------|
| [setup.md](operations/setup.md) | Local development |
| [deployment.md](operations/deployment.md) | Docker, VPS |
| [backup-monitoring.md](operations/backup-monitoring.md) | Backup and monitoring |

### Testing

| Document | Description |
|----------|-------------|
| [test-strategy.md](testing/test-strategy.md) | Pest, fixtures, coverage |
| [acceptance-criteria.md](testing/acceptance-criteria.md) | Production acceptance (54 criteria) |

### API

| Document | Description |
|----------|-------------|
| [README.md](api/README.md) | Meta WhatsApp Cloud API |

---

## Documentation ownership

| Topic | Primary doc | Also update |
|-------|-------------|-------------|
| Scope / v1 boundary | plan.md | vision.md, roadmap.md |
| Business rule | business-rules.md | requirements.md, calculations.md |
| CSV format | csv-specification.md | csv-verification-flow.md, test-strategy.md |
| Verification flow | csv-verification-flow.md | ux-overview.md, ADR 0009 |
| Normalization | normalization-policy.md | calculations.md, ADR 0005 |
| Schema | data-model.md | requirements.md, backend-services.md |
| Owner active center | owner-active-center.md | ux-overview, permission-matrix, ADR 0011 |
| Permissions | permission-matrix.md | requirements.md, security-privacy.md |
| Theme / UI | design-system.md | ux-overview.md |
| Stack decision | ADR in decisions/ | overview.md, setup.md |
| Implementation order | implementation-sequence.md | roadmap.md, plan.md §37–38 |

---

## Architecture Decision Records

| ADR | Title | Status |
|-----|-------|--------|
| [0001](architecture/decisions/0001-doc-first.md) | Doc-first restart | Accepted |
| [0002](architecture/decisions/0002-modular-monolith.md) | Modular Laravel monolith | Accepted |
| [0003](architecture/decisions/0003-livewire-flux.md) | Livewire + Flux UI | Accepted |
| [0004](architecture/decisions/0004-mysql-redis.md) | MySQL and Redis | Accepted |
| [0005](architecture/decisions/0005-exact-duplicate-ledger.md) | Exact duplicate ledger | Accepted |
| [0006](architecture/decisions/0006-daily-versioning.md) | Daily versioning | Accepted |
| [0007](architecture/decisions/0007-whatsapp.md) | WhatsApp Cloud API | Accepted |
| [0008](architecture/decisions/0008-probable-duplicates.md) | Probable duplicates | Accepted |
| [0009](architecture/decisions/0009-verification-token-flow.md) | Verification token flow | Accepted |
| [0010](architecture/decisions/0010-owner-first-delivery.md) | Owner-first delivery | Accepted |
| [0011](architecture/decisions/0011-owner-active-center.md) | Owner active-center session | Accepted |

---

## Delivery status

| Sprint | Documentation | Code |
|--------|---------------|------|
| S1 — Foundation | Complete | No |
| S2 — Verification backend | Specified | No |
| S3–S8 | Specified | No |
