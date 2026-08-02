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

herdr pane run w1:p3 "sg docker -c './vendor/bin/sail artisan test --filter=AdminAuthTest' && echo __TESTS_OK__"
herdr pane wait-output w1:p3 --match __TESTS_OK__ --timeout 600000   # event-driven wait; never sleep
herdr pane read w1:p3 --source recent-unwrapped --lines 120          # read log-style output
```

Pitfalls learned in prior sessions:

- **Prompt quoting**: single-quoted prompts containing apostrophes break the shell command (`ops` agent's prompt failed to submit this way). Avoid apostrophes or escape them.
- **`herdr pane run` has no completion wait** — `--wait` matches the first state change, not task completion. Never `sleep` then read: use the wait/read primitives below (validated 2026-08-02 by a 4-model research fleet — the sleep+read pattern was our single biggest reliability gap).

**Wait/read primitives (official herdr; replace ALL sleep-polling):**
```bash
herdr pane wait-output <pane> --match __TESTS_OK__ --timeout 600000    # text-level wait for shells/tests/builds (--regex for patterns)
herdr agent prompt <pane> "<task>" --wait --timeout 600000             # lifecycle wait: submit + wait for settled idle/done/blocked
herdr agent wait <pane> --until idle --until done --timeout 600000     # wait for a specific settled state (crash-fallback for DONE-message)
herdr agent wait <pane> --until blocked --timeout 5000                 # permission-prompt rescue: then read, approve, or simplify
herdr agent read <pane> --source recent-unwrapped --lines 120          # reads for logs/transcripts (soft wraps joined)
```
- `agent prompt --wait` / `agent wait` are lifecycle-based, NOT turn-scoped: a working agent may settle on its current turn → false completion. Always pair with the report-file-on-disk check — `/tmp/opencode/<batch>-<role>-report.md` stays the completion evidence.
- `pane wait-output` matches text already on screen — use batch-unique markers (`echo __TESTS_OK__`) or anchored regexes so a stale line cannot satisfy the wait.
- Always pass `--timeout` — both wait commands have NO default and wait indefinitely (a hung wait pins a ~700MB pane).
- Wrong pane state (`unknown`, idle-while-working)? `herdr agent explain <pane>` shows which detection rule matched and why (manifest source, fallback reason) — the first debugging step.
- OpenCode is screen-manifest detected by default; `herdr integration install opencode` makes lifecycle hook-authoritative (`idle`/`working`/`blocked` semantics become reliable).
- Alt-screen caveat: full-screen agents (OpenCode/Claude Code) lose scrolled rows to herdr's host scrollback — `--lines` cannot recover them. THIS is why report files stay the PRIMARY channel for fleet results (on-pane summaries ≤22 lines + disk report), and why pane reads use `--source recent-unwrapped`.
- **Check the pane, don't trust acknowledgement** — fast models may acknowledge a task without doing it; read the pane output to confirm work happened. Agents may also silently ignore instructions (a "don't commit" instruction was violated) — verify the pane, and if a violation matters, dispatch a corrective prompt.
- **Permission prompts block agents** — if a pane is stuck, read its output; the agent may be waiting on a permission prompt that needs approval or needs its task simplified to avoid it.
- **`herdr agent read` only exposes the ~30 visible terminal lines** — an agent's full report scrolls out of reach. To collect a long result: prompt the agent to reprint it in a compact block that fits one screen (≤22 lines), or have it write the result to a file. Split long outputs into "print section X only" follow-ups.
- **Agents and panes disappear when the session ends** — research agents' panes closed after their sessions, losing direct access. Capture lessons/post-mortems WHILE agents are alive, and have deliverables written to files (e.g. `docs/`) rather than only spoken into the pane.
- **Completion notification (do NOT poll)**: the main session (`wE:p1`) should never `sleep`-poll agent status. Every lead prompt MUST end with: on completion, run `herdr agent prompt wE:p1 "DONE <branch>: commit <sha>, tests <N> passed, <files>, post-mortem: ..."` — this injects a message into the main opencode session, which wakes and reacts (merge, cleanup). Main session reads `herdr agent read <pane>` only on notification, not on a timer. Keep the DONE-injection for its PAYLOAD (commit SHA, test counts — `agent wait` cannot carry one); add `herdr agent wait <lead-pane> --until idle --until done --timeout 600000` as the deterministic completion check and crash-fallback (an agent that dies never sends DONE).
- **Branch worktrees from the current `main` HEAD** — branches created from an older base show unrelated infra commits as deletions in `git diff main <branch>`; the 3-way merge still resolves cleanly (main keeps its versions), but the diff noise is confusing. Re-create stale-base branches or accept the artifact.
- **Post-mortems** — before closing agent panes, ask each agent (and yourself) for `LESSONS LEARNED` bullets; fold them into AGENTS.md quirks and these skills. This session's lessons are captured in `.opencode/skills/sail-filament-workflow/SKILL.md`.

## Starting opencode agents in panes

```bash
herdr pane split --pane <current-pane> --direction right --cwd <repo>   # plain sessions have NO pre-provisioned panes — split first
herdr agent start --kind opencode --pane <new-pane-id>                  # bare `herdr agent start` without --pane fails: "unknown option"
herdr agent start <name> --kind opencode --pane <id> -- --model opencode-go/<model>   # multi-model fleets: native args pass after `--` (proven 2026-08-02: pro/glm/grok/qwen in 4 panes)
herdr agent rename <pane-id> <role-name>                                # e.g. docs-pos, docs-website — readable fleet lists; rename EVERY role at fleet boot (stable targets)
herdr agent prompt <pane-id> "<task>"                                   # then submit the task
```

then submit each agent's task prompt. Use the project conventions in `AGENTS.md` (e.g. `sg docker` for every container command, `PAO_DISABLE=1` for tests). Split tasks along file boundaries so agents don't edit the same files concurrently. In a worktree workspace the "agents" tab already runs 3 opencode agents (auto-layout) — the lead agent is the FIRST pane; sub-agents are the OTHER pre-provisioned panes in the same tab. Use `herdr agent prompt <pane> "..."` / `herdr agent read` / `herdr agent wait` on them. Do NOT run `herdr pane split`/`agent start` from inside a lead — it opens new terminals instead of staying in the shared layout. Idle pre-provisioned panes hold ~700MB each — close panes the lead doesn't need (`herdr pane close <pane>`) or keep fleet sizes matched to task size.

**Docs-only / no-code batches (no worktree needed):** when the task touches only
disjoint files that all land on `main` together (docs reformatting, research,
report writing), run the fleet on the MAIN checkout — each agent gets one source→
target pair, WRITES files only (never `git add`/`git rm`/`git commit` — concurrent
writes to the shared index race and can corrupt it; the lead stages everything
after the batch), writes its report to `/tmp/opencode/<batch>-<role>-report.md`,
and notifies via `herdr agent prompt w14:p1 "DONE ..."`. No Sail stack, no ports,
no worktree lifecycle. Close the panes (`herdr pane close`) when all DONE
messages arrive — each idle agent holds ~700MB. Don't plan to reuse agents from
previous sessions: panes/agents from ended workspaces are already gone (`herdr
pane list` shows only live ones).

**Lead-prompt template (standardize every dispatch):**
```
TASK: <one line>. FIRST read /tmp/opencode/<batch>-contract.md — it defines file
ownership (you are the <role> agent: <source> → <target>), conventions, accuracy
rules, report file /tmp/opencode/<batch>-<role>-report.md, and the DONE
notification. <Role-specific: what to verify/build>. Work on
/home/rizki/projects/coffee-shop, <docs-only|write-files-only>, NO commits, NO
git add/rm/commit. On completion write the report file, then run:
herdr agent prompt w14:p1 "DONE <role>: <what changed>, <verified N claims>,
<post-mortem>"
```
Every lead prompt MUST end with the DONE-notification instruction — that injects
a message into the main opencode session, which wakes and reacts (merge,
cleanup). Main session reads `herdr agent read <pane>` only on notification, not
on a timer, and should ACK the DONE message (a one-liner) so the agent knows it
was received.

**Failure/retry protocol:** if a DONE report is thin (no verified claims, no
report file on disk), or a pane goes quiet >~10 min past its siblings' average, do
NOT wait — dispatch a corrective prompt: "You reported DONE but <report file
missing | claims unverified | file ownership violated>. Re-read the contract,
finish the work, rewrite the report, re-notify." If a pane is stuck on a
permission prompt, confirm it semantically with `herdr agent wait <pane>
--until blocked --timeout 5000`, then read its output and either approve the
permission or simplify the task. Never accept a DONE message as evidence of
completion — the report file on disk + lead spot-check are the evidence.

**Fleet sizing (v3 rule — role-based, layered):** match sub-agents to the feature's LAYER COUNT, NOT "always 3". A dedicated read-only "verify" pane is NOT used anymore — verification happens twice already (lead runs full suite + Pint before DONE; main session re-runs the suite on main before push). A verify pane just burns ~700MB idle (community-validated: herdr has no built-in subagent orchestration; the role-split convention is per-team — see herdr discussion #1274).
- S-effort, single-layer (e.g. maps embed, one blade view) → lead only; close unneeded pre-provisioned panes immediately
- M-effort, single-layer (e.g. one Filament resource) → lead + tester (tester authors the feature tests in parallel against the contract)
- M/L-effort, multi-layer (backend + frontend + tests, e.g. POS milestones) → lead + backend + frontend + tester. Roles split by file boundary: backend = models/migrations/controllers/services/jobs/Filament PHP; frontend = blade views/css/js/vite; tester = tests/ only.
- Never more than one agent per file set; never a pure observer pane.
Coordination overhead (prompting, verifying, folding) eats the parallel gain below ~M effort.

## V2 orchestration flow (main session, per batch)

Order of operations for a parallel batch — designed to remove polling, duplicated research, and message storms:

1. **Write a shared contract file FIRST** — before prompting any lead, the main session writes `/tmp/opencode/<branch>-contract.md` containing: feature spec, existing code to MIRROR (paths), conventions to follow (money mask idiom, localization, enum-cast handling), the worktree path, app URL/port, and a "do not touch" list of files owned by other branches. Kills redundant research — every agent starts from the same context (the expenses lead wrote one spontaneously mid-session; make it standard).
2. **Dispatch leads in parallel** — one `herdr agent prompt <lead-pane>` per worktree, each ending with the DONE-notification instruction (see Panes pitfalls). Keep file boundaries disjoint across branches.
3. **Sub-agents write reports to files** — each sub-agent writes its deliverable summary to `/tmp/opencode/<batch>-<role>-report.md` (fixed naming: batch + role, so "all reports exist on disk" is a mechanical glob check; their on-pane reports scroll out of reach). The lead verifies on disk, never from pane output. Agents WRITE files only — the lead stages all git changes after the batch (concurrent `git add` on a shared index is a race).
4. **Only the LEAD notifies** — on completion the lead runs `herdr agent prompt wE:p1 "DONE <branch>: ..."`. Sub-agents never message the main session — prevents message storms when several finish at once. Main session ACKs each DONE so agents know it was received.
5. **Main reacts on notification, never on a timer** — merge, run the suite once, cleanup (see Worktree lifecycle below), and free RAM.

Worktree lifecycle (proven sequence, order matters):
```bash
herdr worktree create --cwd /home/rizki/projects/coffee-shop --branch feature-x --no-focus --json
scripts/worktree-env.sh $WT <slot> --dev --force && cp -al <main>/vendor <main>/node_modules $WT/
sg docker -c "cd $WT && ./vendor/bin/sail up -d" && sg docker -c "cd $WT && ./vendor/bin/sail artisan key:generate && php artisan config:clear && php artisan view:clear && php artisan route:clear"
# equalize + boot the fleet: scripts/herdr-fleet-boot.sh $BRANCH 4  (splits to a 2x2 grid, renames, starts opencode per pane, verifies idle)
# manual split fallback: herdr pane split --pane <id> --direction right|down --ratio 0.5  (--ratio makes EXACT layouts — no delta math)
# ... work happens, lead notifies DONE ...
rtk git merge feature-x -m "Merge feature-x: <summary>"
herdr workspace close <workspace-id>                 # removes herdr state AND deletes the git branch
sg docker -c "docker run --rm -v ~/.herdr/worktrees/coffee-shop:/wt alpine rm -rf /wt/feature-x"   # root-owned bootstrap/cache/filament files block plain rm
rtk git worktree prune && rtk git branch -d feature-x || true   # branch usually already gone with the workspace
```

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

- **Layout-first fleet boot (validated 2026-08-03)**: the worktree layout (`~/.config/herdr/plugins/config/cloudmanic.herdr-plus/worktrees/coffee-shop.toml`) now opens the **`agents` tab with ONE plain shell pane** (label `fleet-boot`) + a `terminal` tab — it does NOT launch opencode anymore (that caused the 4:2:1:1 ugly auto-split and opencode launch races at creation). After `herdr worktree create`, run **`scripts/herdr-fleet-boot.sh <branch> [N]`** (N=2|3|4, default 4): it waits for the agents tab, splits the pane into N EQUAL panes (`--ratio 0.5` — N=4 gives a perfect 2×2 grid, N=3 gives three equal columns), renames them (lead/backend/frontend/tester), then starts one opencode agent per pane via `herdr agent start <name> --kind opencode --pane <id>` and verifies all reach `idle`. Idempotent — re-run after a partial boot.
  - Agent names are GLOBAL in herdr and constrained to `[a-z0-9_-]{1,32}`: the script names them `<role>-<branch>` (e.g. `backend-feature-menu`) so parallel worktrees never collide (`agent_name_taken`).
  - ALL agents live in the SAME tab/view — leads must NOT spawn new panes/terminals (that scatters agents into separate terminals, seen in the suppliers/expenses fleets). The lead prompts its pre-provisioned siblings: `herdr pane list` to find pane IDs, then `herdr agent prompt <pane> "task"`. Check with `herdr plugin log list --plugin cloudmanic.herdr-plus`. Layout files: `repo = "coffee-shop"` (case-insensitive) or `repo = "*"` wildcard; optional `branch`; specificity repo+branch > repo > wildcard+branch > wildcard; idempotent (skips workspaces that already have tabs).
  - **Boot troubleshooting** (agents stuck/not running at initiation): 1) `herdr agent explain <pane>` — first debug step (why detection says unknown/idle); 2) `herdr agent wait <pane> --until idle --timeout 60000` — opencode TUI cold start takes seconds per pane; 3) if the pane shows a bare shell prompt instead of opencode, restart it with `herdr agent start <name> --kind opencode --pane <id>` (or fallback `herdr pane run <pane> opencode`); 4) `herdr agent start` requires the pane at an interactive shell prompt — it FAILS if opencode is already running there; 5) NEVER close ALL panes of a tab — closing the last pane removes the tab (agents tab gone = `herdr-fleet-boot.sh` "agents tab not found"; recreate the worktree workspace if that happens); 6) `blocked` at boot is a PERMISSION PROMPT, not a failure — the boot script reports it as a note (rescue: `herdr agent wait <pane> --until blocked --timeout 5000`, read, approve or simplify); 7) **external-directory permission prompts (validated 2026-08-03 drill): worktree agents treat the MAIN checkout and `/tmp/opencode` as EXTERNAL — a contract referencing them blocks the pane at an "Access external directory" dialog.** Fixed in `opencode.json` via `permission.external_directory` (`~/projects/coffee-shop/**` + `/tmp/opencode/**` allow, `*` ask — restart opencode to apply). Manual rescue for running fleets: `herdr pane send-keys <pane> enter` accepts the dialog default; contracts should prefer WORKTREE-relative paths when the branch was forked from current main.
  - **Fleet identity**: the boot script writes `/tmp/opencode/fleet-<branch>-roster.md` (role | pane | agent name | main/worktree paths). Sub-agents self-orient from it; contracts should tell agents to verify their own pane/name via `herdr agent list` instead of trusting the prompt. Agent names are `<role>-<branch>-<hash4>` (checksum makes 32-char truncation collision-proof).
  - **Fleet drill (fake task) — validate the pipeline before trusting a real batch**: 1) write a bounded contract (read-only: read repo files, write ONE report file to `/tmp/opencode/fleet-test-<role>-report.md`, reply `DONE <role>` exactly; reference ABSOLUTE main-checkout paths — a worktree forked before recent commits may lack the newest scripts/skills); 2) prompt all N panes in parallel with the role in each message; 3) verify: all agents return to `idle`/`working`, every report file exists on disk with `status: FLEET_TEST_DONE`; 4) have the LEAD cross-prompt the other panes (`herdr agent prompt <pane> "reply with your pane id and role" --wait --timeout 90000`) to validate inter-agent dispatch — validated 2026-08-03: 3/3 prompts accepted with correct metadata; 5) read the reports for workflow feedback (agents consistently find script/skill gaps — 4 agents surfaced 5 distinct improvements in one drill).
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
1. **Max 2 worktrees at a time** (each opens 3 opencode agents via auto-layout = 6 agents ≈ 4.2GB) on this hardware. 4 full fleets (12 agents) is NOT viable — it nearly OOMed at 15 agents.
2. **Kill idle sub-agent panes immediately** — idle opencode panes still hold ~700MB. Never leave a completed sub-agent pane alive while more work is pending. (Layout panes can be closed per-run with `herdr pane close`/workspace close when idle.)
3. **Stagger verification**: do NOT run test suites + Vite builds across all worktrees simultaneously — that is the peak-memory spike. Two at a time max.
4. **Free stacks as soon as branches merge**: `sg docker -c "cd <wt> && ./vendor/bin/sail down"` + close the workspace recovers ~1.5GB each.
5. **8GB swapfile is the one-time fix**: `sudo fallocate -l 8G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab` — turns OOM risk into graceful slowdown. (Passwordless sudo is NOT available to agents; the user runs this.)
6. **Check `uptime` load before starting**: if load > 12 on a 12-thread box, finish or kill existing work before fanning out more.

## When to use Task tool vs herdr panes

- Quick parallel exploration/review → built-in `explore`/`general` Task agents are fine (they appear in the pane too).
- **Read-only audits / pre-merge bug-hunts → built-in `explore` Task agents, NOT herdr panes** (no worktree, no Sail stack, no RAM concern beyond the agents themselves). 5 parallel agents + MCP verification protocol: `.opencode/skills/pre-merge-bug-hunt/SKILL.md`.
- Actual parallel implementation work the user asked for → real herdr panes with opencode agents.
