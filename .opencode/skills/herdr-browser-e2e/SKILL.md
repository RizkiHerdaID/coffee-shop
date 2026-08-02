# herdr Browser E2E

Drive real-browser E2E tests against the local coffee-shop app using the herdr
Browser plugin CLI (`official.browser`). Distilled from the 2026-08-02 four-agent
parallel E2E run (all 4 agents PASS; lessons below cost ~1h of agent time each).

## Prerequisites (already installed on this machine)

- **bun** at `~/.local/bin/bun` (installed via `curl -fsSL https://bun.sh/install | BUN_INSTALL=$HOME/.local bash`)
- **Chrome for Testing** at `~/.local/share/cft/chrome-linux64/chrome` (v151; symlink `~/.local/bin/chromium`). NOT system-installable (no sudo): the missing shared libs (`libnspr4.so`, `libnss3.so`, `libsmime3.so`, `libasound.so.2`, `libsoftokn3.so` + nss modules) were extracted from an `ubuntu:24.04` container (`apt-get install -y libasound2t64 libnspr4 libnss3`) into `~/.local/share/cft/libs/`.
- **MANDATORY env for every CLI call** (Chrome needs the libs + the daemon must know the binary):
  ```bash
  export LD_LIBRARY_PATH=$HOME/.local/share/cft/libs
  export HERDR_BROWSER_CHROME=$HOME/.local/share/cft/chrome-linux64/chrome
  ```
  Without `LD_LIBRARY_PATH`, Chrome crashes at startup (`libnspr4.so` missing).
- Plugin root: `~/.config/herdr/plugins/github/official.browser-ff2a44eccae9`. CLI: `bun run <plugin>/src/cli.ts`.

## CLI cheatsheet

```bash
CLI="$HOME/.local/bin/bun run $HOME/.config/herdr/plugins/github/official.browser-ff2a44eccae9/src/cli.ts"
$CLI open "http://localhost/menu"          # navigate (returns title/url)
$CLI eval "http://localhost/menu" "document.title"   # navigate THEN eval (atomic — use this for fresh-session assertions!)
$CLI eval "location.pathname"              # eval on current view (WARNING: view is about:blank after daemon restart)
$CLI text                                  # visible page text
$CLI selector-click "a[href='/menu']"      # click first match
$CLI wait "document.querySelector('h1')" 15000   # poll until truthy
$CLI console                               # console entries for the CURRENT view
$CLI screenshot --output /tmp/x.png        # screenshot (do NOT pass a URL — it re-navigates and wipes Livewire state)
$CLI status / views / connect --view <id>  # inspect
$CLI stop                                  # graceful daemon shutdown
```

## THE critical truths (learned the hard way — read before writing any test)

1. **Cookies NEVER survive daemon restarts.** This Chrome-for-Testing build never
   flushes the cookie DB to disk (verified: profile `Default/Cookies` stays at 0
   rows while running, after SIGTERM, and after natural shutdown). A login session
   dies with its daemon. **Login state lives only as long as ONE daemon.**
2. **Keep ONE daemon alive for a compound flow.** Every `open`/`eval` reuses the
   running daemon. Do NOT restart the daemon between steps of an authenticated
   flow. Restart only when it crashes. The daemon's view lease is ~10s and it
   self-shuts down ~3s after the last view is gone — keep commands chained, or
   heartbeat the view (`POST /views/heartbeat`) every 5s in the background.
3. **`HERDR_BROWSER_PROFILE_ROOT` is STRIPPED by the plugin.** `applyBrowserConfigEnv`
   deletes it when the plugin has no config file (`profileRoot: null`). Real
   isolation between concurrent agents comes from `HERDR_PLUGIN_STATE_DIR` +
   `HERDR_SESSION` (both feed `chromeProfileDir`/`daemonStateFile`). Never rely on
   the profile-root var reaching the daemon.
4. **Per-agent isolation (parallel runs):** each agent needs its OWN
   `HERDR_BROWSER_DAEMON_STATE` file AND `HERDR_PLUGIN_STATE_DIR`. Two daemons
   sharing a profile dir = `SingletonLock: File exists` + Chrome abort.
5. **Orphaned Chrome trees kill CLI-spawned daemons.** After crashes, an orphan
   Chrome can hold a profile's SingletonLock; every CLI spawn then aborts
   instantly (daemon exits 0, CLI times out with `timed out waiting for daemon
   state`). Fix: `pkill -f "<agent-profile-dir>"` (never a broad `chrome-linux64`
   pkill — that kills other agents' sessions).
6. **Never run two CLI/br.sh calls concurrently** (even in one message): each
   kills the other's daemon via shared lock paths.
7. **Load matters:** ~90 concurrent chrome procs from 3 other agents made single
   attempts fail intermittently. Expect retries; verify with `daemon.json` +
   `kill -0 <pid>` between attempts.
8. **CLICK the interactive elements — presence is NOT function.** The menu
   category-filter bug (multi-token `classList.remove('a b c')` throwing
   `InvalidCharacterError`, handler died on first line) survived a 4-agent E2E
   run because every agent asserted the chips RENDERED but nobody clicked one.
   A real CDP click (selector-click, same daemon, fresh page) + assert the DOM
   effect is the only valid proof for any button/filter/toggle.

## Working patterns

### Public pages (no login) — per-call daemons are fine
Each call opens fresh; use atomic `eval "<url>" "<expr>"`. Console check needs
the daemon's HTTP API right after `open` (`curl` the daemon's `/console` while
the view lives) — `console` on a fresh daemon hits about:blank.

### Authenticated admin flows — one daemon, compound commands
1. Start the daemon with the CLI once (env exported): `open http://localhost/admin/login`
2. Login in ONE step (Filament inputs have NO `name` attrs; plugin `type` crashes
   on `email`/`password` inputs — `setSelectionRange InvalidStateError`):
   ```js
   // eval on the login page:
   const set=(el,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,v);el.dispatchEvent(new Event('input',{bubbles:true}));};
   set(document.querySelector('input[type=email]'),'admin@example.com');
   set(document.querySelector('input[type=password]'),'password');
   document.querySelector('form').requestSubmit(); true
   ```
   or Livewire-direct: `$wire.set('data.email','...'); $wire.set('data.password','...'); $wire.authenticate()`
3. Keep the daemon alive across subsequent `eval`s; verify logged-in state with
   `eval "location.pathname"` (expect `/admin`), re-open `/admin/login` and
   re-login only if it flipped.
4. Restart the daemon only on crash; re-login after every restart.

### One-shot async scripts (bulk steps in one call)
`eval "<url>" "<async IIFE>"` (awaitPromise is on): fill form, submit, await
navigation/timeout, then return a JSON summary. Used successfully for the public
site (click switcher → read result), since each br.sh call kills the daemon.

## Known plugin/Filament quirks (verified)

- `type` command breaks on `email`/`password` inputs (focus calls
  `setSelectionRange`). Flip input `type` to `text` first, or use the native
  setter + events (above). Money-mask fields (`type=text` w/ Alpine mask) DO work
  with `type` — real CDP keyboard input lets `x-mask:dynamic` run naturally.
- Money-mask fields pre-fill with `0` (`->default(0)`): typing appends after it
  (`200000` → `0.200.000`). Clear the field first. DB still stores the integer.
- Filament searchable/combobox selects ignore synthetic clicks — real mouse
  `click <x> <y>` on the trigger then on `li[data-value]` (coords per render).
- Delete-confirm buttons use `wire:target="callMountedAction"` — synthetic
  clicks ignored; real mouse click required. Confirmation modals are `.fi-modal`.
- Dashboard chart widgets lazy-load (IntersectionObserver) — `scrollIntoView()`
  before asserting canvas/svg.
- Admin money columns render `Rp 25.000,00` (Filament 2-decimal default) vs the
  public site's `Rp 25.000` — both correct per conventions.
- Login throttle is per-email+IP (5/min): a throwaway email can be throttled
  without locking out `admin@example.com`; 6th attempt shows
  "Terlalu banyak permintaan. Silakan coba lagi dalam N detik."
- `screenshot <url>` re-navigates (wipes Livewire state) — use plain
  `screenshot --output`.
- 404 pages log exactly one expected console entry (the 404 resource itself).

## Running the fleet (this project)

```bash
herdr pane split --pane <main> --direction right   # ×4, one per agent
herdr agent start <name> --kind opencode --pane <pane>
herdr agent prompt <pane> "<scenarios>"            # end with: herdr agent prompt wR:p1 "DONE ..."
```

- Write ONE contract file first (`/tmp/opencode/browser-e2e-contract.md`) with
  app URL, creds, per-agent env table, login procedure, data hygiene
  (`E2E-<agent>-` prefix, delete what you create, verify DB), report format.
- **Require a STRUGGLES section in every report** (5-15 bullets: what wasted
  time, plugin confusion, daemon issues, flaky waits) — it feeds this skill.
- Agents must report to files (`/tmp/opencode/browser-e2e/<agent>/report.md`) and
  notify the main session only via `herdr agent prompt wR:p1 "DONE ..."`.
- Screenshot evidence per step; verify DB rows via
  `sg docker -c "docker exec coffee-shop-pgsql-1 psql -U sail -d coffee_shop -t -c '<SQL>'"`.
- App creds: `admin@example.com` / `password`; app at `http://localhost`
  (Sail stack must be up; clear caches after any `sail up`).

## Cleanup after the run

```bash
pkill -f "daemon.ts --state-file" ; pkill -f "chrome-linux64"   # kill leftover daemons/browsers
rm -rf /tmp/opencode/browser-e2e                                # session artifacts
```
