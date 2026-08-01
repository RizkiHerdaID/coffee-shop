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

- A worktree runs its OWN Sail containers (prefix `feature-menu-*`); they hold ports 6379/5432 and conflict with the main repo's `sail up`. Run `sail down` (or `docker stop`) in stale worktrees first.
- After merging a branch with new composer deps, run `sail composer install` in the main repo (container mounts the repo).
- Commit style: imperative summary, e.g. `Add Filament admin panel, replace custom admin auth, add menu resource`.

## When to use Task tool vs herdr panes

- Quick parallel exploration/review → built-in `explore`/`general` Task agents are fine (they appear in the pane too).
- Actual parallel implementation work the user asked for → real herdr panes with opencode agents.
