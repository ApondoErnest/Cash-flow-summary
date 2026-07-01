# Changelog

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.15] - 2026-07-01

### Added

- **Step 18** — [`.github/workflows/ci.yml`](.github/workflows/ci.yml) (Pest + Vite build on push/PR to `main`)

### Changed

- [test-strategy.md](docs/testing/test-strategy.md) — CI section links to workflow
- **Checkpoint Steps 13–18 passed** (stack installation complete)
- Status docs updated; **Step 19** next

[2.0.15]: #2015---2026-07-01

## [2.0.14] - 2026-07-01

### Added

- **Step 17** — `app/Modules/` with 13 domain modules (Services, Models, Livewire, Jobs subfolders)
- [`app/Modules/README.md`](app/Modules/README.md) — module index and namespace conventions

### Changed

- Status docs updated; **Step 18** next

[2.0.14]: #2014---2026-07-01

## [2.0.13] - 2026-07-01

### Changed

- **Step 16 complete** — [`.env.example`](.env.example) aligned with project stack (MySQL, Redis, Africa/Douala, verification TTL)
- [setup.md](docs/operations/setup.md) — installation flow references `.env.example`
- Status docs updated; **Step 17** next

[2.0.13]: #2013---2026-07-01

## [2.0.12] - 2026-07-01

### Added

- **Step 15** — Laravel Horizon 5.47.2, Pest 4.7.4 + Laravel plugin
- `tests/Pest.php` — Pest harness initialized
- `config/horizon.php` — Horizon scaffolding

### Changed

- Local `.env` — `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` set to `redis` (ADR 0004)
- Status docs updated; **Step 16** next

[2.0.12]: #2012---2026-07-01

## [2.0.11] - 2026-07-01

### Added

- **Step 14** — `livewire/flux` 2.15.0 (includes Livewire 4.3.3)
- Flux layout component (`resources/views/components/layouts/app.blade.php`)
- Minimal Flux welcome page for stack verification

### Changed

- `resources/css/app.css` — Flux CSS import, dark variant, view sources
- Tailwind upgraded to **4.3.2**; `npm run build` verified
- Status docs updated; **Step 15** next

[2.0.11]: #2011---2026-07-01

## [2.0.10] - 2026-07-01

### Added

- Laravel **13.18.0** application scaffold (Step 13) — merged into repo preserving `docs/`, `plan.md`, project docs

### Changed

- `.env` configured for local MySQL (from `.env.local`); full `.env.example` at Step 16
- [README.md](README.md), [CONTRIBUTING.md](CONTRIBUTING.md), [setup.md](docs/operations/setup.md) — application phase started
- Status docs updated; **Step 14** next

[2.0.10]: #2010---2026-07-01

## [2.0.9] - 2026-07-01

### Added

- `scripts/create-local-database.sql` — local MySQL setup script (Step 12)
- `.env.local` — gitignored local DB credentials (copy to `.env` at Step 16)

### Changed

- **Step 12 complete** — `cashflow_summary` database and `cashflow_app` user verified; Redis `PONG`
- [setup.md](docs/operations/setup.md) — Step 12 instructions and verified environment
- `.gitignore` — `.env.local`
- Status docs updated; **Step 13** next

[2.0.9]: #209---2026-07-01

## [2.0.8] - 2026-07-01

### Changed

- **Step 11 complete** — local prerequisites verified (PHP, Composer, MySQL, Redis, Node, Git, PHP extensions)
- [setup.md](docs/operations/setup.md) — verified environment table and re-check commands
- Status docs updated; **Step 12** next

[2.0.8]: #208---2026-07-01

## [2.0.7] - 2026-07-01

### Added

- Git repository on branch `main` (Step 10)
- `.gitignore` — Laravel defaults plus private import/export paths

### Changed

- [CONTRIBUTING.md](CONTRIBUTING.md) — repository section
- Status docs updated; **Step 11** next

[2.0.7]: #207---2026-07-01

## [2.0.6] - 2026-07-01

### Changed

- **Step 9 complete** — charter and requirements approved for development kickoff
- [project-charter.md](docs/governance/project-charter.md) — Step 9 approval record; corrected acceptance criteria count (54)
- [implementation-sequence.md](docs/implementation-sequence.md), [roadmap.md](docs/product/roadmap.md), [README.md](README.md), [docs/README.md](docs/README.md), [plan.md](plan.md) — status updated; **Step 10** next

[2.0.6]: #206---2026-07-01

## [2.0.5] - 2026-06-27

### Changed

- [implementation-sequence.md](docs/implementation-sequence.md) — **Steps 1–120** linear build order (one step at a time); phases retained as step groups with checkpoints
- [roadmap.md](docs/product/roadmap.md), [plan.md](plan.md) §37–40 — step-based authority; removed parallel work streams
- [acceptance-criteria.md](docs/testing/acceptance-criteria.md), [user-stories.md](docs/product/user-stories.md) — **Steps** column replaces Phase
- [requirements.md](docs/product/requirements.md) appendix — step ranges per group
- [test-strategy.md](docs/testing/test-strategy.md), [data-model.md](docs/design/data-model.md) — step references

[2.0.5]: #205---2026-06-27

## [2.0.4] - 2026-06-27

### Added

- **Phase column** on all REQ/NFR in [requirements.md](docs/product/requirements.md) with appendix index
- **Phase column** on acceptance criteria (54 items) and phase gate summary
- **Phase column** on user stories with phase mapping table
- REQ/AC blocks per phase in [implementation-sequence.md](docs/implementation-sequence.md) Appendices A & B

### Changed

- Corrected REQ phase assignments (e.g. CSV UI → Phase 11, financial backend → Phase 10)
- [data-model.md](docs/design/data-model.md) migration waves labeled Phases 4, 6, 9
- [test-strategy.md](docs/testing/test-strategy.md) tests mapped to phases
- [plan.md](plan.md) §37 links to REQ traceability appendix

[2.0.4]: #204---2026-06-27

## [2.0.3] - 2026-06-27

### Added

- [implementation-sequence.md](docs/implementation-sequence.md) — chronological Phases 0–22 (project setup → design shell → database → backend → UI → production)

### Changed

- [roadmap.md](docs/product/roadmap.md) — points to implementation-sequence as primary build guide
- [plan.md](plan.md) §37–38 — chronological phases replace mixed checklist order
- [docs/README.md](docs/README.md) — separate "building" vs "understanding" reading orders

[2.0.3]: #203---2026-06-27

## [2.0.2] - 2026-06-27

### Added

- [owner-active-center.md](docs/design/owner-active-center.md) — Owner active-center architecture
- ADR 0011 — Owner active-center session
- Business rules BR-019–BR-022
- Requirements REQ-024a–REQ-024j
- Acceptance criteria 37–54 (Owner active-center)

### Changed

- **Removed** consolidated Owner dashboard and center-comparison report from v1 scope
- Owner login → Center Selection → active center → selected-center dashboard
- CSV import uses active session center (no picker on card)
- [ux-overview.md](docs/design/ux-overview.md), [permission-matrix.md](docs/product/permission-matrix.md), [plan.md](plan.md) §4, §18, §43
- ADR 0010 amended for active-center flow

[2.0.2]: #202---2026-06-27

## [2.0.1] - 2026-06-27

### Changed

- [design-system.md](docs/design/design-system.md) — expanded Heroicons-through-Flux policy, variant rules, Lucide `flux:icon` workflow, excluded libraries
- [plan.md](plan.md) §15, ADR 0003, REQ NFR-002, [setup.md](docs/operations/setup.md) — aligned with icon strategy

[2.0.1]: #201---2026-06-27

## [2.0.0] - 2026-06-27

### Added

- Complete documentation restart v2 from Revised Professional Technical Implementation Document
- **3-step CSV workflow** (Select → Verify → Import/Reject) replacing 7-step wizard
- **Midnight Finance** design system ([design-system.md](docs/design/design-system.md))
- [csv-verification-flow.md](docs/design/csv-verification-flow.md) with verification token model
- Active normalization policy **`field_specific_v1`** ([normalization-policy.md](docs/design/normalization-policy.md))
- `import_verifications` entity for temporary pre-import data
- 8 delivery sprints with gates in [plan.md](plan.md) sections 39–40
- ADRs 0001–0010 including verification-token flow and owner-first delivery
- [acceptance-criteria.md](docs/testing/acceptance-criteria.md) — production sign-off checklist
- Shared `CsvVerificationCard` Livewire component pattern (documented in architecture)

### Changed

- Interface delivery order: Owner → Manager → Cashier
- Compact dashboard principles for all roles
- Business rules consolidated in [business-rules.md](docs/product/business-rules.md)

[2.0.0]: #200---2026-06-27
