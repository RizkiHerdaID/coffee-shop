# Coffee Shop

A Laravel 12 application for a coffee shop, combining a public marketing site with an admin back office. It ships with a Docker-based development environment built on [Laravel Sail](https://laravel.com/docs/sail), extended with a database-driven menu, admin authentication, an object-storage-backed filesystem (MinIO), and a mail preview server (Mailpit).

## Features

- **Landing pages** — hero, menu, and contact pages styled with Tailwind CSS 4.
- **Database-driven menu** — menu items are stored in PostgreSQL and rendered from the database via a `MenuItem` model.
- **Admin authentication** — a password-protected admin area with its own login flow.
- **Local services** — PostgreSQL, Redis, MinIO (S3-compatible storage), and Mailpit run as Docker services via Sail.

## Prerequisites

- **Docker** with the Compose plugin (required — the whole stack runs in containers).
- **PHP 8.3+ and Composer** (optional — only needed if you want to run Sail without the `./vendor/bin/sail` wrapper or work with Composer tooling on the host).

## Quick Start

```bash
# 1. Configure the environment
cp .env.example .env

# 2. Build the app image (first time only)
./vendor/bin/sail build

# 3. Start the stack
./vendor/bin/sail up -d
```

The first boot is fully automated by `docker-entrypoint.sh`, which runs inside the `laravel.test` container:

- generates `APP_KEY` in `.env` if it is empty,
- waits for PostgreSQL and runs `php artisan migrate --force` (retries for up to 60 attempts),
- caches the app config and compiled views,
- then hands off to Sail's `start-container`, which remaps the `sail` user to your host UID and launches `php artisan serve` via supervisord.

After the stack is up, the site is available at `http://localhost` (or `APP_PORT` if you changed it), and the Vite dev server runs on port 5173.

### Seed the database

```bash
./vendor/bin/sail artisan db:seed
```

This seeds the database and creates the admin account from the `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` environment variables. If they are not set, the fallbacks are used:

| Variable | Fallback |
| --- | --- |
| `ADMIN_NAME` | `Admin` |
| `ADMIN_EMAIL` | `admin@example.com` |
| `ADMIN_PASSWORD` | `password` |

> **WARNING:** change `ADMIN_*` in `.env` before seeding anything that will face real traffic. The fallback password is for local development only.

### Run the tests

```bash
./vendor/bin/sail artisan test
```

## Local Services

| Service | Purpose | Host port |
| --- | --- | --- |
| `laravel.test` | Laravel app (`php artisan serve` via supervisord) | `APP_PORT` (default `80`) |
| `queue` | `php artisan queue:work --sleep=3 --tries=3` | — |
| `pgsql` | PostgreSQL 18 database | `FORWARD_DB_PORT` (default `5432`) |
| `redis` | Redis 7.4 cache/session store | `FORWARD_REDIS_PORT` (default `6379`) |
| `minio` | S3-compatible object storage | `9000` / console `8900` |
| `mailpit` | SMTP mail catcher + web UI | `1025` / UI `8025` |

### Mail

Outgoing mail uses SMTP and is delivered to **Mailpit** (see `MAIL_*` in `.env`). Open `http://localhost:8025` (`FORWARD_MAILPIT_UI_PORT`) to inspect captured messages.

### Queues

A dedicated `queue` container runs `php artisan queue:work` continuously, so jobs are processed automatically once you switch `QUEUE_CONNECTION` from `sync` to `redis` in `.env` and restart the stack:

```bash
./vendor/bin/sail restart
```

## Production Notes

- **Secrets** — generate a real `APP_KEY`, use a strong `DB_PASSWORD`, and set real `ADMIN_*` credentials. Never reuse the defaults from `.env.example`.
- **Storage** — `FILESYSTEM_DISK=s3` points at MinIO. In production, replace `AWS_ENDPOINT_URL` with your S3 provider endpoint (or switch to `FILESYSTEM_DISK=local`) and use real `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` values instead of the `MINIO_ROOT_*` references.
- **Mail** — point `MAIL_HOST` / `MAIL_PORT` at a real SMTP provider and configure `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_ENCRYPTION` accordingly.
- The template defaults to `APP_ENV=production` with `APP_DEBUG=false`. For local development, set `APP_ENV=local` and `APP_DEBUG=true` (see the comment in `.env.example`).

## Uptime monitoring

The app exposes a built-in Laravel health endpoint at `/up` (returns HTTP 200 with an "Application up" page when the app is healthy). Configure an external monitor to check it every 5 minutes:

### External check via healthchecks.io

1. Sign in at https://healthchecks.io, go to **Checks** → **Add check**.
2. Choose **HTTP(s)** with URL `https://coffee-shop.example/up`, period **5 minutes**, grace **~10 minutes** (2 missed checks).
3. Save — you'll get a Slack/email notification when the site is down.

### External check via Uptime Kuma

1. In your Uptime Kuma instance, **Add New Monitor**.
2. Type **HTTP(s)** (or **HTTP(s) Browser** for full page loads), URL `https://coffee-shop.example/up`.
3. Set interval to **300 seconds** (5 min) and assign a notification group.

The `/up` endpoint needs no auth and is safe to hit from outside — it performs a framework health check only.

### Scheduler heartbeat

If the scheduler stops (e.g. cron removed, `schedule:run` no longer invoked), external site checks stay green while summary emails and low-stock alerts silently stop. To catch that, the scheduled commands (`summary:send` daily/weekly, `stock:alert-low`, `pulse:check`) ping a heartbeat URL after each successful run, guarded by `withoutOverlapping()` so a stuck run never fakes a heartbeat. Setup:

1. Set `UPTIME_HEARTBEAT_URL` in production `.env`:
   - **healthchecks.io**: create a **Cron** check, set the period to match the most frequent command (1 minute — `pulse:check` runs every minute) and copy its **ping URL** (e.g. `https://hc-ping.com/<uuid>`).
   - **Uptime Kuma**: **Add New Monitor** → type **Push**, copy the **Heartbeat URL** (e.g. `https://your-kuma/api/push/<token>`), leave interval at 60 s.
2. Rebuild the config cache so the value is picked up: `php artisan config:cache`.
3. Verify with `php artisan schedule:test` (pick e.g. `pulse:check`) — it runs the chosen command with its hooks, so the ping URL should get a hit and the monitor should flip to "up" within one interval. (`schedule:list` does not show ping hooks — it only lists expressions and next-due times.)

Leave `UPTIME_HEARTBEAT_URL` empty in development — no pings are sent and the schedule behaves exactly as before.

## Backups

`scripts/db-backup.sh` dumps the PostgreSQL database (`pg_dump -Fc`, gzipped) into `storage/app/backups/` and keeps a rolling retention: **14 daily** dumps plus **8 weekly** snapshots (one per ISO week, created on the first day of a new week). It runs inside the pgsql container, so no host PostgreSQL client is needed — only `docker` access.

```bash
./scripts/db-backup.sh
```

On the VPS, install it in the crontab (every day at 02:00 server time):

```
0 2 * * *  /opt/coffee-shop/scripts/db-backup.sh >> /var/log/coffee-shop-backup.log 2>&1
```

Useful environment overrides (defaults work in both dev and prod):

| Variable | Default | Purpose |
| --- | --- | --- |
| `PG_CONTAINER` | auto | Container name for `docker exec`. Auto-detects `docker compose exec pgsql` first; falls back to `coffee-shop-pgsql-1` (prod). Dev worktrees use `<branch>-pgsql-1`. |
| `DB_USER` / `DB_NAME` | from `.env` (`sail` / `coffee_shop`) | Database credentials. |
| `BACKUP_DIR` | `storage/app/backups` | Where dumps are written. |
| `RETENTION_DAILY` / `RETENTION_WEEKLY` | `14` / `8` | How many dumps to keep. |
| `S3_BACKUP=1` + `S3_BUCKET` (+ optional `S3_PREFIX`) | off | Also copy the newest dump to S3 via the `aws` CLI (creds from `.env` or the environment). Expire old S3 objects with a bucket lifecycle rule. |

### Restore drill

`scripts/restore-drill.sh` verifies the latest backup is restorable without touching the live database: it restores the newest dump into a throwaway `*_restore_drill_*` database, runs sanity queries, prints `PASSED`, and drops the throwaway database. Run it manually or on a schedule (e.g. weekly):

```bash
./scripts/restore-drill.sh                      # newest daily dump
./scripts/restore-drill.sh backups/foo.dump.gz  # a specific dump
```

Both scripts accept the same `PG_CONTAINER` / `DB_USER` / `DB_NAME` / `BACKUP_DIR` overrides, and neither needs the app itself to be running — only the pgsql container.
