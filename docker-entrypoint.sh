#!/bin/sh
set -e

if [ -z "${APP_KEY}" ]; then
  echo "Generating APP_KEY" >&2
  php artisan key:generate --force --no-interaction
fi

attempt=0
until php artisan migrate --force --no-interaction; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 60 ]; then
    echo "Database migration failed after 60 attempts" >&2
    exit 1
  fi
  echo "Waiting for database (attempt $attempt)..." >&2
  sleep 3
done

php artisan optimize --no-interaction
php artisan view:cache --no-interaction

# Public storage symlink (public/storage -> storage/app/public); --force
# recreates it so it also heals a broken link after a clean checkout, and
# --relative keeps the target valid from both host and container. All app
# containers (laravel.test, queue, scheduler) boot this entrypoint
# concurrently, so tolerate a race where another boot already created the
# link — the outcome is identical whichever container wins.
php artisan storage:link --relative --force --no-interaction || true

exec "$@"
