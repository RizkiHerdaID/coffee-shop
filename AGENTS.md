# AGENTS.md

Project knowledge for AI agents working in this repository, distilled from prior opencode sessions. Read this before doing anything; the environment quirks section has caused real incidents.

## Project overview

Laravel 13.23 + Filament 5.7.5 + Tailwind CSS 4 + Vite coffee shop app:

- **Public site** — landing pages (`/`, `/menu`, `/contact`) rendered from Blade views styled with Tailwind 4.
- **Admin panel** — Filament v5 panel at `/admin` (replaced the original hand-rolled admin auth in commit `05d1826`).
- **Stack** — Docker via Laravel Sail (`compose.yaml`): `laravel.test` (app), `queue` (worker), `pgsql` (PostgreSQL 18), `redis` 7.4, `minio` (S3-compatible storage), `mailpit` (SMTP catcher + UI at `:8025`).
- **Production** — https://coffee.rizkilab.my.id (smoke-test with `curl`).

Git history (all on `main`):
`bb2502b` landing pages → `c36abe6` Sail setup → `5069e63` admin auth → `01aabbc` hardening + DB menu + onboarding fix + tests → `05d1826` Filament admin panel (current HEAD).

## Architecture map

| Concern | Location |
| --- | --- |
| Public pages | `app/Http/Controllers/PageController.php` (`home()`, `menu()`, `contact()`) |
| Admin panel | `app/Providers/Filament/AdminPanelProvider.php` (id `admin`, path `admin`, `->authGuard('admin')`, brand "Coffee Shop") |
| Admin model | `app/Models/Admin.php` — implements `FilamentUser` (`canAccessPanel` returns `true`), `#[Fillable]`/`#[Hidden]` attributes |
| Menu model | `app/Models/MenuItem.php` — `menu_items` table; `name`, `price` (unsigned int IDR), `note` (nullable), `sort_order` (default 0) |
| Filament resource | `app/Filament/Resources/MenuItems/` — `MenuItemResource.php` + `Schemas/MenuItemForm.php` + `Tables/MenuItemsTable.php` (Filament v5 layout) |
| Shop info | `config/shop.php` — `name`, `phone`, `phone_display`, `email`, `address`, `hours` (code→time map; codes `mon_fri`/`sat`/`sun`, labels come from `lang/*/site.php` `days` keys), `maps_url` (Google Maps search URL from `rawurlencode($address)`) |
| Localization | `lang/{id,en}/` — `site.php` (nav/footer/meta/days + `wa_message`), `home.php`, `menu.php`, `contact.php`; default locale `id` (config + `.env` `APP_LOCALE=id`), switch via `app/Http/Middleware/SetLocale.php` (session or `?lang=`), route `GET /lang/{locale}` (persists session, redirects back); switcher partial `resources/views/partials/language-switcher.blade.php` |
| Auth config | `config/auth.php` — `admins` provider + `admin` session guard |
| Login throttling | `app/Providers/AppServiceProvider.php::boot()` — `RateLimiter::for('admin.login', Limit::perMinute(5)->by(strtolower(email).'|'.ip))`; applied as `throttle:admin.login` middleware on POST `/admin/login` only |
| Seeders | `database/seeders/DatabaseSeeder.php` (User factory + Admin from `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` env with fallbacks `Admin`/`admin@example.com`/`password` + `MenuSeeder`); `MenuSeeder.php` — 8 items with Indonesian notes, idempotent via `updateOrCreate` on `name` (re-seed `--class=MenuSeeder` to refresh notes) |
| Tests | `tests/Feature/` — `HomePageTest`, `MenuPageTest`, `ContactPageTest` (public pages, seed `MenuSeeder`, assert `Rp 25.000` format), `AdminAuthTest` + `AdminLoginThrottleTest` (Filament login via Livewire), `LocalizationTest` (default `id`, `?lang=en`, switcher redirects) |
| Docker | `compose.yaml` (entrypoint `["./docker-entrypoint.sh", "/usr/local/bin/start-container"]`, healthchecks, `queue` + `mailpit` services), `docker-entrypoint.sh` (APP_KEY gen, wait for pgsql 60×, `migrate --force`, optimize, view:cache), `.env.example` (see README) |

## Commands

```bash
# Tests (MUST run inside the Sail container — see quirks)
sg docker -c "./vendor/bin/sail artisan test"
sg docker -c "./vendor/bin/sail artisan test --filter=AdminAuthTest"

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
curl -s -o /dev/null -w "%{http_code}\n" https://coffee.rizkilab.my.id/
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
10. **Never access `/mnt/c`** (outside WSL) — user explicitly banned it.
11. **Don't commit or push unless explicitly asked.** When asked, use the existing style (e.g. `Add Filament admin panel, replace custom admin auth, add menu resource`).

## Conventions

- **Laravel 13 attribute style**: `#[Fillable(['...'])]`, `#[Hidden(['password', 'remember_token'])]`, casts as `#[Cast]`/property casts — mirror `app/Models/Admin.php`.
- **Price formatting**: integer IDR, rendered as `Rp {{ number_format($item->price, 0, ",", ".") }}` (e.g. `Rp 25.000`) on BOTH home and menu pages. Tests assert this exact string.
- **Localization (mandatory, ALL features)**: every user-facing string — Blade, Filament UI (labels, column headers, badges, action titles, modal submit buttons, notifications, empty states, navigation labels), CLI command output, queued job messages, and user-facing exception messages — must come from `lang/{id,en}/` via `__()`. Indonesian is primary (`id`), English secondary (`en`). Never hardcode English strings in code. Per-feature files (e.g. `lang/id/stock.php`); console output/job messages a person might read are localized too. Exception/log messages kept in English are acceptable; strings rendered to a user are not. Filament package chrome (Save/Delete/search etc.) stays English unless `vendor:publish --tag=filament-translations` is run.
- **Numeric inputs (mandatory)**: any numeric form input (Filament or Blade) must DISPLAY Indonesian thousand/million separators with a period (`25.000`, `1.500.000`) while the DATABASE stores the raw integer. Idiom: `->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))` (Alpine dynamic money mask — NOT literal `'999.999.999'`, which fills left-to-right and mangles input into `500.0`) + `->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')` + `->formatStateUsing()` (idempotent — skip already-dotted state so failed-validation re-renders don't mangle) + `->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))` (mirror `StockItemForm::formatQuantity()`/`rawQuantity()`). Verify the stored DB row, not just the form display.
- **Shop info** must come from `config('shop.*')` in views — never hardcode hours/address/phone in Blade (contact page already does this). All page copy comes from `lang/{id,en}/` — never raw strings in Blade. Day labels: `__("site.days.$day")` keyed by the `config('shop.hours')` code; JSON-LD `dayOfWeek` stays English. WhatsApp prefill: `__('site.wa_message')` (no longer in config).
- **Menu ordering**: `MenuItem::query()->orderBy('sort_order')`; home takes the first 4 as `$highlights`.
- **Filament v5 layout**: resources live at `app/Filament/Resources/<Plural>/` with `Schemas/` + `Tables/` subdirectories; v5 form components (`TextInput`, `TextArea`) live in `Schemas/`, table columns in `Tables/`.
- **Admin auth**: Filament handles login/logout at `/admin`; guard is `admin`, model implements `FilamentUser`. No custom admin controllers or `resources/views/admin/*` views exist anymore (deleted in `05d1826`).
- **Rate limiting**: keep brute-force protection on admin login via the named `admin.login` limiter + `throttle:` middleware. Never re-add `trustProxies(at: '*')`.
- **Tests**: phpunit.xml pins `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` + `FILESYSTEM_DISK=local` — tests are DB-independent. Use `RefreshDatabase` + `$this->seed(MenuSeeder::class)` for menu-dependent tests.
- **Filament test gotchas**: `assertHasFormErrors(['email'])` (NOT `['data.email']` — Filament prepends the `data` path itself); `withServerVariables` does NOT affect Livewire's request IP (test IP-keyed throttling deterministically, e.g. per-email keys); `Cache::flush()` in `setUp` for throttle tests.
- **Test-writing rules (from post-mortems)**: assert on stable strings (URLs, `config('shop.*')` values) not copy/placement; use `url()` helpers not literal hosts (worktree APP_URL is `http://localhost:8081`); `assertSee` escapes input (pass the rendered-entity form, not a literal `&amp;`); widgets are Livewire-lazy — test protected methods via reflection, not `assertSee` on dashboard HTML; static `public/` files shadow routes in prod (e.g. `public/robots.txt` beats a `/robots.txt` route) — check `public/` for collisions.
- **Filament v5 dashboard**: charts need NO package — `ChartWidget` ships in `filament/widgets`; side-by-side stats = one `StatsOverviewWidget` with multiple `Stat::make()`; never use fractional `columnSpan('1/3')` (invalid CSS). `migrate:fresh` silently empties the dev pgsql DB — re-seed after.
- **`docker-entrypoint.sh`** must stay executable (755) — it provisions APP_KEY and migrations on first boot.

## Workflow (user preferences)

- Parallel work is expected via **herdr panes** running **opencode agents** (`herdr agent start --kind opencode`), not just the built-in Task tool. See `.opencode/skills/herdr-parallel/SKILL.md`.
- Feature branches live in herdr worktrees at `~/.herdr/worktrees/coffee-shop/<branch>` and get merged into `main` when ready.
- Verify before reporting: tests in container (`PAO_DISABLE=1`, `--no-coverage`), `vendor/bin/pint`, `php -l` on touched files, HTTP smoke test via `curl` (local `http://localhost` and prod `https://coffee.rizkilab.my.id`).
- The composer scripts are `setup`, `dev` (concurrently: server/queue/logs/vite), `test` — there is no separate `composer test` alias.
