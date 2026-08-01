---
name: herdr-parallel
description: Use when the user asks for parallel work, parallel agents, "use herdr pane", or "use opencode in a pane". Covers running multiple opencode agents in herdr panes, pane split/run/read, and the herdr git-worktree feature-branch workflow for this project (~/.herdr/worktrees/coffee-shop/<branch>).
---

# herdr parallel agents + worktrees

The user expects parallel work to run in **herdr panes running opencode agents** (`herdr agent start --kind opencode`), not just the built-in Task tool. Feature branches live in herdr worktrees.

## Panes

```bash
herdr pane list                # current panes; the agent session runs in e.g. w1:p1
herdr pane current
herdr pane split --direction right --cwd /home/rizki/projects/coffee-shop
herdr pane split --pane w1:p1 --direction down --cwd /home/rizki/projects/coffee-shop

herdr pane run w1:p3 "sg docker -c './vendor/bin/sail artisan test --filter=AdminAuthTest' && echo DONE"
herdr pane read w1:p3 | tail -30   # read pane output after a sleep
```

Pitfalls learned in prior sessions:

- **Prompt quoting**: single-quoted prompts containing apostrophes break the shell command (`ops` agent's prompt failed to submit this way). Avoid apostrophes or escape them.
- **`herdr pane run ... --wait` returns early** — it matches the first state change, not task completion. After dispatching, `sleep` then `herdr pane read <pane>`.
- **Check the pane, don't trust acknowledgement** — fast models may acknowledge a task without doing it; read the pane output to confirm work happened.
- **Permission prompts block agents** — if a pane is stuck, read its output; the agent may be waiting on a permission prompt that needs approval or needs its task simplified to avoid it.

## Starting opencode agents in panes

```bash
herdr agent start --kind opencode
```

then submit each agent's task prompt. Use the project conventions in `AGENTS.md` (e.g. `sg docker` for every container command, `PAO_DISABLE=1` for tests). Split tasks along file boundaries so agents don't edit the same files concurrently.

## Worktrees (feature branches)

Worktrees live at `~/.herdr/worktrees/coffee-shop/<branch>`:

```bash
git worktree list
git -C ~/.herdr/worktrees/coffee-shop/feature-menu status
git log --oneline main..feature-menu          # review before merging
git diff --stat main feature-menu
git merge feature-menu -m "Merge feature-menu: <summary>"
```

- A worktree runs its OWN Sail containers (prefix `feature-menu-*`) with its own compose network and volumes. All worktrees can be up SIMULTANEOUSLY because each gets its own host ports (see below).
- After merging a branch with new composer deps, run `sail composer install` in the main repo (container mounts the repo).
- Commit style: imperative summary, e.g. `Add Filament admin panel, replace custom admin auth, add menu resource`.

### Per-worktree ports (no more sail down conflicts)

`.env` is gitignored, so each worktree carries its own port mapping. Generate it with the helper:

```bash
scripts/worktree-env.sh <worktree-dir> <slot 1-4> [--dev] [--force]
```

Slot table (host ports; main repo keeps the defaults 80/5173/5432/6379/9000/8900/1025/8025):

| Slot | App | Vite | Postgres | Redis | MinIO | MinIO ctrl | Mailpit smtp | Mailpit UI |
|---|---|---|---|---|---|---|---|---|
| 1 | 8081 | 5174 | 5433 | 6380 | 9001 | 8901 | 1026 | 8026 |
| 2 | 8082 | 5175 | 5434 | 6381 | 9002 | 8902 | 1027 | 8027 |
| 3 | 8083 | 5176 | 5435 | 6382 | 9003 | 8903 | 1028 | 8028 |
| 4 | 8084 | 5177 | 5436 | 6383 | 9004 | 8904 | 1029 | 8029 |

CRITICAL: the script only overrides `APP_PORT` / `VITE_PORT` / `FORWARD_*`. Never change `DB_PORT`, `REDIS_PORT`, `MAIL_PORT`, or `AWS_ENDPOINT_URL` in a worktree `.env` — inside the container the app reaches services by hostname (`pgsql`, `redis`, `mailpit`, `minio`) within its own compose network; those values must stay at the `.env.example` defaults. The script appends only the forward vars and rewrites `APP_URL` to `http://localhost:<APP_PORT>`.

Workflow per worktree:

```bash
git worktree add ~/.herdr/worktrees/coffee-shop/feature-menu -b feature-menu
scripts/worktree-env.sh ~/.herdr/worktrees/coffee-shop/feature-menu 1 --dev
cp -al <main-repo>/vendor <main-repo>/node_modules <worktree>/   # fresh checkouts have neither
sg docker -c "cd ~/.herdr/worktrees/coffee-shop/feature-menu && ./vendor/bin/sail up -d"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8081/
```

`cp -al` hardlinks vendor/node_modules from the main checkout (instant, no duplication; a later `composer install` in the worktree only breaks links for changed files). Remember `npm run build` in the worktree after view changes — `public/build` is gitignored and a missing build means HTTP 500 on every page.

RAM note: each stack is 5 containers (laravel.test, queue, pgsql, redis, minio, mailpit); keep only worktrees you are actively testing in. Stale containers from worktrees created BEFORE this scheme still hold the default ports — `sail down` those once before relying on slots.

## When to use Task tool vs herdr panes

- Quick parallel exploration/review → built-in `explore`/`general` Task agents are fine (they appear in the pane too).
- Actual parallel implementation work the user asked for → real herdr panes with opencode agents.
