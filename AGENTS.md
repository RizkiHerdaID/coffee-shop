# AGENTS.md

Project knowledge for AI agents working in this repository, distilled from prior opencode sessions. Read this before doing anything; the environment quirks section has caused real incidents.

## Project overview

Laravel 13.23 + Filament 5.7.5 + Tailwind CSS 4 + Vite coffee shop app:

- **Public site** — landing pages (`/`, `/menu`, `/contact`) rendered from Blade views styled with Tailwind 4.
- **Admin panel** — Filament v5 panel at `/admin` (replaced the original hand-rolled admin auth in commit `d154765`).
- **Stack** — Docker via Laravel Sail (`compose.yaml`): `laravel.test` (app), `queue` (worker), `scheduler` (scheduler), `pgsql` (PostgreSQL 18), `redis` 7.4, `minio` (S3-compatible storage), `mailpit` (SMTP catcher + UI at `:8025`).
- **Production** — https://coffee-shop.example (smoke-test with `curl`).

Git history (all on `main`):
`bb2502b` landing pages → `c36abe6` Sail setup → `5069e63` admin auth → `01aabbc` hardening + DB menu + onboarding fix + tests → `d154765` Filament admin panel → 2026-08-02 marathon (ops/hardening, booking, kitchen/POS repeat, P&L, payments+loyalty, discounts+suppliers, marketing+ops, share/deps, badges/testimonials/forecasting — see `docs/roadmap.md`) → `1295e67` MCP/tooling/README polish → 2026-08-03 bug-fix marathon → current HEAD `1086a9d`.

2026-08-03 bug-fix marathon (all on `main`, from the pre-merge audit + i18n/ops/messaging waves):
`22bacfa` POS cash bugs (payments store APPLIED amount + change, net-revenue unification, concurrency guards) → `d6761c3` order audit freeze (no deletes, closed-shift freeze, loyalty exactly-once, honest markPaid, atomic capture) → `1728a9c` ops/infra (scheduler service, storage:link, robots route, report indexes, AWS endpoint) → `8134983`+`924492a` stock/wastage forms (wastage stock linkage, PO receive atomicity, numeric masks, seeder uniqueness) → `2b9b22f` review role + workflow docs → `76d76e6` public-site i18n fixes (raw JSON-LD price, SetLocale precedence, lang-switch query strip, reservation hardening, empty states, loyalty config) → `1086a9d` services/messaging hardening (Fonnte retries + JSON validation, phone normalization, AiCopy retries + array content).

## Architecture map

| Concern | Location |
| --- | --- |
| Public pages | `app/Http/Controllers/PageController.php` (`home()`, `menu()`, `contact()`, `points()` at `/cek-poin`, `reservation()` at `/reservasi`, `qr()` at `/qr/{table}`) |
| Admin panel | `app/Providers/Filament/AdminPanelProvider.php` (id `admin`, path `admin`, `->authGuard('admin')`, brand "Coffee Shop", `->login(App\Filament\Auth\Login::class)`) |
| Admin model | `app/Models/Admin.php` — implements `FilamentUser` (`canAccessPanel` returns `true`), `#[Fillable]`/`#[Hidden]` attributes |
| Domain models | `app/Models/` — `Admin`, `User`, `MenuItem`, `Shift`/`ShiftCashMovement`, `Order`/`OrderItem`/`Payment`, `StockItem`/`StockMovement`, `Expense`, `Supplier`, `PurchaseOrder`/`PurchaseOrderItem`, `CashRegisterSession`, `LoyaltyCard`, `Promo`, `Reservation`, `Testimonial`, `Wastage`; enums in `app/Enums/` (string-backed, `HasLabel` for UI labels — `OrderStatus` implements it, `PurchaseOrderStatus` localizes manually in render sites) |
| Filament resources | `app/Filament/Resources/<Plural>/` for 12 features: `MenuItems`, `Orders`, `StockItems`, `Suppliers`, `PurchaseOrders`, `Expenses`, `CashRegisterSessions`, `LoyaltyCards`, `Promos`, `Reservations`, `Testimonials`, `Wastages` — each `*Resource.php` + `Schemas/` + `Tables/` (+ `RelationManagers/` for StockItems/PurchaseOrders); dashboard widgets in `app/Filament/Widgets/` (`TodayStats`, `RevenueChart`, `TopItemsChart`, `BestSellersChart`, `PeakHoursChart`, `PaymentSplitChart`, `LowStockWidget`, `DemandForecastWidget`) |
| POS pages | `app/Filament/Pages/` — `Cashier` (cart→order→payment→served), `ManageShift` (slug `shift`, `/admin/shift`, open/close with opening/closing cash; one active shift enforced by a partial unique index; close is a conditional `whereNull('closed_at')` update setting only `closed_at` + `closing_cash` — the `expected_total` column was DROPPED 2026-08-03, expected cash & discrepancy are computed live from payment rows + cash movements — then redirects to the report), `ShiftReport` (`/admin/shift-report/{record}`, Z-report via `getRoutePath`; not in nav), `PnlReport` (`/admin/pnl-report`, monthly P&L — see `docs/owner-tools.md`), `QrCodes` (printable QR table codes). Standalone printable Z-report: `PosZReportController` + `resources/views/filament/pos/z-report.blade.php` at `/pos/z-report/{shift}` (auth:admin, mirrors `pos.receipt`). Shift math on `App\Models\Shift`: `active()`, `salesTotal()` (NET — `net_total` after discounts), `paidOrdersCount()`, `paymentsByMethod()` (`['cash','qris','ewallet']`, positive rows only), `expectedCash()` (opening + cash paid − cash refunds + deposits − petty_out), `discrepancy()` (counted − expected; 0 while open). Cashier orders attach `shift_id` when an active shift exists (null otherwise, banner shown). Cash capture stores the APPLIED amount (capped at the remaining balance) + `change` on the `payments` row — the drawer only ever accounts for what was applied; change due = Σ `payments.change` |
| Shop info | `config/shop.php` — `name`, `phone`, `phone_display`, `email`, `address`, `hours` (code→time map; codes `mon_fri`/`sat`/`sun`, labels come from `lang/*/site.php` `days` keys), `maps_url` (Google Maps search URL from `rawurlencode($address)`) |
| Localization | `lang/{id,en}/` — per-feature files `site.php` (nav/footer/meta/days + `wa_message`), `home.php`, `menu.php`, `contact.php`, `qr.php`, `dashboard.php`, `orders.php`, `pos.php`, `stock.php`, `expenses.php`, `suppliers.php`, `purchase-orders.php`, `promos.php`, `reservation.php`, `loyalty.php`, `points.php`, `pnl.php`, `recipes.php`, `testimonials.php`, `wastage.php`, `summary.php`, `whatsapp.php`, `ai-copy.php` (+ `validation.php`); default locale `id` (config + `.env` `APP_LOCALE=id`), switch via `app/Http/Middleware/SetLocale.php` (session or `?lang=`), route `GET /lang/{locale}` (persists session, redirects back); switcher partial `resources/views/partials/language-switcher.blade.php` |
| Auth config | `config/auth.php` — `admins` provider + `admin` session guard |
| Login throttling | `app/Filament/Auth/Login.php` — custom Filament login page overriding `getRateLimitKey()` to key attempts by **email + IP** (5/min, Filament's `WithRateLimiting` flow + notification). There is NO `throttle:` middleware on the login route (Livewire component, not a route). Tests: `AdminLoginThrottleTest` |
| Services/console | `app/Services/AiCopyService.php` (menu copy gen, `DEEPSEEK_*` env — retries + joins array content), `app/Services/FonnteWhatsApp.php` (WA gateway, `config/whatsapp.php` — retries, JSON-response validation, empty-phone guard), `app/Support/Phone.php` (E.164-ish normalization: `0812-3456-7890`/`+6281234567890` → `6281234567890`; keyed loyalty lookups, WA send path, cek-poin); commands `app/Console/Commands/GenerateMenuCopy.php`, `SendSummaryEmail.php` (`config/summary.php`), `SendLowStockAlerts.php` (24h re-alert pacing) — scheduled in `bootstrap/app.php` `withSchedule` (summary daily/weekly, `stock:alert-low`, `pulse:check`), run by the dedicated `scheduler` container, heartbeat via `UPTIME_HEARTBEAT_URL` |
| Seeders | `database/seeders/DatabaseSeeder.php` (User factory + Admin from `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` env with fallbacks `Admin`/`admin@example.com`/`password` + `MenuSeeder`); `MenuSeeder.php` — 8 items with Indonesian notes, idempotent via `updateOrCreate` on `name` (re-seed `--class=MenuSeeder` to refresh notes; `menu_items`/`stock_items` have UNIQUE `name` and `promos` UNIQUE `title` since 2026-08-03) |
| Tests | `tests/Feature/` — 52 classes, **575 tests / 2452 assertions** (verified 2026-08-03): public pages (`HomePageTest`, `MenuPageTest`, `ContactPageTest`, `SeoTest`, `LocalizationTest`, `QrMenuTest`, `ReservationTest`, `SecurityHeadersTest`, `PageSpeedTest`, `PointsPageTest`, `RobotsTest`), admin auth (`AdminAuthTest`, `AdminLoginThrottleTest`), resources (`MenuItemFormTest`, `OrdersTest`, `OrderTest`, `InventoryTest`, `SuppliersTest`, `PurchaseOrdersTest`, `ExpenseTest`, `CashRegisterTest`, `PromoTest`, `RecipesTest`, `TestimonialsTest`, `WastageTest`), POS (`PosCashierTest`, `ShiftTest`, `PaymentTest`, `RefundsVoidsTest`, `RecipeStockConsumeTest`, `OverTenderRegressionTest`, `PaymentChangeTrackingTest`, `PosConcurrencyTest`, `OrderRefundGuardTest`), dashboard (`DashboardWidgetsTest`, `LowStockWidgetTest`, `DemandForecastTest`, `PnlReportTest`), services (`AiCopyTest`, `WhatsAppTest`, `SalesSummaryEmailTest`, `WaPickupTest`, `LowStockAlertTest`, `LoyaltyTest`, `PhoneTest`, `SendOrderConfirmationTest`, `SendReservationConfirmationTest`), ops (`ExportsTest`, `PulseTest`, `UptimeMonitorTest`, `ScheduleRegistrationTest`, `MenuSeederTest`) — all run inside the Sail container |
| Docker | `compose.yaml` (entrypoint `["./docker-entrypoint.sh", "/usr/local/bin/start-container"]`, healthchecks, `queue` + `scheduler` + `mailpit` services), `docker-entrypoint.sh` (APP_KEY gen, wait for pgsql 60×, `migrate --force`, `storage:link --relative --force`, optimize, view:cache), `.env.example` (see README) |

## Commands

```bash
# Tests (MUST run inside the Sail container — see quirks)
sg docker -c "./vendor/bin/sail artisan test"
sg docker -c "./vendor/bin/sail artisan test --filter=AdminAuthTest"
# Fast parallel loop (~4.5x faster on 8 procs; per-process sqlite:memory/array-cache = safe)
sg docker -c "cd /home/rizki/projects/coffee-shop && PAO_DISABLE=1 ./vendor/bin/paratest --no-coverage --processes 8"

# Code style (Pint)
vendor/bin/pint                    # or: vendor/bin/pint --test
vendor/bin/pint app/Models/Admin.php tests/Feature/AdminAuthTest.php

# PHP syntax check (host is fine for this)
php -l app/Models/Admin.php

# Vite build (needed after any view/Tailwind change; runs in container)
sg docker -c "./vendor/bin/sail npm install --ignore-scripts"   # if node_modules missing
sg docker -c "./vendor/bin/sail npm run build"

# Migrate / seed / tinker
sg docker -c "./vendor/bin/sail artisan migrate"
sg docker -c "./vendor/bin/sail artisan db:seed"
sg docker -c "./vendor/bin/sail artisan tinker"

# Inspect the DB directly
sg docker -c "docker exec coffee-shop-pgsql-1 psql -U sail -d coffee_shop -c 'SELECT * FROM admins;'"

# Smoke-test pages
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/
curl -s -o /dev/null -w "%{http_code}\n" https://coffee-shop.example/
```

## Environment quirks (learned the hard way)

1. **Docker socket needs `sg docker`.** The user is in the `docker` group, but shells/sessions started before login don't have the group loaded. Plain `docker`/`sail` gives permission denied. Always wrap: `sg docker -c "<command>"`. Do NOT use `sudo` (no passwordless sudo).
2. **Host PHP cannot run the suite.** Host PHP lacks `pdo_sqlite`/`pdo_pgsql` and `.env` points at Docker-network hostnames (`pgsql`, `redis`) unreachable from the host. Run tests inside the container only.
3. **`laravel/pao` swallows PHPUnit output** in agent environments. Run tests with `PAO_DISABLE=1 ... --no-coverage` (phpunit.xml has `failOnWarning=true`, so the missing coverage-driver warning aborts the run).
4. **`public/build` is gitignored and generated by `npm run build`.** There is NO Node.js on the host. A missing/stale build causes HTTP 500 (`@vite` throws) or stale CSS (the "login card not centered" incident — the running container served an old `public/build`). After changing views/CSS, rebuild assets in the container and re-check with `curl`.
5. **Redis RDB version crash-loop**: a stale `sail-redis` volume written by a newer Redis makes Redis 7.4 crash-loop. Fix: `sg docker -c "./vendor/bin/sail down"` then `sg docker -c "docker volume rm coffee-shop_sail-redis"` then `sail up -d`.
6. **Worktree Sail containers run side-by-side with per-worktree ports.** Feature branches run in herdr worktrees (`~/.herdr/worktrees/coffee-shop/<branch>`); each worktree gets its own `.env` port mapping via `scripts/worktree-env.sh <worktree-dir> <slot 1-4>` (slots map to APP_PORT 8081-8084, FORWARD_DB_PORT 5433-5436, etc. — see the herdr-parallel skill for the table). All worktrees can be up simultaneously. Only `APP_PORT`/`VITE_PORT`/`FORWARD_*` and `APP_URL` are overridden — `DB_PORT`/`REDIS_PORT`/`MAIL_PORT`/`AWS_ENDPOINT_URL` stay internal (in-container hostnames `pgsql`, `redis`, `mailpit`, `minio`). One-time cleanup: worktrees created BEFORE this scheme still hold default ports; `sail down` them once.
7. **`laravel.test` unhealthy / can't resolve `pgsql`** after interrupted starts → leftover containers have no network attached. Fix: `sail down` + `sail up -d` (full recreate).
8. **After `sail up`, the entrypoint's `optimize` re-caches config/views/routes, which breaks the test suite** (phpunit.xml env overrides like `SESSION_DRIVER=array`/`DB_CONNECTION=sqlite` are ignored → CSRF 419s and `data.email is required` failures in AdminAuthTest). Fix after every `sail down`/`up` in a dev env: `sg docker -c "php artisan config:clear && php artisan view:clear && php artisan route:clear"`. The same stale caches cause: new routes 404 (check `bootstrap/cache/routes-v7.php`), new Filament resources 500 "Route not defined", deleted widget classes fatal "Class not found" — clear before debugging code. `sail artisan migrate` right after a recreate may also report a bogus `pgsql` DNS error — retry (or use `docker exec -u sail coffee-shop-laravel.test-1 php artisan ...`) before assuming a real network fault. See `.opencode/skills/sail-filament-workflow/SKILL.md` for the full verification loop and Filament v5 gotchas.
9. **Seeded admin**: `admin@example.com` / `password` (fallbacks; `.env` may not set `ADMIN_*`). Change before anything facing real traffic.
10. **Stale `bootstrap/cache/filament/panels/admin.php` component map**: written at boot and root-owned (container), so `artisan optimize:clear` can't delete it. New Filament resources 404/500 ("Route not defined") despite `route:list` showing them. Fix: `rm -rf bootstrap/cache/filament` (from inside a root container if needed: `sg docker -c "docker run --rm -v .:/app alpine rm -rf /app/bootstrap/cache/filament"`).
11. **`pkill -f 'artisan serve'` kills the Sail web server** — `laravel.test` serves via `artisan serve` under supervisord, NOT nginx. Don't pkill broad patterns; target PIDs from `ps aux | grep` instead.
12. **Never access `/mnt/c`** (outside WSL) — user explicitly banned it.
13. **Don't commit or push unless explicitly asked.** When asked, use the existing style (e.g. `Add Filament admin panel, replace custom admin auth, add menu resource`).
14. **MCP server quirks (validated 2026-08-03 audit session)** — read `.opencode/skills/pre-merge-bug-hunt/SKILL.md` for the full audit protocol and the working Semgrep recipe:
    - **Semgrep path scans fail**: the MCP server is sandboxed and cannot see repo paths (`/home/...` AND container paths like `/var/www/html/...` → Errno 2). `semgrep_findings` needs `SEMGREP_APP_TOKEN` (unset). The WORKING path is `semgrep_scan_with_custom_rule` with file CONTENT in the payload, and `metavariable-regex` at RULE level (nested under `patterns` → "Loading rules from local config..." error). Read files fully first; content must be verbatim.
    - **phpcodearcheology claims "No tests found" / "No test infrastructure" for everything** — it cannot detect PHPUnit; ignore that line. Its hotspots/cycles/refactor priorities are reliable (14 dependency cycles incl. a 9-class Order/Shift/StockItem cycle; Cashier CC=56).
    - **Postgres MCP has no `pg_stat_statements` or `hypopg`** — `get_top_queries` and `analyze_query_indexes` fail; use `explain_query` on hot report queries instead (seq-scan output = missing-index evidence) and `laravel-boost database-query` for read-only data queries.
    - **Redis holds only Laravel cache keys** (queue + sessions live in the DB) — a near-empty `scan_all_keys` is NORMAL, not a fault.
    - **Live-data verification beats re-reading code**: the strongest bug evidence is an integrity query (e.g. `HAVING SUM(amount) > total` proved the cash over-tender bug with real rows; component-wise shift-expected queries proved expectedCash overstated by exactly the change). See the skill for the query set.
15. **Laravel 13 cache serializer refuses class unserialization** (`allowed_classes=false`) — `Cache::put()` with an Eloquent model/collection throws. Cache raw rows/arrays and re-hydrate; never cache models.
16. **Paratest `ExportsTest` flake**: export tests share real files on the `local` disk across paratest processes — a parallel run can report spurious failures there. Re-run with `artisan test --filter=ExportsTest` to confirm before treating it as a regression.
17. **Worktree names are `worktree-1..4`** (slot = name, recycled per wave), not feature names. Worktree agents need `npm run build` before public-page tests (stale/missing Vite manifest), and `scripts/worktree-env.sh` leaves `.env.bak` artifacts (safe to ignore).
18. **`/tmp/opencode/<branch>-contract.md` files have been deleted mid-run once** — dispatch prompts now carry the full task; treat the prompt as the source of truth, not the file.

## Conventions

- **Laravel 13 attribute style**: `#[Fillable(['...'])]`, `#[Hidden(['password', 'remember_token'])]`, casts as `#[Cast]`/property casts — mirror `app/Models/Admin.php`.
- **Price formatting**: integer IDR, rendered as `Rp {{ number_format($item->price, 0, ",", ".") }}` (e.g. `Rp 25.000`) on BOTH home and menu pages. Tests assert this exact string.
- **Localization (mandatory, ALL features)**: every user-facing string — Blade, Filament UI (labels, column headers, badges, action titles, modal submit buttons, notifications, empty states, navigation labels), CLI command output, queued job messages, and user-facing exception messages — must come from `lang/{id,en}/` via `__()`. Indonesian is primary (`id`), English secondary (`en`). Never hardcode English strings in code. Per-feature files (e.g. `lang/id/stock.php`); console output/job messages a person might read are localized too. Exception/log messages kept in English are acceptable; strings rendered to a user are not. Filament package chrome (Save/Delete/search etc.) stays English unless `vendor:publish --tag=filament-translations` is run.
- **Numeric inputs (mandatory)**: any numeric form input (Filament or Blade) must DISPLAY Indonesian thousand/million separators with a period (`25.000`, `1.500.000`) while the DATABASE stores the raw integer. Idiom: `->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))` (Alpine dynamic money mask — NOT literal `'999.999.999'`, which fills left-to-right and mangles input into `500.0`) + `->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')` + `->formatStateUsing()` (idempotent — skip already-dotted state so failed-validation re-renders don't mangle) + `->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))` (mirror `StockItemForm::formatQuantity()`/`rawQuantity()`). Verify the stored DB row, not just the form display.
- **Shop info** must come from `config('shop.*')` in views — never hardcode hours/address/phone in Blade (contact page already does this). All page copy comes from `lang/{id,en}/` — never raw strings in Blade. Day labels: `__("site.days.$day")` keyed by the `config('shop.hours')` code; JSON-LD `dayOfWeek` stays English. WhatsApp prefill: `__('site.wa_message')` (no longer in config).
- **Menu ordering**: `MenuItem::query()->orderBy('sort_order')`; home takes the first 4 as `$highlights`.
- **Filament v5 layout**: resources live at `app/Filament/Resources/<Plural>/` with `Schemas/` + `Tables/` subdirectories; v5 form components (`TextInput`, `TextArea`) live in `Schemas/`, table columns in `Tables/`. Note: v5 component class is `Filament\Forms\Components\Textarea` (NOT `TextArea`) — tests catch this.
- **Enum-cast handling in Filament**: `formatStateUsing`/badges receive the ENUM INSTANCE (model cast), not a string — use `$state instanceof EnumClass` checks, not string comparisons.
- **Admin auth**: Filament handles login/logout at `/admin`; guard is `admin`, model implements `FilamentUser`. Login page is the custom `app/Filament/Auth/Login.php` (registered via `->login()` in the panel provider). No custom admin controllers or `resources/views/admin/*` views exist anymore (deleted in `d154765`).
- **Rate limiting**: brute-force protection on admin login lives in `app/Filament/Auth/Login.php` (`getRateLimitKey()` — 5/min keyed by **email + IP**). There is no `throttle:` middleware on the login route (Livewire component) and no `RateLimiter::for('admin.login')` registration — a dead registration was removed; don't re-add one expecting it to wire up.
- **trustProxies is REQUIRED (do NOT remove)**: `bootstrap/app.php` must keep `$middleware->trustProxies(at: ['127.0.0.1', '172.16.0.0/12'], ...)` — behind the Caddy proxy Laravel would otherwise emit `http://` asset/form URLs and the site renders unstyled (mixed-content CSS, blocked by browsers). The `172.16.0.0/12` scope exists because docker-proxy makes the app see the bridge gateway (e.g. `172.19.0.1`) as REMOTE_ADDR, not `127.0.0.1`. Never remove it, never widen to `'*'` — both regressed the site once already (git log `408f29a` → `9768f02`). The deploy script's post-deploy checks (https CSS + https `/admin` redirect) will FAIL deploys that break this.
- **Tests**: phpunit.xml pins `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` + `FILESYSTEM_DISK=local` — tests are DB-independent. Use `RefreshDatabase` + `$this->seed(MenuSeeder::class)` for menu-dependent tests.
- **Filament test gotchas**: `assertHasFormErrors(['email'])` (NOT `['data.email']` — Filament prepends the `data` path itself); `withServerVariables` does NOT affect Livewire's request IP (test IP-keyed throttling deterministically, e.g. per-email keys); `Cache::flush()` in `setUp` for throttle tests.
- **Test-writing rules (from post-mortems)**: assert on stable strings (URLs, `config('shop.*')` values) not copy/placement; use `url()` helpers not literal hosts (worktree APP_URL is slot-dependent, e.g. `http://localhost:8081`–`:8084`); `assertSee` escapes input (pass the rendered-entity form, not a literal `&amp;`); widgets are Livewire-lazy — test protected methods via reflection, not `assertSee` on dashboard HTML; static `public/` files shadow routes in prod — check `public/` for collisions before adding a route (the old `public/robots.txt` was DELETED 2026-08-03 so `RobotsController` serves `/robots.txt`; adding a static file back would shadow it).
- **Filament v5 dashboard**: charts need NO package — `ChartWidget` ships in `filament/widgets`; side-by-side stats = one `StatsOverviewWidget` with multiple `Stat::make()`; never use fractional `columnSpan('1/3')` (invalid CSS). `migrate:fresh` silently empties the dev pgsql DB — re-seed after.
- **Phone normalization (mandatory)**: every user-supplied phone (loyalty lookup/credit, WA confirmations, cek-poin query, pickup) goes through `App\Support\Phone::normalize()` — `0812-3456-7890`/`+6281234567890`/`081234567890` all converge to `6281234567890`. Loyalty cards are stored with the normalized phone and a UNIQUE index on it (`findByPhone()` matches any input format).
- **Loyalty exactly-once**: stamps credit on paid orders once — `OrderObserver::saved()` re-reads the row with `lockForUpdate` and skips when `loyalty_credited_at` is already set, then `forceFill()->saveQuietly()` stamps the column (no `wasChanged` guard — a concurrent save of the same order cannot both observe the unset flag); reward threshold from `config('loyalty.stamps_per_reward')` (default 10, env `LOYALTY_STAMPS_PER_REWARD`).
- **Order audit freeze (2026-08-03)**: orders are never deleted (delete guard throws), refunds are only allowed on open/unattached shifts so closed Z-reports stay stable, order numbers come from the `order_counters` table (concurrency-safe daily sequence), and `markPaid()`/capture are atomic (conditional updates — a stale client cannot double-apply).
- **Cash handling**: capture stores the APPLIED amount (capped at the remaining balance) and the surplus as `change` on the `payments` row — the drawer only accounts for what was applied; `Shift::expectedCash()` = opening + cash paid − cash refunds + deposits − petty_out; revenue everywhere (`Shift::salesTotal()`, P&L, `CashRegisterSession::revenue()`) is NET (`net_total` = gross − discount).
- **`docker-entrypoint.sh`** must stay executable (755) — it provisions APP_KEY and migrations on first boot.

## Workflow (user preferences)

- **Vikunja kanban (MANDATORY, MAIN SESSION ONLY — sub-agents NEVER):** every task — done, in-flight, or future — must be tracked on the homelab Vikunja board. The `vikunja` MCP server (in `opencode.json`, `@eargollo/vikunja-mcp`, env `VIKUNJA_URL`/`VIKUNJA_API_TOKEN`/`VIKUNJA_MCP_ALLOW_WRITE=1`) is the MAIN SESSION's preferred way to do board ops — the curl lore below stays as fallback (real board URL is the gitignored `VIKUNJA_URL` in `opencode.json`; replace the placeholder below before use). **HARD RULE: only the main opencode session (this repo's orchestrator, w14:p1-style pane) manages Vikunja.** Sub-agents, worktree agents, herdr panes, and Task-tool agents must NEVER call the Vikunja API (curl or MCP), read `~/.vikunja-agent-token`, or create/move/label/comment on cards — their AGENTS.md mandate ends at the code they implement; the main session does all board bookkeeping (create/move/label/comments) before dispatch and after merge. If a sub-agent finds the AGENTS.md Vikunja instructions ambiguous, it must ignore them and continue the code task. Board: https://vikunja.example, project "Coffee Shop" (id 6, kanban view 24, buckets Pending=17 / Doing=18 / Done=19). Auth: Bearer token from `~/.vikunja-agent-token` (0600). Workflow: create a card (Pending) when starting — POST `/api/v2/projects/6/tasks` `{"title": "<card title>"}` (the bucket-PUT variant 422s on `title`; create goes to `/tasks`, move goes to `/buckets/{B}/tasks`), then move to Doing while working, Done when finished (PUT `/api/v2/projects/6/views/24/buckets/{B}/tasks` with the **task ID in the BODY** — `{"task_id": <id>}` — NOT in the URL and NOT `{title}`; URL ends at `/tasks`, e.g. `curl -X PUT -H "Authorization: Bearer $T" -H "Content-Type: application/json" "https://vikunja.example/api/v2/projects/6/views/24/buckets/18/tasks" -d '{"task_id":88}'`. The task-ID-in-URL variant 404s, `{title}` in body 422s. Moving into Done also marks `done=true`); add progress comments via `/tasks/{id}/comments`; verify task IDs before updating (board verification: the PUT response's `bucket_id` is authoritative — the view tasks listing always shows `bucket_id: 0`, and `list_buckets` reports `count: 0` for every bucket regardless of contents — verify counts via `list_tasks` with `filter: done = false` or `get_task` instead). Labels (Eisenhower Matrix, IDs: 1=P1 Urgent&Important, 2=P2 Important/Not Urgent, 3=P3 Urgent/Not Important, 4=P4 Neither) should be applied to every card via POST `/api/v2/tasks/{id}/labels` `{"label_id": N}`. Before starting any new task, GET `/api/v2/projects/6/tasks` and check whether a card for it already exists (board was populated 2026-08-02 with 20 done + 15 pending cards from git history and `docs/research-*.md`). If `~/.vikunja-agent-token` is missing, STOP and ask the user to restore it — never guess.
- Parallel work is expected via **herdr panes** running **opencode agents** (`herdr agent start --kind opencode`), not just the built-in Task tool. See `.opencode/skills/herdr-parallel/SKILL.md`.
- **MCP servers** (configured in `opencode.json`, gitignored — holds secrets): `laravel-boost` (Sail), `vikunja` (board ops — see bullet above), `postgres` (crystaldba, read-only restricted, EXPLAIN/index analysis), `redis` (official), `semgrep` (local SAST), `phpcodearcheology` (architecture metrics), `playwright` (`@playwright/mcp`, real-browser E2E INSIDE the session — `--isolated --headless`; see `.opencode/skills/playwright-mcp-e2e/SKILL.md`). Bootstrap a fresh checkout: `cp opencode.json.example opencode.json`, then fill `VIKUNJA_API_TOKEN` (from `~/.vikunja-agent-token`) and the postgres `DATABASE_URI` (password from `.env`; worktree slots remap DB to 5433–5436 and Redis to 6380–6383 — main checkout: 5432/6379).
- **Documentation lives in `docs/` (feature docs + `docs/README.md` index + `docs/roadmap.md` backlog mirror).** When a feature lands, update the matching doc in the same change — never re-create `research-*.md` files in `docs/` (they were replaced by `docs/{pos,website,owner-tools,roadmap}.md` + `docs/i18n/` on 2026-08-02).
- **Agent role split (MANDATORY rule) — split by feature shape, not habit:**
  - *Backend-only features* (models, Filament actions, migrations, config, jobs — no public UI): **lead + test + review** (3 panes — reviewer = active contract-validation while the lead implements + phase-2 re-check of the final diff; catches integration hazards like enum `match()`, shift-math contracts, transaction locking).
  - *Features touching public UI AND backend* (Blade/Tailwind/JS/Livewire views + backend): **lead + backend + frontend + test** (4 panes — matches the auto-layout).
  - *Tiny S tasks*: **lead alone** — close spare panes (~700MB RAM each).
  - *Audits / bug-hunts (read-only, no worktree, no herdr panes)*: 5 parallel built-in `explore` agents via the Task tool — POS/shift/orders, Filament resources+auth, services/jobs/ops, public site+i18n, tests/schema — then the verification passes in `.opencode/skills/pre-merge-bug-hunt/SKILL.md` (spot-check every P0/P1 at source, postgres data-integrity proof, content-based semgrep, line-by-line missed-items diff, Vikunja carding).
- **Simultaneous dispatch (MANDATORY): ALL agents of a batch must be prompted at dispatch time, in parallel — never sequential.** Test agents write tests from the contract file while the lead implements (tests may fail until implementation lands — expected, they report which fail); review agents do contract-validation against the current codebase immediately. Do NOT tell a lead "implement first, then delegate" — that serializes the fleet. Leads must instead be told sub-agents are already running and to read their report files from `/tmp/opencode/<batch>-<role>-report.md` (verify on disk) and incorporate results before committing. **Staging rule: agents WRITE files only — they never run `git add`/`git rm`/`git commit` (concurrent writes to the shared index race and can corrupt it); the lead stages everything after the batch.**
- **Pre-merge bug-hunt (MANDATORY for any branch touching money/POS/reports — orders, payments, shifts, cash register, stock, P&L, summary emails):** before merging, run the audit protocol in `.opencode/skills/pre-merge-bug-hunt/SKILL.md`: baseline MCP sweep (boost/phpcodearcheology/postgres/redis), the 5-area parallel explore fleet with the report contract (BUG/EDGE/RISK/HARDCODED/VERIFIED-OK, `file:line` every finding), claim verification at source (never trust agent claims unverified — 1 in ~30 was wrong in the 2026-08-03 run), live-DB data-integrity queries, content-based semgrep, and the line-by-line missed-items re-check. P0/P1 findings become Vikunja cards (Pending, P1/P2 labels) and must be FIXED (or explicitly deferred by the user) before the merge. Why this gate exists: the 2026-08-03 full-repo audit found 30 issues (15 P1) while the suite was 428/428 green (575 now) — the cash over-tender bug shipped because tests encoded the wrong behavior.
- **Pre-merge gate (MANDATORY, before merging ANY worktree branch to main):** never merge on the lead's DONE message alone. Checklist:
  1. `herdr agent list` — every pane of the branch fleet must be **done/idle** (lead/test/review all finished; any `working` = do not merge) and confirm via `herdr agent read <pane>` what the last action was; treat `unknown` as a hard block (machine-checkable, not eyeballed).
  2. All sub-agent report files exist on disk under `/tmp/opencode/<batch>-<role>-report.md` and their findings are incorporated (spot-check the diff against the report's checklist).
  3. Review agents run a **phase-2 re-check** of the final code (prompt the idle reviewer to re-run its checklist against the committed diff, read-only) — phase 1 alone is not enough.
  4. `git status` of the worktree must be clean except the expected commits (`git log main..branch` reviewed); no dangling uncommitted work.
  5. Full suite on main after merge (`sg docker -c "PAO_DISABLE=1 ./vendor/bin/sail artisan test --no-coverage"`), then teardown (sail down, workspace close, remove dir, `git worktree prune`), then move the Vikunja card to Done and verify (`done: true`).
- **Main-checkout batch gate (no worktree — docs/research-only fleets):** same spirit, lighter: 1) all report files exist at `/tmp/opencode/<batch>-<role>-report.md` and each agent confirmed DONE via `herdr agent prompt w14:p1`; 2) the lead spot-checks each agent's claims against the code (read the files, don't trust the report) — flag any stale-claim correction that was NOT applied; 3) `git status` shows EXACTLY the expected staged files (all targets added, all sources removed, nothing else); 4) close the fleet panes, then commit once on main.
- Real-browser E2E testing uses the **herdr Browser plugin** (`official.browser`): bun at `~/.local/bin/bun`, Chrome for Testing at `~/.local/share/cft/chrome-linux64/chrome` (needs `LD_LIBRARY_PATH=~/.local/share/cft/libs` + `HERDR_BROWSER_CHROME`). Critical: cookies never flush to disk (login dies with the daemon — keep ONE daemon per compound flow); `HERDR_BROWSER_PROFILE_ROOT` is stripped by the plugin (isolate agents via `HERDR_PLUGIN_STATE_DIR` + `HERDR_SESSION`). Full playbook + quirks: `.opencode/skills/herdr-browser-e2e/SKILL.md`. E2E run 2026-08-02: all 4 parallel agents PASS (public pages, auth+dashboard+throttle, inventory, ops). For SHORT in-session spot checks (single audit flow, console/network capture) use the **Playwright MCP** instead — see `.opencode/skills/playwright-mcp-e2e/SKILL.md` (heavy per-step tokens; keep flows short).
- Feature branches live in herdr worktrees at `~/.herdr/worktrees/coffee-shop/<branch>` and get merged into `main` when ready. After `herdr worktree create`, boot the fleet with `scripts/herdr-fleet-boot.sh <branch> [N]` — it splits the agents tab into N equal panes and starts one opencode agent per pane (layout-first; see `.opencode/skills/herdr-parallel/SKILL.md`).
- Verify before reporting: tests in container (`PAO_DISABLE=1`, `--no-coverage`), `vendor/bin/pint`, `php -l` on touched files, HTTP smoke test via `curl` (local `http://localhost` and prod `https://coffee-shop.example`).
- The composer scripts are `setup`, `dev` (concurrently: server/queue/logs/vite), `test` — there is no separate `composer test` alias.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/pulse (PULSE) - v1
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== filament/filament rules ===

## Filament

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Inspect required options before running, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

<code-snippet name="Reactive field update" lang="php">
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),

</code-snippet>

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

<code-snippet name="Section and Grid layout" lang="php">
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),

</code-snippet>

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

<code-snippet name="Repeater for HasMany" lang="php">
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

<code-snippet name="Table filters" lang="php">
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),

</code-snippet>

Actions are buttons that encapsulate optional modal forms and behavior:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),

</code-snippet>

### Testing

Testing setup (requires `pestphp/pest-plugin-livewire` in `composer.json`):

- Always call `$this->actingAs(User::factory()->create())` before testing panel functionality.
- For edit pages, pass `['record' => $user->id]`, use `->call('save')` (not `->call('create')`), and do not assert `->assertRedirect()` (edit pages do not redirect after save).

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Edit resource test" lang="php">
livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

<code-snippet name="Calling actions" lang="php">
use Filament\Actions\Testing\TestAction;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v4.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes

</laravel-boost-guidelines>
