# ADR 0007: WhatsApp Cloud API notifications

**Status:** Accepted  
**Date:** 2026-06-27

## Context

Owner needs timely import summaries. Email excluded from v1.

## Decision

- **Meta WhatsApp Cloud API** for external notifications to Owner
- Internal in-app notifications for all roles
- Owner phone number in **admin settings** (not Git)
- Idempotency via unique `idempotency_key` per business event
- WhatsApp failure does **not** roll back financial data

## Events

Successful import; with duplicates; duplicate-only file; revision pending/approved; financial mismatch; missing submission; delivery failure; consolidated daily summary.

**Historical imports:** suppressed by default; optional "Notify Owner" checkbox (BR-014).

## Content rules

Aggregate totals and metadata only — no customer or plate lists.

## Related

- [api/README.md](../../api/README.md)
- REQ-090–REQ-095
