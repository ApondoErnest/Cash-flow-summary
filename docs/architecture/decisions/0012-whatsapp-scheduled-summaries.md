# ADR 0012: Scheduled WhatsApp activity summaries

**Status:** Accepted  
**Date:** 2026-07-10  
**Supersedes (partial):** [0007-whatsapp.md](0007-whatsapp.md) — per-import send behaviour

## Context

Owners previously received a WhatsApp message after every import commit. That produced too many messages when managers upload frequently. Owners still need aggregate visibility without customer PII.

Imports, daily versions, and summaries already exist. Meta template `import_activity_summary` supports the required aggregate fields.

## Decision

Replace **per-import** WhatsApp notifications with **scheduled activity summaries** per center.

### Cadences

| Cadence | When sent | Period summarized |
|---------|-----------|-------------------|
| **Daily** | Every **operating day** at center time | That day **00:00 through send time** (`APP_TIMEZONE`). **Skipped** on non-operating days per center calendar (BR-009, BR-010, BR-026). |
| **Weekly** | Every **Saturday** | Monday–Saturday of that week (inclusive) |
| **Monthly** | Last calendar day of month | First–last day of that month |
| **Yearly** | **31 December** | 1 January–31 December of that year |

All cadences use the **same Meta template** (`import_activity_summary`) and seven body parameters. `event_type` on `whatsapp_messages` distinguishes cadence for history and idempotency.

### Operating calendar (daily only)

Daily summaries are sent **only on operating days** configured by the Owner in the center **operating calendar** (`is_open` per weekday + holiday/closure/special_open exceptions). Same semantics as missing-submission checks. Weekly, monthly, and yearly summaries are **not** gated by the weekly `is_open` flag on the send date (e.g. a Saturday weekly summary still sends even if Saturday is normally closed).

### Per-center send time

Each center stores one **local send time** (`HH:MM`, 24-hour). The scheduler evaluates times in `APP_TIMEZONE` (see [whatsapp-scheduled-summaries.md](../../design/whatsapp-scheduled-summaries.md)).

Owner configures the time in **Manage Centers** (center edit form). Organization-level WhatsApp credentials (phone, token) remain in **WhatsApp Settings**.

### Import behaviour

- **No WhatsApp job** is queued from `ImportService` after commit.
- Imports, verification, `notify_owner` on historical uploads, and import result pages remain; only the outbound WhatsApp trigger moves to the scheduler.
- Historical `notify_owner` no longer triggers an immediate WhatsApp message (BR-014 updated).

### Idempotency

One message per center per cadence per period:

```
{daily_summary|weekly_summary|monthly_summary|yearly_summary}:center:{center_id}:{period_key}
```

Examples: `daily_summary:center:3:2026-07-09`, `weekly_summary:center:3:2026-W28`, `monthly_summary:center:3:2026-07`, `yearly_summary:center:3:2026`.

### Failure handling

Unchanged from ADR 0007: queue retries, failure does not roll back financial data, webhook delivery status when configured.

## Consequences

- Requires Laravel scheduler (`schedule:run` every minute in production) and queue worker.
- On days when multiple cadences align (e.g. 31 Dec on a Saturday), up to **four** messages may send at the configured time.
- Import-linked rows in WhatsApp history become legacy; new rows use `center_id` + period in `payload_summary`, `import_id` nullable.

## Related

- [whatsapp-scheduled-summaries.md](../../design/whatsapp-scheduled-summaries.md)
- [api/README.md](../../api/README.md)
- REQ-097–REQ-101 (planned)
