# ADR 0004: MySQL and Redis stack

**Status:** Accepted  
**Date:** 2026-06-27

## Context

Relational financial data with strict constraints; background processing for CSV and WhatsApp.

## Decision

- **MySQL 8** primary database — utf8mb4_unicode_ci
- **Redis** for cache, sessions, queues
- **Laravel Horizon** for queue monitoring
- **Private disk** for CSV and exports

## Consequences

- VPS must run MySQL + Redis (Docker Compose in S8)
- Unique constraints enforce duplicate prevention at DB level

## Related

- [data-model.md](../../design/data-model.md)
