# Implementation Roadmap

[← Documentation hub](../README.md) | **[implementation-sequence.md](../implementation-sequence.md)** | [plan.md](../../plan.md)

---

## How to build (start here)

Use the **chronological step guide** — work **Step 1 → Step 120** in strict order:

**[docs/implementation-sequence.md](../implementation-sequence.md)**

Complete **one step at a time**. Do not start Step N+1 until Step N is done. Pass each **checkpoint** before continuing past a step group.

### Quick chronological overview

| Steps | What | Status |
|-------|------|--------|
| 1–8 | Documentation | ✓ |
| 9 | Charter & requirements approval | ✓ |
| 10 | Git repository initialized | ✓ |
| 11 | Local prerequisites verified | ✓ |
| 12 | MySQL database + app user | ✓ |
| 13 | Laravel scaffold | ✓ |
| 14 | Livewire + Flux + Vite build | ✓ |
| 15 | Horizon + Pest + Redis drivers | ✓ |
| 16 | `.env.example` | ✓ |
| 17 | `app/Modules/` structure | ✓ |
| 18 | CI pipeline | ✓ |
| 19 | Midnight Finance tokens | ✓ |
| 20 | Heroicons via Flux | ✓ |
| 21 | App shell layout | ✓ |
| 22 | Role-based navigation | ✓ |
| 23 | Reusable UI patterns | ✓ |
| 24 | Responsive shell | ✓ |
| 25+ | Database Wave 1 | Next |
| 19–24 | **Frontend design system & shell** | |
| 25–31 | **Database** Wave 1 (admin) | |
| 32–39 | Auth & security foundation | |
| 40–42 | **Database** Wave 2 (verification) | |
| 43–51 | **Backend** CSV verification | |
| 52–58 | Owner admin **UI** | |
| 59–64 | **Database** Wave 3 (financial) | |
| 65–71 | **Backend** import & financial core | |
| 72–79 | Owner operational **UI** | |
| 80–85 | Manager **UI** | |
| 86–89 | Cashier **UI** | |
| 90–93 | Reports & exports | |
| 94–98 | WhatsApp | |
| 99–120 | Security, tests, UAT, Docker, VPS, rollout | |

---

## Sprint overview (planning rollup)

Sprints group steps for planning. **Step order wins** when they differ.

| Sprint | Steps | Checkpoint gate |
|--------|-------|-----------------|
| **S1 — Foundation** | 9–39 | Step 39 — auth & isolation |
| **S2 — Verification backend** | 40–51 | Step 51 — verify service |
| **S3 — Owner admin** | 52–58 | Step 58 — Center Selection; CRUD |
| **S4 — Owner financial UI** | 72–79 (with 43–51, 65–71) | Step 79 — verify → import E2E |
| **S5 — Financial core** | 59–71, 72–79, 90–93 | Step 71 — import backend |
| **S6 — Manager** | 80–85 | Step 85 — center-locked E2E |
| **S7 — Cashier** | 86–89 | Step 89 — compact E2E |
| **S8 — Production** | 94–120 | Step 120 — production live |

---

## Dependency rules

1. **Frontend shell** (Step 24) before feature pages (Step 52+)
2. **Database** migration before UI using those tables
3. **Verification backend** (Step 51) before CSV UI (Step 72+)
4. **Financial backend** (Step 71) before import UI commit path (Step 72+)
5. **Owner operational UI** (Step 79) before Manager/Cashier (Steps 80+)
6. **UAT** (Step 108) before Docker (Step 109)

Full rules: [implementation-sequence.md](../implementation-sequence.md#dependency-rules-never-violate)

---

## Interface delivery order

Owner admin (Steps 52–58) → Owner operational (72–79) → Manager (80–85) → Cashier (86–89). ADR [0010](../architecture/decisions/0010-owner-first-delivery.md).

Shared `CsvVerificationCard` built at Step 72; reused in Steps 80+ and 86+.

---

## Current status

| Item | Status |
|------|--------|
| Steps 1–24 | Complete — **Phase 3 checkpoint passed** |
| **Next step** | **Step 25** — Review ERD ([data-model.md](../design/data-model.md)) |
