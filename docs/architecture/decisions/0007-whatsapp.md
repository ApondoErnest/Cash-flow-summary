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

**Scheduled activity summaries** (ADR [0012](0012-whatsapp-scheduled-summaries.md)) — daily, weekly (Saturday), monthly (last day), yearly (31 December). Per-center send time configured by Owner. **No per-import WhatsApp.**

Legacy per-import event types (`import_success`, etc.) may appear on historical `whatsapp_messages` rows only.

Future (not in scope): revision pending/approved; financial mismatch; missing submission; delivery failure alerts.

**Historical imports:** no immediate WhatsApp; activity rolls into scheduled summaries (BR-014 updated).

## Content rules

Aggregate totals and metadata only — no customer or plate lists.

**Template parameters:** center name, period, inspection count, category summary (A–D counts for the period), HT/VAT/TTC — seven body fields via Meta template `import_activity_summary`. See [api/README.md](../../api/README.md#import-template-import_activity_summary).

## Configuration tiers

| Tier | When | Settings | Capabilities |
|------|------|----------|--------------|
| **Outbound only** | Local dev, UAT, Meta test number | Owner phone, phone number ID, access token | Send template messages; track `queued` → `sent` / outbound `failed` |
| **Full integration** | Production deployment | Above + webhook verify token + Meta webhook URL | Inbound webhooks; `delivered`, `read`, `failed` status updates |

Meta’s WhatsApp **test number** does not expose webhook verify token configuration. The Owner admin UI treats webhook verify token as **optional** so outbound testing is not blocked. Production **must** configure webhooks and the verify token for delivery lifecycle tracking.

Implementation: when `whatsapp.webhook_verify_token` is absent, skip webhook registration and ignore delivery/read/failed inbound events; when present, enable Step 97 webhook endpoint and status processing per [api/README.md](../../api/README.md).

## Related

- [api/README.md](../../api/README.md)
- [0012-whatsapp-scheduled-summaries.md](0012-whatsapp-scheduled-summaries.md)
- REQ-090–REQ-096, REQ-097–REQ-101
