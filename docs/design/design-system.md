# Midnight Finance Design System

[← Documentation hub](../README.md) | [ux-overview.md](ux-overview.md)

**Theme:** Midnight Finance — financial trust, professional control, clarity.

---

## Design quality standard (project-wide)

**Every screen in Cash Flow Summary must look professional, beautiful, and visually cohesive.** This is not optional polish at the end — it applies to every step from Step 33 onward and to all roles (Owner, Manager, Cashier).

### What “good design” means here

| Principle | Practice |
|-----------|----------|
| **Color blend** | Use palette tokens (`midnight-navy`, `emerald-brand`, `gold-brand`, `app-bg`, `surface`) — never raw `#fff` blocks sitting on flat gray without intent |
| **Hierarchy** | Clear label → value → context; headings in Manrope, body in Inter |
| **Surfaces** | Cards and forms use subtle borders, soft shadows, and tinted backgrounds — not harsh white-on-white |
| **Focus & states** | Emerald focus rings; hover/focus/disabled states on all interactive elements |
| **Consistency** | Reuse `x-ui.*` components and existing CSS patterns (`mf-*` classes in `resources/css/app.css`) before inventing one-off styles |
| **Guest + auth** | Login and post-login shell must feel like the same product (reference: login page styling, Step 32) |

### Anti-patterns (reject in review)

- Default Flux/Laravel white inputs on white panels with no token tuning
- Random colors outside the palette
- Inconsistent spacing or typography between pages
- Shipping a functional page that “works” but looks unfinished

### Before marking any UI step complete

1. Compare against [design-system.md](design-system.md) tokens and components
2. Check desktop **and** mobile viewport
3. Verify EN **and** FR ([i18n.md](i18n.md)) — layout must not break in either language
4. Visually confirm colors **blend** with the navy sidebar / app shell

**Reference implementation:** login page (`resources/views/livewire/authentication/login.blade.php`, `.mf-login-*` in `app.css`).

---

## Color palette

| Token | Hex | Tailwind suggestion | Use |
|-------|-----|---------------------|-----|
| `midnight-navy` | `#14213D` | custom | Sidebar, top nav, Owner sections |
| `emerald` | `#0F766E` | `teal-700` base | Primary buttons, active nav, links |
| `warm-gold` | `#D6A756` | custom | Owner accents, key totals |
| `app-bg` | `#F5F7FA` | `slate-50` | Page background |
| `surface` | `#FFFFFF` | `white` | Cards, tables, forms |

### Text

| Role | Hex |
|------|-----|
| Heading | `#111827` |
| Body | `#4B5563` |
| Muted | `#6B7280` |
| Disabled | `#9CA3AF` |

### Status

| State | Hex |
|-------|-----|
| Success | `#15803D` |
| Warning | `#D97706` |
| Error | `#B91C1C` |
| Info | `#2563EB` |

---

## Typography

| Use | Font | Notes |
|-----|------|-------|
| Headings | Manrope | `font-display` |
| Body / UI | Inter | `font-sans` |
| Money | Inter + `tabular-nums` | All HT/VAT/TTC figures |

---

## Buttons

| Variant | Style | Use |
|---------|-------|-----|
| Primary | Emerald bg, white text | Verify, Import, Save, Create |
| Secondary | White bg, navy border | Cancel, Return, View, Download |
| Owner approval | Navy bg, white text, gold accent | Approve revision |
| Destructive | Red outline or red bg | Reject, Delete |

Import button: primary emerald with loading spinner and disabled state during processing.

---

## Links

Emerald text; darker on hover; underline; visible focus ring.

---

## Cards

- White background, subtle border (`border-slate-200`)
- Light shadow (`shadow-sm`)
- Rounded (`rounded-lg`)
- Compact padding (`p-4`–`p-6`)
- Strong hierarchy: label → figure → context

Avoid oversized empty cards on dashboards.

---

## Icons

### Primary: Heroicons through Flux UI

Heroicons is the **only** general-purpose icon system at project start. Flux UI uses Heroicons natively — no separate icon package to install or configure when Flux is part of the Livewire starter kit.

**Why Heroicons + Flux**

- Native integration with Flux buttons, inputs, navigation, breadcrumbs, badges, and menus
- Outline, solid, mini, and micro variants
- Clean style aligned with Midnight Finance
- Consistent with Tailwind / Livewire stack

### Variant rules

| Variant | Use |
|---------|-----|
| **Outline** | Sidebar navigation, tables, filters, normal actions |
| **Mini / micro** | Compact buttons, badges, table row actions |
| **Solid** | Selected navigation item, important status cards, primary dashboard highlights |

Do **not** mix outline and solid on the same control. Do **not** mix multiple icon libraries on the same page.

**Target:** Heroicons for **≥ 90%** of interface icons.

### Feature mapping (Heroicons)

| Application feature | Icon name |
|---------------------|-----------|
| Dashboard | `home` or `squares-2x2` |
| Centers | `building-office-2` |
| Users | `users` |
| Cashier | `banknotes` |
| CSV imports | `arrow-up-tray` |
| Cash-flow records | `document-currency-dollar` |
| Reports | `chart-bar-square` |
| Daily versions | `clock` |
| Revision approvals | `check-badge` |
| Duplicate records | `document-duplicate` |
| Warnings | `exclamation-triangle` |
| In-app notifications | `bell` |
| WhatsApp / messages | `chat-bubble-left-right` |
| Audit logs | `clipboard-document-list` |
| Settings | `cog-6-tooth` |
| Search | `magnifying-glass` |
| Filter | `funnel` |
| Export | `arrow-down-tray` |
| Logout | `arrow-right-start-on-rectangle` |

### Import page actions (Heroicons)

| Action | Icon | Variant |
|--------|------|---------|
| Verify | `shield-check` | outline on secondary; solid when active |
| Import confirm | `check-circle` | solid on primary emerald button |
| Reject | `x-circle` | outline on destructive control |

Use Flux icon components (e.g. `<flux:icon name="arrow-up-tray" />`) — do not paste raw SVG paths inline except for brand icons.

### Secondary: Lucide (selected imports only)

When Heroicons has no suitable icon, Flux can import **individual** Lucide icons:

```bash
php artisan flux:icon
# or specify only what you need:
php artisan flux:icon file-spreadsheet scan-line
```

Rules:

- Import **only** the icons required — never the full Lucide collection
- Use Lucide for specialized gaps (e.g. spreadsheet/export variants) after confirming no Heroicon fits
- Keep Lucide usage under ~10% of total icons

### Brand icons

Custom SVG Blade components **only** for brand-specific marks (e.g. official WhatsApp logo). Brand icons must not define the visual style of the rest of the UI.

### Libraries not used

| Library | Reason |
|---------|--------|
| Font Awesome | Large bundle; less consistent with Tailwind/Flux |
| Bootstrap Icons | Bootstrap-oriented; wrong stack fit |
| Material Symbols | Distinct Google/Material look; conflicts with premium financial theme |
| Multiple full icon sets | Style inconsistency; harder maintenance |

### Implementation checklist (Sprint 1)

1. ✓ Heroicons via Flux confirmed (Step 20 — no extra npm/composer icon packages)
2. Document any Lucide imports in `CHANGELOG` when added
3. Add `WhatsAppIcon` custom SVG component in S8 (WhatsApp sprint)
4. Code review: reject PRs that add Font Awesome, Bootstrap Icons, or Material Symbols

---

## Layout

- Sidebar: midnight navy, white/emerald nav items
- Main content: app-bg
- 12-column grid on desktop
- Responsive: stack cards 1–2 per row on mobile

---

## Flux UI

Use Flux components for forms, tables, modals, badges, dropdowns. Extend with Tailwind tokens above.

---

## Chart.js

- Owner: one wide revenue trend (daily/weekly/monthly toggle)
- Manager: one compact center trend
- Cashier: no chart on main dashboard

Colors: emerald primary series; muted grid; navy axis labels.

---

## Tailwind tokens (Tailwind CSS v4)

Implemented in [`resources/css/app.css`](../../resources/css/app.css) via `@theme`:

| Token utility | Hex |
|---------------|-----|
| `midnight-navy` | `#14213D` |
| `emerald-brand` | `#0F766E` |
| `gold-brand` | `#D6A756` |
| `app-bg` | `#F5F7FA` |
| `surface` | `#FFFFFF` |
| `text-heading` / `text-body` / `text-muted` / `text-disabled` | see Text table above |
| `status-success` / `warning` / `error` / `info` | see Status table above |

Fonts loaded via Vite (`Inter` → `font-sans`, `Manrope` → `font-display`). Money figures use `@utility tabular-money`.

Flux accent maps to `emerald-brand` in `:root`.

Legacy Tailwind v3 config excerpt (reference only):

```js
// tailwind.config.js excerpt
theme: {
  extend: {
    colors: {
      midnight: { DEFAULT: '#14213D' },
      emerald: { brand: '#0F766E' },
      gold: { brand: '#D6A756' },
    },
    fontFamily: {
      display: ['Manrope', 'sans-serif'],
      sans: ['Inter', 'sans-serif'],
    },
  },
},
```
