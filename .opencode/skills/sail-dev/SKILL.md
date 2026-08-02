---
name: sail-dev
description: Use when running Laravel commands, tests, Pint, migrations, seeders, npm/Vite builds, or inspecting the DB in this coffee-shop project. Covers the Sail (Docker) command recipes, the sg docker wrapper, PAO_DISABLE test invocation, and troubleshooting container issues (stale Vite build, Redis RDB crash-loop, port conflicts, unhealthy laravel.test).
---

# Sail dev workflow (coffee-shop)

All Laravel tooling runs inside the Sail Docker stack. Host PHP cannot run the test suite (no `pdo_sqlite`/`pdo_pgsql`, `.env` points at Docker-network hostnames), and there is no Node.js on the host.

## The `sg docker` wrapper

Fresh shells don't have the `docker` group loaded (user added after login). Plain `docker`/`sail` gives permission denied. Always wrap:

```bash
sg docker -c "./vendor/bin/sail artisan test"
```

Do NOT use `sudo` (no passwordless sudo) and do NOT keep retrying plain `docker` — it will fail the same way every time.

## Test suite

```bash
# In the container, with the pao output wrapper disabled and no coverage
sg docker -c "PAO_DISABLE=1 ./vendor/bin/sail artisan test --no-coverage"

# Single file / filter
sg docker -c "PAO_DISABLE=1 ./vendor/bin/sail artisan test --filter=AdminAuthTest --no-coverage"

# Fast parallel loop (~4.5x faster; brianium/paratest, dev dep)
sg docker -c "PAO_DISABLE=1 ./vendor/bin/paratest --no-coverage --processes 8"
```

- `PAO_DISABLE=1` is required: the `laravel/pao` package swallows PHPUnit output in agent environments.
- `--no-coverage` is required: `failOnWarning=true` in `phpunit.xml` makes the "no code coverage driver" warning abort the whole run.
- phpunit.xml pins `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `FILESYSTEM_DISK=local` — tests don't touch the dev DB.
- `.phpunit.result.cache` permission warnings inside the container are harmless.
- **Parallel tests are safe**: each worker gets its own `sqlite :memory:` DB + `array` cache/session (`CACHE_STORE=array` in phpunit.xml) — no shared state. `artisan test` stays serial (nicer reporting); use `paratest` for the fast loop. CI runs `paratest --processes 4` (GitHub runners have ~2 cores).

## Artisan, seeders, DB

```bash
sg docker -c "./vendor/bin/sail artisan migrate"
sg docker -c "./vendor/bin/sail artisan db:seed"
sg docker -c "./vendor/bin/sail artisan tinker"

# Inspect the DB directly (container name pattern: coffee-shop-pgsql-1)
sg docker -c "docker exec coffee-shop-pgsql-1 psql -U sail -d coffee_shop -c 'SELECT email FROM admins;'"
```

Seeded admin: `admin@example.com` / `password` unless `.env` sets `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD`.

## Vite / frontend assets

`public/build` is gitignored and generated in the container. A missing build → HTTP 500 (`@vite` throws); a stale build → old CSS served (container mounts the repo, so a rebuild elsewhere may not match what the container serves).

```bash
# if node_modules is missing in the container
sg docker -c "./vendor/bin/sail npm install --ignore-scripts"
# after ANY view/Tailwind change:
sg docker -c "./vendor/bin/sail npm run build"
```

Always re-check with `curl -s -o /dev/null -w "%{http_code}\n" http://localhost/` after a build.

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `Permission denied` on docker/sail | Use `sg docker -c "..."` |
| Redis crash-loops | Stale RDB written by newer Redis. `sail down`, `docker volume rm coffee-shop_sail-redis`, `sail up -d` |
| `Port 6379/5432 already in use` | Worktree Sail containers still running (e.g. `feature-menu-redis-1`). `sail down` in the stale worktree or `docker stop` those containers |
| `laravel.test` unhealthy, can't resolve `pgsql` | Leftover containers lost their network. `sail down` + `sail up -d` (full recreate) |
| HTTP 500 on pages | Likely missing `public/build` — run the npm build steps above |
| Tests pass locally, fail in CI/other env | Host PHP can't run the suite — run everything in the container |
