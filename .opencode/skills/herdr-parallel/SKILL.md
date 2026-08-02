---
name: herdr-parallel
description: Use when the user asks for parallel work, parallel agents, "use herdr pane", or "use opencode in a pane". Covers running multiple opencode agents in herdr panes, pane split/run/read, and the native herdr worktree flow (herdr worktree create + herdr-plus auto-layout) for feature branches (~/.herdr/worktrees/coffee-shop/<branch>).
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
- **Check the pane, don't trust acknowledgement** — fast models may acknowledge a task without doing it; read the pane output to confirm work happened. Agents may also silently ignore instructions (a "don't commit" instruction was violated) — verify the pane, and if a violation matters, dispatch a corrective prompt.
- **Permission prompts block agents** — if a pane is stuck, read its output; the agent may be waiting on a permission prompt that needs approval or needs its task simplified to avoid it.
- **`herdr agent read` only exposes the ~30 visible terminal lines** — an agent's full report scrolls out of reach. To collect a long result: prompt the agent to reprint it in a compact block that fits one screen (≤22 lines), or have it write the result to a file. Split long outputs into "print section X only" follow-ups.
- **Agents and panes disappear when the session ends** — research agents' panes closed after their sessions, losing direct access. Capture lessons/post-mortems WHILE agents are alive, and have deliverables written to files (e.g. `docs/`) rather than only spoken into the pane.
- **Branch worktrees from the current `main` HEAD** — branches created from an older base show unrelated infra commits as deletions in `git diff main <branch>`; the 3-way merge still resolves cleanly (main keeps its versions), but the diff noise is confusing. Re-create stale-base branches or accept the artifact.
- **Post-mortems** — before closing agent panes, ask each agent (and yourself) for `LESSONS LEARNED` bullets; fold them into AGENTS.md quirks and these skills. This session's lessons are captured in `.opencode/skills/sail-filament-workflow/SKILL.md`.

## Starting opencode agents in panes

```bash
herdr agent start --kind opencode
```

then submit each agent's task prompt. Use the project conventions in `AGENTS.md` (e.g. `sg docker` for every container command, `PAO_DISABLE=1` for tests). Split tasks along file boundaries so agents don't edit the same files concurrently. In a worktree workspace the "agent" tab already runs opencode (auto-layout) — use `herdr agent prompt <target> "..."` / `herdr agent read` / `herdr agent wait` on it; you do NOT need `agent start` there.

## herdr-plus extras (user has the plugin installed)

- **Projects** (action `cloudmanic.herdr-plus.projects` from herdr's plugin action menu; README suggests binding `prefix+up`): fuzzy-pick a declarative workspace template from `~/.config/herdr/plugins/config/cloudmanic.herdr-plus/projects/*.toml`; **ctrl+g** in the picker opens the project as a git worktree (empty branch → herdr generates `worktree/<name>`, bare names get `[worktree] branch_prefix` from the plugin's `config.toml`, names with `/` used verbatim).
- **Quick Actions** (action `cloudmanic.herdr-plus.quick-actions`, suggested `prefix+down`): fuzzy launcher for one-off commands from `quick-actions/*.toml` (global) and `<repo>/.herdr-plus/quick-actions/` (repo-scoped, runs from the directory you launched from).
- Neither needs config for the worktree flow to work — the `worktrees/` layout above is the only file that matters for worktrees.

## Worktrees (feature branches)

Native herdr flow (herdr ≥0.7). A worktree is a **normal herdr workspace with Git provenance** — `herdr worktree create` does `git worktree add` AND opens a workspace, grouped with the parent repo in the sidebar (which shows `branch` + `git_status`). Do NOT hand-roll `git worktree add` + `herdr pane split` anymore — that bypasses grouping, events, and auto-layout. The branch-slug path is exactly the old convention: `~/.herdr/worktrees/coffee-shop/<branch>` (config `worktrees.directory`, default `~/.herdr/worktrees`).

```bash
# create worktree + workspace in one step (branch from HEAD; --base REF to fork elsewhere)
herdr worktree create --cwd /home/rizki/projects/coffee-shop --branch feature-menu --no-focus --json
#   → JSON has workspace_id / tab_id / pane_id; workspace label = branch slug
herdr worktree open --cwd /home/rizki/projects/coffee-shop --branch feature-menu   # existing checkout

herdr worktree list                                # all worktrees + open_workspace_id
git -C ~/.herdr/worktrees/coffee-shop/feature-menu status
git log --oneline main..feature-menu               # review before merging
git diff --stat main feature-menu
git merge feature-menu -m "Merge feature-menu: <summary>"

herdr worktree remove --workspace <id>             # git worktree remove (never deletes branch); --force for dirty
herdr workspace close <id>                         # herdr state only — checkout stays; NOT a worktree remove
```

- The **agent pane comes automatically**: herdr-plus auto-layout (`~/.config/herdr/plugins/config/cloudmanic.herdr-plus/worktrees/coffee-shop.toml`) reacts to the `worktree.created`/`worktree.opened` events and opens tabs (agent + terminal) into the fresh workspace — verified: plugin log says `applied worktree layout "coffee-shop.toml" to repo "coffee-shop" (branch "x"): 2 tab(s)`. Check with `herdr plugin log list --plugin cloudmanic.herdr-plus`. Layout files: `repo = "coffee-shop"` (case-insensitive) or `repo = "*"` wildcard; optional `branch`; specificity repo+branch > repo > wildcard+branch > wildcard; idempotent (skips workspaces that already have tabs). The tab "agent" runs `opencode` in the worktree cwd — an agent is live there immediately.
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

Workflow per worktree (right after `herdr worktree create` — do this BEFORE the agent in the "agent" tab starts testing, and note herdr's generated checkout path is the same one the helper expects):

```bash
WT=~/.herdr/worktrees/coffee-shop/feature-menu
scripts/worktree-env.sh $WT 1 --dev
cp -al <main-repo>/vendor <main-repo>/node_modules $WT/   # fresh checkouts have neither
sg docker -c "cd $WT && ./vendor/bin/sail up -d"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8081/
```

`cp -al` hardlinks vendor/node_modules from the main checkout (instant, no duplication; a later `composer install` in the worktree only breaks links for changed files). Remember `npm run build` in the worktree after view changes — `public/build` is gitignored and a missing build means HTTP 500 on every page. After `sail up` in a worktree, clear the entrypoint's cached config/views/routes in that worktree (AGENTS.md quirk 8), else the test suite 419s.

RAM note: each stack is 5 containers (laravel.test, queue, pgsql, redis, minio, mailpit); keep only worktrees you are actively testing in. Stale containers from worktrees created BEFORE this scheme still hold the default ports — `sail down` those once before relying on slots.

## Hardware guardrails (16GB / 12-thread dev machine — learned the hard way)

The host is a Ryzen 7 5700X (6c/12t), **16GB RAM, zero swap by default**. A full parallel run costs more than the machine has: 4 worktrees × 6 containers ≈ 3–4GB, and EVERY opencode agent (lead + sub-agents) ≈ 700MB RSS. One fleet of 4 worktrees × ~4 agents hit 14.7GB used / 880MB free with load 29 — an OOM kill was one Vite build away.

**Before fanning out agents** (run these, then budget):
- `free -h` — if available < ~2GB, do NOT start more agents; kill idle panes first.
- `herdr agent list` — count live agents; budget 700MB each.
- `sg docker -c "docker ps | wc -l"` — each worktree stack ≈ 1GB.

**Hard rules for multi-agent runs:**
1. **Max 2 worktrees × 2 sub-agents per lead** on this hardware (≈6 agents total). 4 full fleets (15 agents) is NOT viable — it nearly OOMed.
2. **Kill idle sub-agent panes immediately** (`herdr pane split`d agents that finished or haven't been prompted yet) — idle opencode panes still hold ~700MB. Never leave a completed sub-agent pane alive while more work is pending.
3. **Stagger verification**: do NOT run test suites + Vite builds across all worktrees simultaneously — that is the peak-memory spike. Two at a time max.
4. **Free stacks as soon as branches merge**: `sg docker -c "cd <wt> && ./vendor/bin/sail down"` + close the workspace recovers ~1.5GB each.
5. **8GB swapfile is the one-time fix**: `sudo fallocate -l 8G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab` — turns OOM risk into graceful slowdown. (Passwordless sudo is NOT available to agents; the user runs this.)
6. **Check `uptime` load before starting**: if load > 12 on a 12-thread box, finish or kill existing work before fanning out more.

## When to use Task tool vs herdr panes

- Quick parallel exploration/review → built-in `explore`/`general` Task agents are fine (they appear in the pane too).
- Actual parallel implementation work the user asked for → real herdr panes with opencode agents.
