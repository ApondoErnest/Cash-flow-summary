# Production Acceptance Criteria

[← Documentation hub](../README.md) | [implementation-sequence.md](../implementation-sequence.md) | [owner-active-center.md](../design/owner-active-center.md)

**54 criteria** for first production release. Owner formal sign-off required (Step 108 — UAT).

Step checkpoints: [implementation-sequence.md Appendix C](../implementation-sequence.md#appendix-c--acceptance-criteria-by-checkpoint).

---

## Core (1–36)

| # | Criterion | Steps | REQ / reference |
|---|-----------|-------|-----------------|
| 1 | Owner creates and manages centers | 52–58 | REQ-020 |
| 2 | Owner creates Manager and Cashier accounts | 52–58 | REQ-006 |
| 3 | Each non-owner user has one assigned center | 25–39 | REQ-003 |
| 4 | Manager and Cashier never manually select a center | 32–39 | REQ-004 |
| 5 | Cross-center access blocked | 32–39 | REQ-005 |
| 6 | French CSV verifies and imports correctly | 43–51, 72–79 | REQ-030, REQ-031 |
| 7 | English CSV verifies and imports correctly | 43–51, 72–79 | REQ-031, REQ-032 |
| 8 | Regitration misspelling supported | 43–51 | REQ-032 |
| 9 | Mixed-language headers rejected | 43–51 | REQ-031, BR-015 |
| 10 | Footer record counts reconcile | 43–51 | REQ-055 |
| 11 | HT, VAT, TTC reconcile | 43–51 | REQ-054, REQ-055 |
| 12 | Zero-value rows remain valid | 43–51 | REQ-053 |
| 13 | Negative amounts rejected | 43–51 | REQ-053a |
| 14 | Unfinished rows remain valid | 43–51 | REQ-052 |
| 15 | 3-step flow: Select → Verify → Import/Reject | 72–89 | REQ-040 |
| 16 | Reject deletes temp data | 43–51, 72–79 | REQ-043, REQ-101 |
| 17 | Verification token expires correctly | 43–51 | REQ-047 |
| 18 | Exact duplicates in file ignored | 43–51, 65–71 | REQ-062 |
| 19 | Historical exact duplicates ignored | 43–51, 65–71 | REQ-063 |
| 20 | Plate normalization per field_specific_v1 | 43–51, 65–71 | REQ-060, BR-016 |
| 21 | Probable duplicates flagged, not auto-deleted | 43–51, 72–79 | REQ-067, ADR 0008 |
| 22 | DB prevents concurrent duplicate masters | 59–71 | REQ-066 |
| 23 | Overlapping files do not double-count revenue | 65–71, 90–93 | REQ-072 |
| 24 | Changed days create proposed revisions | 65–79 | REQ-071 |
| 25 | Only Owner approves revisions | 72–79 | REQ-007 |
| 26 | Reports use active daily snapshots only | 65–71, 90–93 | REQ-072, REQ-086 |
| 27 | Manager center-locked dashboard and import | 80–85 | REQ-081, US-M01 |
| 28 | Cashier compact dashboard and import | 86–89 | REQ-082 |
| 29 | Midnight Finance theme applied | 19–24 | NFR-001 |
| 30 | Historical import: WhatsApp off unless opted in | 72–79, 94–98 | REQ-048, BR-014 |
| 31 | WhatsApp idempotent | 94–98 | REQ-093 |
| 32 | WhatsApp failure does not reverse import | 94–98 | REQ-094 |
| 33 | Audit without rejected CSV body | 43–51, 99–102 | REQ-100 |
| 34 | Docker deploy smoke tests pass | 109–111 | NFR-006 |
| 35 | Backup restore verified | 115–117 | backup-monitoring.md |
| 36 | Heroicons through Flux; no banned icon libs | 19–24 | NFR-002 |

---

## Owner active-center (37–54)

| # | Criterion | Steps | REQ / reference |
|---|-----------|-------|-----------------|
| 37 | Owner login redirects to Center Selection | 52–58 | REQ-024a |
| 38 | Only org active centers listed | 52–58 | REQ-024 |
| 39 | No All Centers operational option | 52–58 | REQ-024b, BR-019 |
| 40 | Open Center opens that center's dashboard | 52–58 | REQ-024g, BR-020 |
| 41 | Active center prominent in header | 52–58 | REQ-024d |
| 42 | Owner switches center via dropdown | 52–58 | REQ-024d |
| 43 | Operational pages show active center only | 52–58, 72–79 | REQ-024c |
| 44 | CSV uses active center automatically | 72–79 | REQ-024e, BR-021 |
| 45 | Reports scoped to active center | 90–93 | REQ-083 |
| 46 | Revisions scoped to active center | 72–79 | REQ-071 |
| 47 | Center switch does not alter queued import center | 65–71 | REQ-024f, BR-022 |
| 48 | Owner accesses center/user admin org-wide | 52–58 | permission-matrix |
| 49 | Manage Centers shows no combined financial totals | 52–58 | owner-active-center §8 |
| 50 | Missing active center redirects to selection | 52–58 | REQ-024c |
| 51 | Inactive active center cleared from session | 52–58 | REQ-024h |
| 52 | Manager/Cashier never see Owner center dropdown | 32–39 | REQ-024j |
| 53 | Cross-center URL tampering blocked | 32–120 | REQ-005, US-X02 |
| 54 | Audit records correct center | 99–102 | REQ-100 |

---

## Step checkpoint summary

Minimum criteria before proceeding past each checkpoint (see [implementation-sequence.md](../implementation-sequence.md) Appendix C):

| After step | Checkpoint group | Minimum AC |
|------------|------------------|------------|
| 24 | Design shell | 29, 36 |
| 39 | Auth & security | 1–5, 52 |
| 51 | Verification backend | 6–21, 16–17, 33 |
| 58 | Owner admin UI | 1–2, 37–42, 48–49, 50 |
| 71 | Financial backend | 18–19, 22–23, 26, 47 |
| 79 | Owner operational UI | 15–16, 24–25, 41–47 |
| 85 | Manager UI | 4, 27, 53 |
| 89 | Cashier UI | 28 |
| 93 | Reports & exports | 26, 45 |
| 98 | WhatsApp | 30–32 |
| 102 | Security & audit | 33, 54 |
| 105 | Automated tests | 1–54 (automated) |
| 108 | UAT | 1–54 (sign-off) |
| 111 | Docker | 34 |
| 117 | Backup | 35 |
| 120 | Production rollout | 1–54 (production) |

---

## Sign-off

| Role | Name | Approval | Date |
|------|------|----------|------|
| Business Owner | | | |
| Lead Developer | | | |
