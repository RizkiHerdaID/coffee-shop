---
name: sail-filament-workflow
description: Use when implementing features in this Laravel + Filament v5 + Sail project (public pages, admin panel, tests, verification). Covers the container verification loop, cache discipline after the docker-entrypoint optimize, Filament v5 gotchas (charts, Livewire-lazy widgets, stat layouts, resource caching), and test-writing rules distilled from past sessions.
---

# Sail + Filament v5 implementation workflow

Lessons learned from the SEO, CTA and orders/dashboard implementation sessions (2026-08-02). Read `AGENTS.md` first — this skill is the implementation-specific companion.

## Cache discipline (the #1 failure mode)

`docker-entrypoint.sh` runs `optimize` (config/route/view cache) at every container boot. After any code change in an already-booted Sail env:

```bash
sg docker -c "php artisan config:clear && php artisan view:clear && php artisan route:clear"
```

Symptoms of a stale cache:

- New route returns 404 (routes cached) — check `bootstrap/cache/routes-v7.php` exists before debugging route code.
- New Filament resource 500s with "Route not defined" (panel routes cached).
- Deleted widget/resource class fatals with "Class not found" (optimized view/components cache).
- Test suite shows CSRF 419s / `data.email is required` in AdminAuthTest (cached config ignores phpunit.xml env overrides like `SESSION_DRIVER=array`).

After clearing, the suite should pass fully; the 4 AdminAuth failures are a cache artifact, NOT pre-existing. `optimize` also runs on every `sail up` — after a recreate, clear caches before trusting anything.

## The verification loop (non-negotiable, in order)

1. Tests in container: `sg docker -c "env PAO_DISABLE=1 ./vendor/bin/sail artisan test --no-coverage"` (workdir = the app dir; PAO disables the pao output swallow, `--no-coverage` avoids the failOnWarning abort).
2. `vendor/bin/pint --test` on touched PHP files (pint rewrites `'a' . 'b'` to `'a'.'b'` — run it, then re-test).
3. `php -l` on touched files (host PHP is fine for this).
4. `sg docker -c "npm run build"` after ANY view/CSS change — a stale `public/build` (gitignored) causes HTTP 500 or stale CSS.
5. Smoke-test live: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:PORT/` + grep the rendered HTML (e.g. `curl -s http://localhost:8081/menu | rg "Rp 25.000"`).

For Filament admin work, the panel also needs the cache cleared (see above); a fresh resource needs `php artisan optimize:clear` + re-optimize inside the container before it renders.

## Filament v5 gotchas

- **Charts need no package**: `filament/charts` does not exist for v5 — `ChartWidget` + `getType()` ship in `filament/widgets`. Verify a package exists on Packagist BEFORE `composer require`.
- **Widgets are Livewire-lazy**: `assertSee` on the dashboard HTML cannot verify widget content. Test the widget's protected methods (via reflection) plus the page's 200 status.
- **Stats layout**: side-by-side stats = ONE `StatsOverviewWidget` with multiple `Stat::make()` (built-in `getColumns()` handles layout). Never use fractional `columnSpan('1/3')` — produces invalid grid-column CSS.
- Resource layout convention: `app/Filament/Resources/<Plural>/` with `Schemas/` + `Tables/` subdirectories, v5 form components (`TextInput`, `Select`, `TextArea`) from `Filament\Forms`.

## Test-writing rules (from the CTA + SEO sessions)

- Assert on **stable strings**: URLs, `config('shop.*')` values — not copy/placement. Copy-driven `assertSee('GoFood & GrabFood')` breaks on every redesign.
- Use `url()` helpers, never literal hosts — worktree APP_URL is `http://localhost:8081`, not `localhost`.
- `assertSee` escapes its input: `'GoFood & GrabFood'` matches the rendered `&amp;`, but passing `&amp;` literally double-escapes and fails. Check rendered HTML, not assumed output.
- Static files shadow routes: `public/robots.txt` (tracked) beats a `/robots.txt` route behind nginx — check `public/` for collisions when adding routes.
- Use `$this->seed(MenuSeeder::class)` + `RefreshDatabase` for menu-dependent tests (sqlite in-memory per phpunit.xml).

## Sail environment footguns

- `sail artisan migrate` right after a recreate may report a bogus `pgsql` DNS error — retry or use `docker exec -u sail coffee-shop-laravel.test-1 php artisan ...` before assuming a real fault.
- `migrate:fresh` silently empties the dev pgsql DB — re-seed afterwards (`php artisan db:seed`).
- Root-owned `bootstrap/cache` after a container boot breaks `optimize` — fix with `docker exec <container> chown -R sail:sail bootstrap/cache`.
- Fresh worktrees need `cp -al <main-repo>/vendor <main-repo>/node_modules <worktree>/` before `sail up` (hardlinks, instant).
- Env regeneration (`scripts/worktree-env.sh --force`) wipes APP_KEY — check for the script warning and run `php artisan key:generate` if the app 500s with MissingAppKeyException.
