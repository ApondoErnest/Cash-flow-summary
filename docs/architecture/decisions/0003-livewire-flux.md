# ADR 0003: Livewire and Flux UI frontend

**Status:** Accepted  
**Date:** 2026-06-27

## Context

Three role-specific interfaces with forms, tables, and real-time verify/import status. Team prefers PHP-first stack.

## Decision

- Livewire 3 for interactive UI
- Flux UI component library
- Tailwind CSS with Midnight Finance tokens
- Vite for asset build
- Chart.js for dashboard trends
- **Heroicons through Flux UI** as primary icon system (no separate icon package at start)
- **Lucide** only via `php artisan flux:icon` for individual missing icons (~10% max)
- **Not used:** Font Awesome, Bootstrap Icons, Material Symbols

## Icon policy

See [design-system.md](../../design/design-system.md) Icons section for variant rules and feature mapping.

## Consequences

- No separate SPA to maintain
- Livewire state discipline required (see ADR 0009)

## Related

- [design-system.md](../../design/design-system.md)
