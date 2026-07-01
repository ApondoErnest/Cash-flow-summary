# Local Development Setup

[← Documentation hub](../README.md) | **Sprint 1**

**Status:** Responsive shell (Step 24). Phase 3 checkpoint passed — Step 25 next.

---

## Prerequisites

| Software | Version | Purpose |
|----------|---------|---------|
| PHP | 8.2+ | Laravel |
| Composer | 2.x | PHP dependencies |
| MySQL | 8.0+ | Database |
| Redis | 7+ | Queues, cache |
| Node.js | 20 LTS | Vite |
| npm | 10+ | Asset build |
| Git | 2.x | Version control |

### PHP extensions

`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`

### Verified environment (Step 11 — 2026-07-01)

| Software | Required | Verified |
|----------|----------|----------|
| Git | 2.x | 2.50.1 |
| PHP | 8.2+ | 8.5.6 |
| Composer | 2.x | 2.9.8 |
| MySQL | 8.0+ | 9.6.0 (service running) |
| Redis | 7+ | 8.8.0 (`PONG`) |
| Node.js | 20 LTS | 26.0.0 |
| npm | 10+ | 11.12.1 |
| PHP extensions | All listed above | All present |

Re-run checks:

```bash
git --version && php -v && composer -V && node -v && npm -v && mysql --version && redis-cli ping
php -m | grep -E 'bcmath|ctype|curl|dom|fileinfo|json|mbstring|openssl|pdo_mysql|tokenizer|xml'
```

### Verified environment (Step 12 — 2026-07-01)

| Item | Status |
|------|--------|
| Database `cashflow_summary` | Created (utf8mb4) |
| User `cashflow_app`@`localhost` | Created with local dev grants |
| App user connection test | `SELECT 1` OK |
| Redis | `PONG` |
| Local credentials file | `.env.local` (gitignored — optional local override) |

### Verified environment (Step 13 — 2026-07-01)

| Item | Status |
|------|--------|
| Laravel Framework | 13.18.0 |
| `php artisan --version` | OK |
| MySQL connection (`db:show`) | `cashflow_summary` via `cashflow_app` |
| Project docs preserved | `docs/`, `plan.md`, project `README.md` |

### Verified environment (Step 14 — 2026-07-01)

| Package | Version |
|---------|---------|
| livewire/livewire | 4.3.3 |
| livewire/flux | 2.15.0 |
| tailwindcss | 4.3.2 |
| vite | 8.1.2 |

| Check | Result |
|-------|--------|
| `npm run build` | OK |
| Blade + Flux layout | `resources/views/components/layouts/app.blade.php` |
| Flux CSS in Tailwind | `@import` in `resources/css/app.css` |

### Verified environment (Step 15 — 2026-07-01)

| Package | Version |
|---------|---------|
| laravel/horizon | 5.47.2 |
| pestphp/pest | 4.7.4 |
| pestphp/pest-plugin-laravel | 4.1.0 |

| Check | Result |
|-------|--------|
| `redis-cli ping` | `PONG` |
| Laravel Redis facade | OK |
| `php artisan test` (Pest) | 2 passed |
| Horizon | Connected (supervisor running) |
| Local `.env` | `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis` |

Run Horizon locally: `php artisan horizon` (separate terminal)

### Verified environment (Step 16 — 2026-07-01)

| Item | Status |
|------|--------|
| `.env.example` | Project template committed (MySQL, Redis, timezone, verification TTL) |
| Secrets | `DB_PASSWORD` and `APP_KEY` left empty in example |
| First-time setup | `cp .env.example .env` → set `DB_PASSWORD` → `php artisan key:generate` |

### Verified environment (Step 17 — 2026-07-01)

| Item | Status |
|------|--------|
| `app/Modules/` | 13 modules × 4 subfolders (`Services`, `Models`, `Livewire`, `Jobs`) |
| Module index | [`app/Modules/README.md`](../../app/Modules/README.md) |
| PSR-4 autoload | `App\` → `app/` (includes `App\Modules\...`) |

### Verified environment (Step 18 — 2026-07-01)

| Item | Status |
|------|--------|
| CI workflow | [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) |
| `php artisan test` | 2 passed |
| `npm run build` | OK |
| Redis / Horizon | `PONG`; Horizon supervisor available locally |
| **Checkpoint (Steps 13–18)** | Passed — stack installation complete |

### Verified environment (Step 19 — 2026-07-01)

| Item | Status |
|------|--------|
| Design tokens | [`resources/css/app.css`](../../resources/css/app.css) `@theme` |
| Fonts | Inter (`font-sans`), Manrope (`font-display`) via Vite |
| Flux accent | Mapped to `emerald-brand` |
| `npm run build` | OK |
| Token preview | `/` welcome page |

### Verified environment (Step 20 — 2026-07-01)

| Item | Status |
|------|--------|
| Icon source | Heroicons via `livewire/flux` only |
| Banned packages | None in `composer.json` / `package.json` |
| UI demo | Feature-mapping icons on `/` |
| Policy tests | `tests/Feature/IconPolicyTest.php` |

### Verified environment (Step 21 — 2026-07-01)

| Item | Status |
|------|--------|
| App shell | [`resources/views/components/layouts/shell.blade.php`](../../resources/views/components/layouts/shell.blade.php) |
| Sidebar | Midnight navy (`midnight-sidebar`) |
| Top bar | Active center label + user actions |
| Content | `flux:main` on `app-bg` |
| Mobile | Collapsible sidebar via `flux:sidebar.toggle` |
| Tests | `tests/Feature/AppShellLayoutTest.php` |

### Verified environment (Step 22 — 2026-07-01)

| Item | Status |
|------|--------|
| Role nav registry | [`app/Support/Navigation/RoleNavigation.php`](../../app/Support/Navigation/RoleNavigation.php) |
| Roles | Owner (15 items), Manager (6), Cashier (3) |
| Preview | `?role=owner\|manager\|cashier` or `NAV_PREVIEW_ROLE` |
| Placeholder routes | [`routes/navigation.php`](../../routes/navigation.php) |
| Tests | `tests/Feature/RoleNavigationTest.php` |

### Verified environment (Step 23 — 2026-07-01)

| Item | Status |
|------|--------|
| UI components | [`resources/views/components/ui/`](../../resources/views/components/ui/) |
| Patterns | Card, stat-card, button, table-panel, status-badge |
| Button variants | primary, secondary, approval, destructive |
| Tests | `tests/Feature/DesignSystemComponentsTest.php` |

### Verified environment (Step 24 — 2026-07-01)

| Item | Status |
|------|--------|
| Mobile sidebar | `collapsible="mobile"` + toggle + backdrop |
| Header | Sticky on mobile; icon-only actions below `sm` |
| Main padding | `p-4` → `p-6` → `p-8` breakpoints |
| Page layout | `x-ui.page` with stacked cards on mobile |
| Tests | `tests/Feature/ResponsiveShellTest.php` |
| **Phase 3 checkpoint** | Passed (Steps 19–24) |

---

## Database setup (Step 12)

Creates database `cashflow_summary` and application user `cashflow_app` with DML-only grants (no DDL — migrations use an admin connection or the same user if you grant CREATE later; for local dev, extend grants if `migrate` fails).

### 1. Choose a local password

Pick a strong password for `cashflow_app`. **Do not commit it** — it goes only in your local `.env` (Step 16).

### 2. Edit the SQL script

Open [`scripts/create-local-database.sql`](../../scripts/create-local-database.sql) and replace:

```
CHANGE_ME_LOCAL_PASSWORD
```

with your chosen password.

### 3. Run as MySQL admin (you run this — not automated)

```bash
mysql -u root -p < scripts/create-local-database.sql
```

Use your MySQL **root** password when prompted. If your admin user is not `root`, substitute accordingly.

### 4. Verify (you run this)

```bash
mysql -u cashflow_app -p -e "SHOW DATABASES LIKE 'cashflow_summary'; USE cashflow_summary; SELECT 1 AS ok;"
```

Expected: database listed and `ok = 1`.

Also confirm Redis still responds:

```bash
redis-cli ping
```

Expected: `PONG`.

### 5. Configure `.env`

Copy [`.env.example`](../../.env.example) to `.env`, set `DB_PASSWORD`, run `php artisan key:generate`.

### Quick reference (inline SQL)

```sql
CREATE DATABASE cashflow_summary CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cashflow_app'@'localhost' IDENTIFIED BY '<strong-local-password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON cashflow_summary.* TO 'cashflow_app'@'localhost';
FLUSH PRIVILEGES;
```

### Troubleshooting

| Issue | Action |
|-------|--------|
| `Access denied for user 'root'` | Use correct admin password; try `mysql -u root -p` interactively first |
| `CREATE USER` syntax error | MySQL 8.0+ required (verified in Step 11) |
| `migrate` fails with DDL errors | Grant `CREATE`, `ALTER`, `DROP`, `INDEX` on `cashflow_summary.*` to `cashflow_app` for local dev, or run migrations as root |

---

## Installation (after Laravel scaffold)

```bash
git clone <repository-url>
cd Cashflow-Summary
composer install
cp .env.example .env
# Edit .env — set DB_PASSWORD (see Database setup above)
php artisan key:generate
```

Key variables are documented in [`.env.example`](../../.env.example) at the project root. Minimum local override after copy:

```env
DB_PASSWORD=<strong-local-password>
```

Then install assets and run:

```bash
npm install
npm run build
php artisan migrate --seed
php artisan horizon   # separate terminal
npm run dev           # separate terminal
php artisan serve
```

### Icons (Flux + Heroicons) — Step 20

Heroicons ship with Flux — **no** separate npm or Composer icon package.

Usage:

```blade
<flux:icon.home variant="outline" />
<flux:button icon="arrow-up-tray">Import</flux:button>
```

Lucide (only when Heroicons has no match):

```bash
php artisan flux:icon file-spreadsheet
```

Policy test: `php artisan test --filter=IconPolicy`. Banned: Font Awesome, Bootstrap Icons, Material Symbols, full Lucide bundle.

Visit `http://localhost:8000`.

---

## File storage (local)

```
storage/app/private/imports/     # Permanent CSV
storage/app/temp/verifications/ # Temp verify files
storage/app/exports/             # Generated exports
```

Ensure not symlinked to `public/`.

---

## Default seed accounts (S1)

| Role | Username | Notes |
|------|----------|-------|
| Owner | owner | Change password on first login |

Manager/Cashier created via Owner UI in S3.

---

## Verification TTL

Set in `.env.example` / `.env`:

```env
IMPORT_VERIFICATION_TTL_MINUTES=120
```

---

## Running tests

```bash
php artisan test
# or
./vendor/bin/pest
```

---

## Troubleshooting

| Issue | Check |
|-------|-------|
| Queue not processing | `php artisan horizon` running |
| Redis connection | `redis-cli ping` |
| Permission denied storage | `chmod -R ug+rwx storage bootstrap/cache` |
