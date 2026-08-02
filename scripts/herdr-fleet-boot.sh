#!/usr/bin/env bash
#
# herdr-fleet-boot.sh — build a clean equal-pane fleet layout in a herdr
# worktree, THEN start an opencode agent in each pane.
#
# Layout-first, agents-second: the worktree layout (.config/herdr/plugins/
# config/cloudmanic.herdr-plus/worktrees/coffee-shop.toml) now opens the agents
# tab with ONE plain shell pane; this script splits it into N equal panes and
# only then launches opencode via `herdr agent start` — no more 4:2:1:1
# auto-splits and no opencode launch races at worktree creation.
#
# Usage:
#   scripts/herdr-fleet-boot.sh <branch> [N]     # N = 2|3|4 (default 4)
#
# Idempotent: safe to re-run after a partial boot (closes stray panes, only
# starts agents that are missing, skips existing ones).
#
# Requires: herdr CLI, python3 (JSON parsing). Run from the main checkout.

set -euo pipefail

BRANCH="${1:?usage: herdr-fleet-boot.sh <branch> [N]}"
N="${2:-4}"

case "$N" in
    2|3|4) ;;
    *) echo "error: N must be 2, 3 or 4 (got $N)"; exit 2 ;;
esac

ROLES=(lead backend frontend tester)   # trimmed to N below
ROLES=("${ROLES[@]:0:N}")

py() { python3 -c "$1"; }

echo "==> resolving workspace for branch '$BRANCH'"
WS="$(herdr worktree list | py "
import json, sys
d = json.load(sys.stdin)['result']
for wt in d['worktrees']:
    if wt['branch'] == '$BRANCH' and wt['open_workspace_id']:
        print(wt['open_workspace_id'])
        break
")"

if [ -z "$WS" ]; then
    echo "error: no open workspace for branch '$BRANCH' (is the worktree created and open?)"
    exit 1
fi
echo "    workspace $WS"

WT_PATH="$(herdr worktree list | py "
import json, sys
d = json.load(sys.stdin)['result']
for wt in d['worktrees']:
    if wt['branch'] == '$BRANCH':
        print(wt['path'])
        break
")"
MAIN_PATH="$(pwd)"
echo "    worktree: $WT_PATH"

# Branch slugs can collide after 32-char truncation; add a 4-char checksum.
BRANCH_HASH="$(printf %s "$BRANCH" | cksum | cut -c1-4)"
# Agent names are GLOBAL in herdr — namespace per worktree, [a-z0-9_-]{1,32}.
# Single-line output: <role>-<branch-truncated-to-27>-<hash4>.
agent_name() {
    printf '%s' "$(printf '%s-%s' "$1" "${BRANCH//\//-}" | cut -c1-27)-$BRANCH_HASH"
}

echo "==> waiting for the 'agents' tab"
AGENTS_TAB=""
for _ in $(seq 1 30); do
    AGENTS_TAB="$(herdr tab list | py "
import json, sys
d = json.load(sys.stdin)['result']['tabs']
print(next((t['tab_id'] for t in d if t['workspace_id']=='$WS' and t.get('label')=='agents'), ''))
")"
    [ -n "$AGENTS_TAB" ] && break
    sleep 1
done
if [ -z "$AGENTS_TAB" ]; then
    echo "error: agents tab not found in workspace $WS after 30s"
    exit 1
fi
echo "    tab $AGENTS_TAB"

panes_in_tab() {
    herdr pane list | py "
import json, sys
d = json.load(sys.stdin)['result']['panes']
for p in d:
    if p['workspace_id']=='$WS' and p['tab_id']=='$AGENTS_TAB':
        print(p['pane_id'])
"
}

echo "==> waiting for the agents-tab pane to exist"
BASE=""
for _ in $(seq 1 15); do
    BASE="$(panes_in_tab | head -1)"
    [ -n "$BASE" ] && break
    sleep 1
done
if [ -z "$BASE" ]; then
    echo "error: no pane in agents tab $AGENTS_TAB"
    exit 1
fi

echo "==> shaping layout: $N equal panes"

# Split a pane and return the NEW pane id from the split response (pane-list
# order is NOT reliable — the list does not match creation order).
split_new() {
    herdr pane split --pane "$1" --direction "$2" --ratio 0.5 --no-focus | py "
import json, sys
print(json.load(sys.stdin)['result']['pane']['pane_id'])
"
}

build_shape() {
    # Verified recipe (empirically tested 2026-08-03):
    #   N=4: split BASE right 0.5, then each half down 0.5 -> perfect 2x2 grid.
    #   N=3: split BASE right 0.5, then BASE down 0.5 -> three equal columns
    #        (lead+backend stacked left, frontend full right column).
    #   N=2: split BASE right 0.5 -> 1:1.
    local RIGHT
    RIGHT="$(split_new "$BASE" right)"
    if [ "$N" -ge 3 ]; then
        split_new "$BASE" down >/dev/null
        if [ "$N" -eq 4 ]; then
            split_new "$RIGHT" down >/dev/null
        fi
    fi
    sleep 1
}

# Geometry check: widths must be equal (all columns); for N=4 heights must be
# equal too (true 2x2 grid — catches the split-wrong-pane bug where the right
# column ends up full-height); for N=3 exactly one pane spans the tab height.
shape_ok() {
    herdr pane layout --pane "$BASE" | py "
import json, sys
d = json.load(sys.stdin)['result']['layout']
area = d['area']
ps = d['panes']
widths = sorted(p['rect']['width'] for p in ps)
heights = sorted(p['rect']['height'] for p in ps)
w_ok = max(widths) - min(widths) <= 2
if not w_ok:
    print('    layout UNEVEN widths: %s' % widths); sys.exit(1)
if '$N' == '4':
    ok = max(heights) - min(heights) <= 2
    print('    layout ok: %s x %s' % (widths, heights) if ok else '    layout NOT 2x2: heights %s' % heights)
    sys.exit(0 if ok else 1)
if '$N' == '3':
    ok = sum(1 for h in heights if abs(h - area['height']) <= 2) == 1
    print('    layout ok: %s x %s' % (widths, heights) if ok else '    layout UNEVEN: heights %s' % heights)
    sys.exit(0 if ok else 1)
print('    layout ok: %s' % widths); sys.exit(0)
"
}

# Keep existing fleet panes (re-run idempotency); close only stray extras.
mapfile -t ALL_PANES < <(panes_in_tab)
if [ "${#ALL_PANES[@]}" -gt "$N" ]; then
    for p in "${ALL_PANES[@]:$N}"; do
        herdr pane close "$p" >/dev/null
        echo "    closed stray pane $p"
    done
fi

CURRENT="$(panes_in_tab | wc -l | tr -d ' ')"
if [ "$CURRENT" -eq "$N" ]; then
    shape_ok || {
        echo "    shape wrong for $N panes — rebuilding from $BASE"
        for p in $(panes_in_tab | grep -v "^$BASE$"); do
            herdr pane close "$p" >/dev/null
        done
        build_shape
        shape_ok || { echo "error: layout still uneven after rebuild — inspect with 'herdr pane layout --pane $BASE'"; exit 1; }
    }
elif [ "$CURRENT" -lt "$N" ]; then
    build_shape
    shape_ok || { echo "error: layout uneven — inspect with 'herdr pane layout --pane $BASE'"; exit 1; }
else
    echo "error: $CURRENT panes in agents tab (expected at most $N) — close extras manually"
    exit 1
fi

# Role assignment from geometry (reading order), NOT pane-list order:
# (y,x)-sorted = top-left, bottom-left, top-right, bottom-right = lead,
# backend, frontend, tester.
mapfile -t PANES < <(herdr pane layout --pane "$BASE" | py "
import json, sys
d = json.load(sys.stdin)['result']['layout']
for p in sorted(d['panes'], key=lambda p: (p['rect']['y'], p['rect']['x'])):
    print(p['pane_id'])
")
echo "    panes: ${PANES[*]}"
[ "${#PANES[@]}" -ne "$N" ] && echo "error: expected $N panes, got ${#PANES[@]}" && exit 1
ASSIGN=("${PANES[@]}")

echo "==> starting opencode agents (one per pane)"
for i in "${!ASSIGN[@]}"; do
    ROLE="${ROLES[$i]}"
    PANE="${ASSIGN[$i]}"
    # Agent names are GLOBAL in herdr — namespace them per worktree so parallel
    # fleets don't collide (agent_name_taken). Constraint: [a-z0-9_-]{1,32};
    # BRANCH_HASH suffix makes truncation collision-proof.
    NAME="$(agent_name "$ROLE")"
    if herdr agent list 2>/dev/null | py "
import json, sys
d = json.load(sys.stdin)['result']['agents']
print('yes' if any(a['pane_id'] == '$PANE' for a in d) else 'no')
" | grep -q yes; then
        echo "    $ROLE already running on $PANE — skipping"
        continue
    fi
    echo "    $ROLE -> $PANE (agent '$NAME')"
    herdr agent start "$NAME" --kind opencode --pane "$PANE" >/dev/null || {
        echo "    agent start failed for $PANE; falling back to 'herdr pane run opencode'"
        herdr pane run "$PANE" opencode || echo "    ERROR: opencode did not start in $PANE"
    }
done

echo "==> renaming panes to roles"
for i in "${!ASSIGN[@]}"; do
    herdr pane rename "${ASSIGN[$i]}" "${ROLES[$i]}" >/dev/null 2>&1 || true
done

echo "==> verifying fleet (herdr agent wait — native primitive, no sleep-polling)"
MISSING=""
BLOCKED=""
for PANE in "${ASSIGN[@]}"; do
    if herdr agent wait "$PANE" --until idle --until working --until blocked --timeout 120000 >/dev/null 2>&1; then
        STATE="$(herdr agent list 2>/dev/null | py "
import json, sys
d = json.load(sys.stdin)['result']['agents']
print(next((a['agent_status'] for a in d if a['pane_id']=='$PANE'), 'unknown'))
")"
        case "$STATE" in
            blocked) BLOCKED="$BLOCKED $PANE" ;;
        esac
    else
        MISSING="$MISSING $PANE"
    fi
done

echo
echo "fleet summary:"
herdr agent list 2>/dev/null | py "
import json, sys
d = json.load(sys.stdin)['result']['agents']
for a in d:
    if a['workspace_id']=='$WS':
        print('    %-10s %-10s %-10s %s' % (a.get('name') or '-', a['pane_id'], a['agent_status'], a['cwd']))
"
if [ -n "$BLOCKED" ]; then
    echo "note: panes blocked (permission prompt?):$BLOCKED — rescue: herdr agent wait <pane> --until blocked --timeout 5000, read, approve or simplify"
fi
if [ -n "$MISSING" ]; then
    echo "error: agents not ready:$MISSING"
    echo "rescue: herdr agent explain <pane> | herdr agent wait <pane> --until idle --timeout 60000"
    exit 1
fi

# Fleet roster — the identity hint every sub-agent needs to self-orient.
# Agents read this to learn their own role/pane/agent-name (drill contract).
ROSTER="/tmp/opencode/fleet-$BRANCH-roster.md"
{
    echo "# Fleet roster — $BRANCH (workspace $WS)"
    echo "Main checkout: $MAIN_PATH   |   Worktree: $WT_PATH"
    echo "Contract convention: dispatch prompts carry the role; agents verify via 'herdr agent list'."
    echo
    echo "| role | pane | agent |"
    echo "| --- | --- | --- |"
    for i in "${!ASSIGN[@]}"; do
        echo "| ${ROLES[$i]} | ${ASSIGN[$i]} | $(agent_name "${ROLES[$i]}") |"
    done
} > "$ROSTER"
echo "roster: $ROSTER"
echo "fleet boot OK — prompt agents via: herdr agent prompt <pane> \"task\""
