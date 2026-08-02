---
name: playwright-mcp-e2e
description: Use for real-browser E2E verification inside an opencode session via the Playwright MCP server (@playwright/mcp) — pre-merge UI spot-checks, admin panel walkthroughs, console/network error capture (404s, 500s, JS errors), money-flow screens (POS, Z-report, receipts). Complements the herdr Browser plugin (which runs outside the session); this one runs INSIDE opencode with direct tool access. Also use when the user asks to "test in the browser" or "check the UI".
---

# Playwright MCP E2E (in-session browser automation)

Wired into `opencode.json` (`playwright` entry): local stdio server via
`npx -y @playwright/mcp@latest --isolated --headless`. Host has Node 24 via nvm;
Chromium is installed to `~/.cache/ms-playwright` (first run:
`npx playwright install chromium`).

## When to use this vs the herdr Browser plugin

| | Playwright MCP (this skill) | herdr Browser plugin |
| --- | --- | --- |
| Runs | INSIDE opencode — tools in the chat | Outside, via `herdr agent` + Chrome for Testing |
| Best for | Single-session spot checks, pre-merge audit E2E passes, console/network capture | Parallel multi-agent compound flows (login persistence across agents, screenshots) |
| State | `--isolated` = fresh profile per session; NO cookie persistence between sessions | Cookies never flush to disk; keep ONE daemon per flow |
| Tokens | Heavy (accessibility snapshots in context) — keep flows short | Cheaper per step |

Audit E2E (one short flow, in-session) → Playwright MCP. Fleet E2E → herdr.

## Tool usage patterns

1. `browser_navigate` — `http://localhost` (main) or `http://localhost:8081..8084` (worktree slots). Dev URLs only; the MCP is not a security boundary.
2. `browser_snapshot` — returns the accessibility tree with element **refs**. Refs are valid for THAT snapshot only — re-snapshot after any navigation/DOM change.
3. `browser_find` — search the snapshot for text/regex; cheaper than full snapshots when you know the label.
4. Interact with the ref: `browser_click`, `browser_type`, `browser_fill_form`, `browser_hover`, `browser_drag`, `browser_file_upload`, `browser_handle_dialog` (JS alerts/confirms).
5. `browser_console_messages` — `level: "error"` catches JS exceptions (this caught the stale-Vite-build 500 class of bugs; a clean `[]` = no JS errors).
6. `browser_network_requests` — inspect status codes/bodies; e.g. menu photo 404s (`/storage/...` missing = `storage:link` not provisioned), `@vite` manifest 500s.
7. `browser_evaluate` — run JS in the page for assertions not exposed by the snapshot (e.g. read `localStorage`, check a DOM value).
8. `browser_close` — end the session; `--isolated` discards all state.

## Coffee-shop verification recipes (pre-merge audit pass)

- **Site smoke**: navigate `/` → snapshot → assert hero/menu section → `browser_console_messages` (errors) → `browser_network_requests` (no 4xx/5xx; photos 200). Repeat `/menu`, `/contact`, `/cek-poin`, `/reservasi`.
- **Admin login + dashboard**: navigate `/admin` → fill `email`/`password` (seeded `admin@example.com`/`password`) → snapshot → assert dashboard stats render (widgets are Livewire-lazy — reload once if empty). Console clean.
- **POS money flow**: navigate `/admin` (as cashier page) → add an item → snapshot the cart → pay → check the change-due notification text on screen; verify receipt browser view at `/pos/receipt/{order}` (auth:admin) lines sum correctly.
- **Z-report reconcile**: open a closed shift's Z-report page → read the printed cash/refund/expected lines via `browser_find` → manually sum them; they must reconcile with `expectedCash()` (see pre-merge-bug-hunt skill for the SQL side).
- **Language switcher**: navigate `/menu?lang=en` → click the ID switch → snapshot shows Indonesian (catches the query-string-defeats-switcher bug class).

## Gotchas (learned patterns)

- **No DISPLAY in this WSL/agent env** — the config forces `--headless`. For visual debugging flip the flag to headed (or use snapshots; no vision needed).
- **Money-mask inputs** (Filament `$money` mask): fill the MASKED form (`"100.000"`), not the raw integer — mirror what a cashier types.
- **Livewire async**: after clicking Filament buttons (Save, pay, close shift), re-snapshot before asserting — the DOM updates over the wire; a stale ref fails with "element not found".
- **Element refs go stale** after every render — never reuse a ref from an older snapshot; `browser_find` + fresh snapshot instead.
- **`browser_snapshot` is token-heavy** — prefer `browser_find` with a regex for targeted assertions; keep audit flows under ~10 interactions.
- **First navigation may be slow** (Chromium cold start) — `--timeout-navigation` default 60s is fine.
- **Sandbox failure** ("SUID sandbox is not supported") in some containers: add `--no-sandbox` to the command args in `opencode.json`.
- **Never point it at production** — the server docs say it is not a security boundary; dev/localhost only.
- **Concurrent sessions**: `--isolated` means parallel opencode sessions don't fight over a profile (the herdr daemon uses its own Chrome for Testing — no conflict).
