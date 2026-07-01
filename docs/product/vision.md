# Product Vision

[← Documentation hub](../README.md) | [plan.md](../../plan.md)

---

## Problem

Vehicle technical inspection centers export cashier statements as CSV files from an external system. The Owner needs reliable cash-flow visibility **per center**, switching explicitly between centers, without duplicate counting or lost import history.

## Goals

- Single source of truth for accepted daily financial snapshots per center
- Fast, trustworthy CSV verification before any data is committed
- Exact duplicate prevention across all time for each center
- Owner-controlled corrections via revision approval
- Compact dashboards tailored to each role
- Timely WhatsApp summaries without exposing customer PII

## Non-goals (v1)

- Replacing the external inspection application
- Public registration or customer portals
- Email notifications
- Microservices architecture
- Multi-step import wizard
- Manual editing of imported line items

## Users

| Persona | Need |
|---------|------|
| Owner | Per-center operations via active center selection; organization admin |
| Center Manager | Center operations, corrections, local reports |
| Cashier | Simple daily upload with clear verify/import/reject |

See [personas.md](personas.md).

## Success metrics

- Zero duplicate master records in production
- Import verification under acceptable time for typical files
- Owner receives one WhatsApp per event (idempotent)
- All acceptance criteria signed off before rollout
