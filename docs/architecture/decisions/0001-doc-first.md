# ADR 0001: Documentation-first restart

**Status:** Accepted  
**Date:** 2026-06-27

## Context

Repository restarted from zero with Revised Professional Technical Implementation Document v2. Building without aligned requirements risks duplicate-counting bugs, center isolation failures, and irreversible schema mistakes.

## Decision

Complete documentation v2.0.0 before Laravel implementation. No application code until Sprint 1 begins after doc review.

## Consequences

- Slower start to coding; fewer rework cycles
- All requirements traceable via REQ-xxx

## Related

- [plan.md](../../plan.md)
- [requirements.md](../../product/requirements.md)
