# ADR 0002: Modular Laravel monolith

**Status:** Accepted  
**Date:** 2026-06-27

## Context

Multi-center financial consolidation needs strong transactions, single deployment unit, and low VPS cost.

## Decision

One Laravel application with `app/Modules/{Name}/` boundaries. No microservices in v1.

## Modules

Authentication, Centers, Users, CsvVerification, CsvImports, Normalization, DuplicateDetection, DailyVersions, Dashboards, Reports, WhatsApp, AuditLogging, SystemSettings.

## Consequences

- Simpler ops than distributed system
- Module discipline required to prevent coupling

## Related

- [overview.md](../overview.md) section Modules
