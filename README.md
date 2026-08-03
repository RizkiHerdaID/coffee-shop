# Coffee Shop

> Run a specialty coffee shop from one self-hosted app: a bilingual public website, a full point-of-sale with shift reconciliation and Z-reports, and owner tools for inventory, suppliers, and P&L — built on Laravel 13 + Filament 5, running anywhere Docker does.

[![CI](https://github.com/RizkiHerdaID/coffee-shop/actions/workflows/tests.yml/badge.svg)](https://github.com/RizkiHerdaID/coffee-shop/actions/workflows/tests.yml)
[![tests](https://img.shields.io/badge/tests-575_passing-brightgreen)](https://github.com/RizkiHerdaID/coffee-shop/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Filament](https://img.shields.io/badge/Filament-5-f43f5e)](https://filamentphp.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Laravel Sail](https://img.shields.io/badge/Laravel_Sail-Docker-2496ED?logo=docker&logoColor=white)](https://laravel.com/docs/sail)

Coffee Shop is a complete platform for a single-store coffee shop. Instead of a website, a third-party POS subscription, a spreadsheet for stock, and a separate accounting tool, everything lives in one Laravel application and one PostgreSQL database: customers order from a QR table menu, the cashier rings up sales with shift-safe totals, and the owner closes the day to a Z-report that already knows the expected cash. Built for the Indonesian market first — Indonesian-first copy with full English support, QRIS and e-wallet payments, `Rp 25.000` formatting, and WhatsApp as the ordering and alerting channel — but every concept transfers to any single-store cafe.

## Table of Contents

- [Why Coffee Shop?](#why-coffee-shop)
- [Feature Highlights](#feature-highlights)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Quick Start](#quick-start)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)
- [Acknowledgements](#acknowledgements)

## Why Coffee Shop?

- **One catalog, one database.** The public menu, the QR table menu, and the POS read the same `menu_items` table — no dual entry, no sync jobs, no price drift between website and counter.
- **Close the day without arithmetic.** Open a shift with the float, take payments all day, count the drawer at closing — the Z-report computes expected cash (opening + cash paid − refunds + deposits − petty cash) and flags the discrepancy before you walk out.
- **No POS subscription.** The research compared Pawoon, Digipos, Moka, Majoo, Square and more — and rejected them all in favor of an in-house POS inside the Filament panel. Software cost for the counter: Rp0/month, with the POS hardware cost unchanged.
- **WhatsApp-first ordering.** Customers build a pickup cart on the menu page and send it as a `wa.me` message; the shop sends order confirmations and low-stock alerts through the same channel (Fonnte).
- **Inventory that moves itself.** Recipes link menu items to stock items, so every sale deducts ingredients automatically — and a low-stock WhatsApp alert fires before the weekend rush runs you dry.
- **Serious about quality.** 575 feature tests across 52 classes, gated by CI: the test workflow (Paratest + Pint) must pass before anything deploys.
- **One command to start developing.** Laravel Sail spins up the app, PostgreSQL, Redis, MinIO, and Mailpit — no local PHP or Node installation required.
- **A live production deployment.** The repo ships a real deploy workflow: tests gate an SSH deploy to the production VPS, and operations tooling (backups, restore drills, uptime checks, scheduler heartbeats) is part of the codebase, not an afterthought.

## Feature Highlights

### Public website — for your customers

- **Bilingual by default** — every page ships in Indonesian and English with a session-based switcher; Indonesian-first copy with full `lang/{id,en}/` coverage across the whole app, not just the site.
- **QR table menu** — `/qr/{table}` serves a compact, phone-width menu per table; the admin prints an SVG QR code per table (bacon/bacon-qr-code, no external API).
- **WhatsApp pickup ordering** — per-item stepper and a fixed cart bar that composes a localized `wa.me` message with `Rp` totals; sold-out items render greyed out with a "Habis" badge.
- **SEO that earns local traffic** — LocalBusiness (Cafe) JSON-LD with opening hours from config, Product `ItemList` structured data on the menu, sitemap and robots handling, OG/Twitter share images, and a keyless Google Maps embed.
- **Conversion features** — dismissible promo banners, testimonials with `aggregateRating` JSON-LD, dietary badge chips, loyalty points lookup at `/cek-poin` (10th drink free), and a reservation form at `/reservasi`.
- **Fast by construction** — Tailwind 4 with Vite, a self-hosted Instrument Sans webfont with preload, lazy-loaded photos with real dimensions, and no analytics or CDN dependencies.

### Point of sale — at the counter

- **Cashier flow in four taps** — menu grid → cart → order → payment → served, with order numbers like `ORD-20260803-0001`, per-line notes, and quick reorder of the last order.
- **Payments for the Indonesian market** — cash with tendered/change math, static QRIS display, and e-wallet with reference; partial and split payments across methods, plus fixed or percentage discounts.
- **Shift management** — open with a float, close with the counted drawer; one active shift enforced, mid-shift deposits and petty-cash movements tracked, and closing redirects straight to the Z-report.
- **Z-reports** — shift totals by payment method, cash check rendered `COCOK` when expected matches counted, printable from the panel or standalone at `/pos/z-report/{shift}`.
- **Refunds and voids that keep totals honest** — refunds write negative payment rows and are refused once a shift is closed, so the Z-report stays stable.
- **Printing** — queued `PrintReceipt` / `PrintKitchenTicket` jobs render 58 mm thermal output via ESC/POS (mike42/escpos-php), with a browser-print receipt fallback and WhatsApp order confirmations when a customer phone is on file.

### Owner tools — in the back office

- **Inventory** — stock items with unit costs and thresholds, `stockIn`/`stockOut` inside transactions that refuse negative quantities, full movement history, and wastage logging.
- **Recipes and COGS** — attach ingredients to menu items; the panel shows COGS/margin per item, every sale deducts recipe ingredients (lenient mode never blocks a sale), and the monthly P&L report uses real ingredient cost.
- **Suppliers and purchase orders** — supplier scorecards (spend, outstanding, lead time, on-time rate), POs with pending/received/cancelled states, one-click receiving that stocks items in, and restock suggestions.
- **Money** — expenses across eight categories, cash register sessions with live expected-vs-counted math, and a monthly P&L statement: revenue − COGS − expenses, with margins and inventory valuation.
- **Analytics** — eight dashboard widgets: today's revenue and order count, 14-day revenue line, top items, best sellers, a 7×24 peak-hours heatmap, payment split, low-stock count, and a demand-forecast chart (weekday + seasonality).
- **Automation** — hourly low-stock WhatsApp alerts (one message per episode, no spam), daily/weekly sales summary email, and CSV/XLSX exports on orders, stock, expenses, and purchase orders.
- **AI copywriting** — DeepSeek-backed generation of Indonesian menu descriptions and promo subtitles right inside the forms (`menu:generate-copy` fills everything at once).

### Quality engineering — under the hood

- **575 feature tests, 52 classes** — the POS (shifts, refunds, split payments, discounts, over-tender/change tracking, concurrency), the site (SEO, localization, pickup cart, points, robots), inventory, and every resource are covered; tests run on in-memory SQLite, so CI needs no database service.
- **CI/CD** — `.github/workflows/tests.yml` (PHP 8.4, real Vite build, Paratest × 4 processes, Pint) gates `.github/workflows/deploy.yml`, which triggers the production deploy via SSH; Dependabot watches composer, npm, and GitHub Actions weekly.
- **Operations built in** — automated `pg_dump` backups (14 daily + 8 weekly, optional S3 copy), a restore-drill script that verifies restorability, a `/up` health endpoint, and scheduler heartbeats that catch a silently dead cron. See [Operations](docs/ops.md).
- **Observability** — Laravel Pulse is installed and gated to the admin guard; queued jobs write structured logs.

## Tech Stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13 (PHP 8.4) · Livewire 4 |
| Admin panel | Filament 5 — 12 resources, 5 pages, 8 dashboard widgets |
| Frontend | Blade · Tailwind CSS 4 · Vite 8 |
| Database | PostgreSQL 18 (single source of truth) |
| Cache / queue | Redis 7.4 + dedicated queue worker container |
| Storage | MinIO (S3-compatible) · Mailpit for dev mail |
| Printing | mike42/escpos-php (58 mm ESC/POS thermal) |
| QR codes | bacon/bacon-qr-code |
| Payments | Cash · static QRIS · e-wallet (dynamic QRIS via gateway is on the roadmap) |
| WhatsApp | Fonnte gateway — order confirmations, low-stock alerts, pickup ordering |
| AI copy | DeepSeek API (`DEEPSEEK_API_KEY`-gated) |
| Tests | PHPUnit 12 · Paratest (parallel) · Laravel Pint |
| Dev environment | Docker via Laravel Sail — app, queue, pgsql, redis, minio, mailpit |

## Architecture

Laravel serves the public marketing site from Blade views (Tailwind 4, Vite-bundled) and hosts the entire back office as a Filament v5 panel at `/admin` — including the POS, which is a set of custom Filament pages rather than a third-party service. All business state (menu, orders, payments, shifts, stock, suppliers, expenses) lives in one PostgreSQL database, so the website, the cashier, and the owner reports always see the same numbers. Queued jobs (thermal printing, WhatsApp messages, summary mail) run on a Redis-backed worker; the scheduler drives daily/weekly summaries and low-stock alerts.

- Public routes: `routes/web.php` — `PageController` (home, menu, contact, QR menu, reservations, loyalty points)
- POS: `app/Filament/Pages/` — `Cashier`, `ManageShift`, `ShiftReport`, `PnlReport`, `QrCodes`
- Shift math: `App\Models\Shift` (`active()`, `salesTotal()`, `expectedCash()`, `discrepancy()`)
- Feature walkthroughs and decisions: see [Documentation](#documentation)

## Quick Start

Requirements: Docker with the Compose plugin. No local PHP or Node needed — everything runs in containers.

```bash
git clone https://github.com/RizkiHerdaID/coffee-shop.git
cd coffee-shop

cp .env.example .env            # defaults are fine for local dev
./vendor/bin/sail up -d         # first boot: builds the image, generates APP_KEY, runs migrations
./vendor/bin/sail artisan db:seed
```

First boot is fully automated by `docker-entrypoint.sh`: it generates `APP_KEY` if empty, waits for PostgreSQL, runs `migrate --force`, and caches config and views before starting the app.

| URL | What you get |
| --- | --- |
| `http://localhost` | Public site (home, menu, contact, QR menu) |
| `http://localhost/admin` | Filament panel — cashier, inventory, reports |
| `http://localhost:8025` | Mailpit — inspect captured mail |
| `http://localhost:8900` | MinIO console — object storage |

The seeder creates a demo admin account from `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD`, plus an 8-item menu. Fallbacks: `Admin` / `admin@example.com` / `password`.

> **Change `ADMIN_*` in `.env` before seeding anything facing real traffic.**

After changing views or Tailwind classes, rebuild assets:

```bash
./vendor/bin/sail npm run build
```

## Testing

```bash
./vendor/bin/sail artisan test                     # full suite, 575 tests
./vendor/bin/sail paratest --no-coverage --processes 8   # same suite, ~4.5× faster
```

The suite runs against in-memory SQLite and needs no external services. CI runs the same tests with Paratest (`--processes 4`) and enforces `vendor/bin/pint --test` on every push and pull request.

## Project Structure

```
app/
├── Console/Commands/     # menu:generate-copy, summary:send, stock:alert-low
├── Enums/                # OrderStatus, PaymentMethod, ExpenseCategory, ...
├── Filament/
│   ├── Auth/             # custom login with email+IP rate limiting
│   ├── Pages/            # Cashier, ManageShift, ShiftReport, PnlReport, QrCodes
│   ├── Resources/        # MenuItems, Orders, StockItems, Suppliers, PurchaseOrders, Expenses, ...
│   └── Widgets/          # TodayStats, RevenueChart, PeakHoursChart, DemandForecast, ...
├── Jobs/                 # PrintReceipt, PrintKitchenTicket, SendOrderConfirmation
├── Models/               # Admin, MenuItem, Order, Shift, StockItem, Expense, ...
├── Providers/Filament/   # AdminPanelProvider (panel config)
└── Services/             # WaPickupMessage, AiCopyService, FonnteWhatsApp, DemandForecastService
config/                   # shop.php, pos.php, whatsapp.php, summary.php, ...
docs/                     # feature docs + roadmap (see below)
lang/{id,en}/             # all user-facing copy — Indonesian-first
resources/views/          # Blade public site + Filament views
routes/web.php            # all public + POS routes
scripts/                  # db-backup.sh, restore-drill.sh
tests/Feature/            # 575 tests across 52 classes
```

## Documentation

- [`docs/README.md`](docs/README.md) — wiki index: `website.md` (public site), `pos.md` (POS, shifts, Z-reports), `owner-tools.md` (inventory, P&L, AI copy), `ops.md` (backups, monitoring, production notes), `roadmap.md` (backlog), `i18n/` (copy decisions)
- [`docs/roadmap.md`](docs/roadmap.md) — what shipped and what is planned (loyalty, dynamic QRIS, kitchen display, multi-branch ideas)
- [`AGENTS.md`](AGENTS.md) — environment quirks and conventions for anyone working in this repo

## Contributing

Contributions are welcome — the roadmap is public, and the project is built for real use, not as a demo. Good places to start:

1. Read [`docs/roadmap.md`](docs/roadmap.md) and pick an open item, or open an issue to propose something new.
2. Check [`AGENTS.md`](AGENTS.md) for environment quirks and conventions (they will save you real time).
3. Branch, implement, and open a pull request — CI runs the full suite plus Pint automatically, so keep tests green and formatting clean.
4. New behavior must come with tests; the existing 429 tests show the style.

## License

License: to be decided — no `LICENSE` file is published yet (MIT is declared in `composer.json`). A formal license will be added before the project is widely shared.

## Acknowledgements

- [Laravel](https://laravel.com) and [Laravel Sail](https://laravel.com/docs/sail) — the foundation and the one-command dev environment
- [Filament](https://filamentphp.com) — the admin panel, POS pages, and dashboard widgets
- [Livewire](https://livewire.laravel.com) — reactive UI without a JS framework
- [Tailwind CSS](https://tailwindcss.com) and [Vite](https://vite.dev) — the frontend toolchain
- [Laravel Pulse](https://laravel.com/docs/pulse) — performance monitoring
- [PostgreSQL](https://www.postgresql.org), [Redis](https://redis.io), [MinIO](https://min.io), [Mailpit](https://mailpit.axllent.org) — the local stack
- [mike42/escpos-php](https://github.com/mike42/escpos-php) — thermal receipt printing
- [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode) — QR generation
- [PHPUnit](https://phpunit.de) and [Paratest](https://github.com/paratestphp/paratest) — the test suite
- [DeepSeek](https://platform.deepseek.com) and [Fonnte](https://fonnte.com) — AI copywriting and WhatsApp messaging
