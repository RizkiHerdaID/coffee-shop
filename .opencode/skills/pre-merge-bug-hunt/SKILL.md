---
name: pre-merge-bug-hunt
description: Use BEFORE merging any worktree/feature branch to main, when the change touches money (POS, shifts, payments, reports), or when the user asks for a bug hunt / audit / "look for bugs" / "anything missed". Full protocol: baseline MCP sweep, parallel 5-agent audit fleet, claim verification at source, live-DB data-integrity proof, content-based Semgrep SAST, cross-agent diff for missed findings, and Vikunja carding. Distilled 2026-08-03 from a full-repo audit that found 30 real issues (incl. a live financial bug proven with real DB data).
---

# Pre-merge bug-hunt / audit protocol

Validated 2026-08-03 on the full coffee-shop repo: 5 parallel explore agents + MCP
sweep → 30 cards, 15 P1, incl. the cash over-tender bug **proven with live DB data**
(payment row recorded the full tendered 100.000 for a 85.500 order; shift 73 closed
"perfect" while the drawer was short by exactly the change).

## When to run

- **MANDATORY before merging any branch that touches money/POS/reports** (orders,
  payments, shifts, cash register, stock, P&L, summary emails) — as a phase of the
  pre-merge gate (AGENTS.md).
- Any "look for bugs / audit / anything missed from previous session" request.
- Periodically on main (feature accumulation drift — stale docs, dead config,
  pin tests that encode bugs).

## Step 1 — Baseline MCP sweep (parallel, ~30s)

Fire these in ONE message before dispatching anything:

- `laravel-boost application-info` (versions — write version-specific code)
- `phpcodearcheology get_health_score` + `get_problems` + `get_hotspots` (churn×CC
  tells you WHERE to point agents; Cashier CC=56 was the top hotspot)
- `postgres analyze_db_health` (index/vacuum/connection health)
- `redis scan_all_keys` (stray keys/queues; this app keeps queue on DB, Redis only
  holds cache)
- `semgrep semgrep_findings` only if `SEMGREP_APP_TOKEN` is set — otherwise skip,
  use the content-based recipe in Step 5.
- `laravel-boost read-log-entries` + `last-error` (distinguish stale artifacts —
  "testing.INFO" E2E logs are noise; a 2-day-old .env error is NOT current)

Hotspot output decides the audit fleet's emphasis (agents read everything anyway).

## Step 2 — Dispatch the 5-agent audit fleet (parallel, Task tool)

Use built-in `explore` agents — the audit is READ-ONLY, no worktree needed. Five
areas, dispatched in ONE message (never sequential):

1. **POS/shift/orders domain**: Cashier page (cart→order→payment→served), ManageShift,
   ShiftReport, Shift/Order/OrderItem/Payment models, enums, print jobs, Z-report
   controller+view, Orders resource. Verify shift math (salesTotal/paymentsByMethod/
   expectedCash/discrepancy) line by line.
2. **Filament resources + auth**: panel provider, Login (rate-limit key), Admin model,
   ALL 7+ resources (Schemas/Tables/RelationManagers), widgets. Check the numeric-mask
   idiom, enum-instance handling, cascade deletes, authorization.
3. **Services/jobs/commands/ops**: AiCopyService, FonnteWhatsApp, all console commands,
   bootstrap/app.php (middleware/schedule/exception handling), middleware, queue
   config, compose.yaml (scheduler!), .env.example vs config keys.
4. **Public site + i18n**: PageController, ALL Blade views, ALL lang files id/en
   (programmatic key parity), Vite/Tailwind, robots/sitemap/JSON-LD, XSS posture,
   env.example completeness, public/ static files shadowing routes.
5. **Tests/schema/factories**: ALL migrations vs models (types, indexes, cascades),
   ALL test classes (gaps, weak tests, tests that PIN bugs), factories, phpunit.xml,
   AGENTS.md claims vs reality (test counts, HEAD, model list).

**Prompt contract (every agent, verbatim rules):**
- READ-ONLY — do NOT write or edit any files.
- "Audit thoroughly (read every file completely). Assume there ARE bugs — be
  skeptical. Verify claims by reading actual code, not guessing."
- Cite `file:line` for EVERY finding.
- Report format (sections, in order):
  `BUG (wrong behavior): description, file:line, why wrong, suggested fix`
  `EDGE CASE (unhandled): description, file:line, scenario, suggested fix`
  `RISK (concurrency/security/perf): description, file:line`
  `HARDCODED STRING (i18n violation): file:line, string`
  `MISSING TRANSLATION KEY: key, which file, id or en`
  `VERIFIED-OK list: invariants you confirmed hold` ← forces evidence-based
  checking and tells you what NOT to re-audit.
- Return the COMPLETE report (do not summarize away file:line detail).

## Step 3 — Claim verification (the anti-hallucination gate)

NEVER merge agent findings into the report/cards unverified. Rules:

- **Spot-check every P0/P1 claim at source** yourself (read the exact file:line).
  In the 2026-08-03 run, 100% of P0/P1 claims verified, but one P3 claim was
  WRONG (agent said the receipt prints a negative "KEMBALIAN" line; the code's
  `paid_total > net_total` guard makes it unreachable — verified as false).
- **Two-agent convergence = high confidence** (the over-tender bug appeared in
  agents 1 AND 2 independently — treat as confirmed).
- **A claim about the same file from two agents that contradicts = investigate**
  (the 650.000-vs-600.000 expected-cash formula disagreement turned out to be MY
  query missing the shift_cash_movements rows — recompute, don't guess).
- Check agent math by re-deriving it (see Step 4) — the strongest verification is
  real data, not re-reads.

## Step 4 — Live-data proof via postgres (strongest evidence)

Data-integrity queries against the dev DB turn "theoretically wrong" into "broken
right now". Run these AFTER the fleet reports (they tell you what to look for):

```sql
-- over-tender/change bug: paid rows exceed gross total
SELECT o.id, o.order_number, o.total, o.status, SUM(p.amount) AS paid
FROM orders o JOIN payments p ON p.order_id=o.id
WHERE o.status IN ('paid','served') GROUP BY o.id HAVING SUM(p.amount) > o.total;

-- payments after shift close (mutates closed Z-report)
SELECT o.order_number, p.paid_at, s.closed_at
FROM payments p JOIN orders o ON o.id=p.order_id JOIN shifts s ON s.id=o.shift_id
WHERE s.closed_at IS NOT NULL AND p.paid_at > s.closed_at;

-- duplicate order numbers / negative stock / pending-with-payments / zero payments
SELECT order_number, COUNT(*) c FROM orders GROUP BY order_number HAVING c>1;
SELECT id, name, quantity FROM stock_items WHERE quantity < 0;
SELECT o.order_number, SUM(p.amount) FROM orders o JOIN payments p ON p.order_id=o.id
  WHERE o.status='pending' GROUP BY o.id HAVING SUM(p.amount) > 0;
SELECT COUNT(*) FROM payments WHERE amount = 0;

-- closed-shift drawer math: component-wise, then compare with real flows
SELECT s.id, s.opening_cash, s.closing_cash,
  (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN orders o ON o.id=p.order_id
    WHERE o.shift_id=s.id AND p.method='cash' AND p.amount>0) AS cash_in,
  (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN orders o ON o.id=p.order_id
    WHERE o.shift_id=s.id AND p.method='cash' AND p.amount<0) AS cash_out,
  (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN orders o ON o.id=p.order_id
    WHERE o.shift_id=s.id AND p.method='qris') AS qris,
  (SELECT COALESCE(SUM(amount),0) FROM shift_cash_movements m
    WHERE m.shift_id=s.id AND m.type='in') AS dep,
  (SELECT COALESCE(SUM(amount),0) FROM shift_cash_movements m
    WHERE m.shift_id=s.id AND m.type='out') AS petty
FROM shifts s WHERE s.closed_at IS NOT NULL;
-- then hand-compute the REAL drawer (opening + cash in − change − refunds + dep − petty)
-- and diff against the app formula. In 2026-08-03: app said 650.000, real = 635.500,
-- difference == the 14.500 change. PROOF.
```

Quirks: `pg_stat_statements` and `hypopg` are NOT installed — `get_top_queries` and
`analyze_query_indexes` fail. Use `explain_query` on the hot report queries instead
(seq-scan output = index-missing evidence). Prefer `laravel-boost database-query`
(the postgres MCP may point elsewhere; both are read-only).

## Step 5 — Semgrep content-based SAST (the working recipe)

The semgrep MCP cannot see workspace paths (sandboxed — absolute AND container
paths fail with Errno 2). `semgrep_findings` needs `SEMGREP_APP_TOKEN`. The working
path is `semgrep_scan_with_custom_rule` with **file CONTENT** in the payload, and
the rule YAML must have `metavariable-regex` at RULE level, NOT nested under
`patterns` (nested = "Loading rules from local config..." error).

Scan the ~10-12 hot files (Cashier, ManageShift, Order, Shift, StockItem, LoyaltyCard,
PageController, FonnteWhatsApp, AiCopyService, SendSummaryEmail, PrintReceipt,
OrderObserver, SetLocale) with the SQL-interpolation + unsafe-functions rule set
(see the 2026-08-03 scan — clean results on all 12). Read each file fully first
(content must be verbatim). Result is a defensible "SAST clean" verdict.

## Step 6 — Missed-items re-check (the pass that found 30% more)

After consolidating, DIFF every finding line of every agent report against your
card plan / summary — do not trust your first consolidation. In 2026-08-03 this
pass recovered: cart-array validation, refund ValueError, category-chip quoting,
negative receipt rows, overnight-shift view, dead `expected_total` column, cascade
chains, no-authz model, home-blade hardcoded strings, reservation past-time booking,
AiCopy timeouts, numeric-mask gaps, seeder fragility, and the whole test-gaps batch.
A "nothing missed" claim must be earned line-by-line.

## Step 7 — Vikunja carding

- Check for existing cards first (`list_tasks` project 6, `done = false`).
- ONE card per fixable finding; description carries the FULL file:line detail
  (agents' reports get distilled INTO the descriptions).
- Labels: Eisenhower 1=P1, 2=P2, 3=P3, 4=P4. Bucket 17 (Pending) via
  `move_task_to_bucket` after create.
- **Board-verification quirk: `list_buckets` shows `count: 0` for every bucket
  even after successful moves — the move response's `bucket_id` is authoritative,
  and `list_tasks` (filter `done = false`) is the reliable count.**
- Post the strongest live-data proof as a task COMMENT (id 62 on the over-tender
  card is the pattern to follow).

## Step 8 — Report + fix fleet

Report to the user: verified-OK list (what's clean), P0/P1 with file:line, the
live-data evidence, corrections to agent claims, then offer the fix fleet (AGENTS.md
role split; P1 batch first, worktree per fix group, test agents from the contract).

## MCP quirk table

| MCP | Works | Broken / needs workaround |
| --- | --- | --- |
| laravel-boost | app-info, schema, database-query, logs | browser-logs empty for server-rendered app |
| phpcodearcheology | hotspots, cycles (14 cycles incl. 9-class Order/Shift/StockItem), refactor priorities | claims "no tests found" for everything (can't detect PHPUnit) — ignore that line |
| postgres | health checks, explain_query, object details | `pg_stat_statements` + `hypopg` missing → no top-queries / no hypothetical-index analysis |
| redis | scan, inspect | this app keeps queue+sessions in DB; Redis holds only cache — empty-ish is normal |
| semgrep | custom-rule content scans | path-based scans fail (sandbox), findings API needs token |
| vikunja | tasks/labels/buckets/comments | list_buckets counts are unreliable; verify via task API |
