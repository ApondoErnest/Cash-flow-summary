# User Stories

[← Documentation hub](../README.md) | [implementation-sequence.md](../implementation-sequence.md) | [owner-active-center.md](../design/owner-active-center.md)

Build order: **Steps 1–120** in [implementation-sequence.md](../implementation-sequence.md). **Steps** column shows when each story is implemented.

---

## Owner

| ID | Story | Steps | Acceptance |
|----|-------|-------|------------|
| US-O01 | As Owner, I log in with 2FA | 32–39, 52–58 | 2FA enroll and verify |
| US-O02 | As Owner, I am redirected to Center Selection after login | 52–58 | Only active org centers listed |
| US-O03 | As Owner, I open a center and see its dashboard | 52–58, 72–79 | Title includes center name |
| US-O04 | As Owner, I switch centers from the header dropdown | 52–58 | Dashboard reloads for new center |
| US-O05 | As Owner, I import CSV for the active center without picking center again | 72–79 | "Importing for: {center}" shown |
| US-O06 | As Owner, I reject a verified file | 43–51, 72–79 | No import record |
| US-O07 | As Owner, I approve a revision for active center | 65–71, 72–79 | Snapshot updates |
| US-O08 | As Owner, I manage centers without seeing combined financial totals | 52–58 | Open Center sets active |
| US-O09 | As Owner, I configure WhatsApp in settings | 52–58, 94–98 | Not in Git |
| US-O10 | As Owner without active center, I cannot open operational pages | 52–58 | Redirect to Center Selection |
| US-O11 | As Owner, I create first center when none exist | 52–58 | Empty state on selection page |

---

## Center Manager

| ID | Story | Steps | Acceptance |
|----|-------|-------|------------|
| US-M01 | As Manager, I see my center without selector | 32–39, 80–85 | No center dropdown |
| US-M02 | As Manager, I verify and import CSV | 72–79, 80–85 | 3-step flow |
| US-M03 | As Manager, I submit correction | 65–71, 80–85 | Pending Owner approval |
| US-M04 | As Manager, I cannot access another center URL | 32–39, 80–85 | 403 |

---

## Cashier

| ID | Story | Steps | Acceptance |
|----|-------|-------|------------|
| US-C01 | As Cashier, I see compact dashboard | 86–89 | Minimal nav |
| US-C02 | As Cashier, I verify and import | 72–79, 86–89 | Footer TTC prominent |
| US-C03 | As Cashier, I never see Owner center dropdown | 32–39, 86–89 | Not in UI |

---

## Cross-cutting

| ID | Story | Steps | Acceptance |
|----|-------|-------|------------|
| US-X01 | As Owner switching center, in-flight import keeps original center | 65–71 | Job uses import.center_id |
| US-X02 | As Owner, tampered center_id in URL blocked | 32+ | 403 or redirect |

---

## Step mapping

| Steps | Stories |
|-------|---------|
| 32–39 | US-M01, US-C03, US-X02 (partial) |
| 43–51 | US-O06 (verify/reject backend) |
| 52–58 | US-O01–02, US-O08, US-O09–11, US-O04 (session) |
| 65–71 | US-O07, US-M03, US-X01 |
| 72–79 | US-O03–05, US-O07 (UI), US-M02 |
| 80–85 | US-M01–04 |
| 86–89 | US-C01–03 |
