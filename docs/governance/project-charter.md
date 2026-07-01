# Project Charter

[← Documentation hub](../README.md) | [plan.md](../../plan.md)

**Version:** 2.0.0 | **Date:** 2026-06-27

---

## Project name

Cash Flow Summary

## Objective

Build a secure web application that consolidates cashier-statement CSV data from multiple vehicle technical inspection centers, prevents duplicate revenue, versions daily financial data, and provides Owner-level oversight with WhatsApp notifications.

## Stakeholders

See [stakeholder-register.md](stakeholder-register.md).

## Scope (v1)

- Three roles: Owner, Center Manager, Cashier
- Bilingual CSV import (French/English) via 3-step verify flow
- Exact duplicate prevention with `field_specific_v1` normalization
- Daily versioning with Owner-approved revisions
- Compact role-based dashboards (Midnight Finance theme)
- Reports and exports from active daily snapshots
- WhatsApp-only external notifications
- Audit logging
- Local → Docker → VPS deployment path

## Out of scope (v1)

See [plan.md](../../plan.md) section 42.

## Success criteria

- No duplicate master records under concurrent import
- Center isolation enforced on all routes and exports
- Owner approves all financial revisions
- 54 production acceptance criteria met ([acceptance-criteria.md](../testing/acceptance-criteria.md))

## Major risks

| Risk | Mitigation |
|------|------------|
| Duplicate race on concurrent upload | DB unique constraint + transactions |
| Report double-counting | Active snapshot-only reporting |
| WhatsApp API failures | Idempotent queue; failure does not reverse import |
| Sensitive CSV in Git | Private storage; sanitized fixtures only |
| Scope creep | Change control + v1 boundary |

## Approval authority

Business Owner approves: requirements, architecture, UX, UAT, production rollout.

## Delivery model

8 sprints per [roadmap.md](../product/roadmap.md) and [plan.md](../../plan.md) section 39.

---

## Step 9 approval — charter and requirements

**Implementation step:** 9  
**Approved for development kickoff:** 2026-07-01  
**Approver:** Project team (Business Owner name: _TBD_ — see [stakeholder-register.md](stakeholder-register.md))

### Verification checklist

| Item | Document | Verified |
|------|----------|----------|
| Project charter | This document | ✓ |
| Stakeholder register | [stakeholder-register.md](stakeholder-register.md) | ✓ |
| Change control | [change-control.md](change-control.md) | ✓ |
| Risk register | [risk-register.md](risk-register.md) | ✓ |
| Requirements (REQ/NFR) | [requirements.md](../product/requirements.md) | ✓ |
| Business rules | [business-rules.md](../product/business-rules.md) | ✓ |
| Permission matrix | [permission-matrix.md](../product/permission-matrix.md) | ✓ |
| Acceptance criteria (54 items) | [acceptance-criteria.md](../testing/acceptance-criteria.md) | ✓ |
| v1 scope boundary | [plan.md](../../plan.md) §42 | ✓ |

### Approval statement

The charter, requirements, business rules, permissions, and acceptance criteria are **approved** as the baseline for Sprint 1 implementation. Changes after this date follow [change-control.md](change-control.md).

**Next step:** [Step 19](../implementation-sequence.md) — Tailwind tokens (Midnight Finance)
