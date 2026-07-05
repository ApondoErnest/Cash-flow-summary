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

## Configuration tiers

| Tier | When | Settings | Capabilities |
|------|------|----------|--------------|
| **Outbound only** | Local dev, UAT, Meta test number | Owner phone, phone number ID, access token | Send template messages; track `queued` → `sent` / outbound `failed` |
| **Full integration** | Production deployment | Above + webhook verify token + Meta webhook URL | Inbound webhooks; `delivered`, `read`, `failed` status updates |

Meta’s WhatsApp **test number** does not expose webhook verify token configuration. The Owner admin UI treats webhook verify token as **optional** so outbound testing is not blocked. Production **must** configure webhooks and the verify token for delivery lifecycle tracking.

Implementation: when `whatsapp.webhook_verify_token` is absent, skip webhook registration and ignore delivery/read/failed inbound events; when present, enable Step 97 webhook endpoint and status processing per [api/README.md](../../api/README.md).

## Related

- [api/README.md](../../api/README.md)
- REQ-090–REQ-096
