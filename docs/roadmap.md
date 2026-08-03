# Roadmap — Coffee Shop

The full backlog captured 2026-08-02 was **completed in one marathon auto-mode run**
(9 parallel waves, 2 worktrees max at a time, herdr opencode fleets), then hardened
by the 2026-08-03 bug-fix marathon below. Main HEAD: `1086a9d`,
suite **575/575 green** (2452 assertions). Live board: https://vikunja.example,
project "Coffee Shop" (id 6) — all cards in Done (bucket 19); card 130
(AGENTS.md drift / missing factories / docs sync) closed by the fix-docs-cleanup branch.

## 2026-08-03 bug-fix marathon (all on `main`)

Shipped in one day after the pre-merge audit + i18n/ops/messaging waves; every item
is a fix on top of the 2026-08-02 features — see `AGENTS.md` git history for the
full chain and `docs/pos.md` / `docs/owner-tools.md` / `docs/website.md` for behavior.

- `22bacfa` **POS cash bugs** — `payments` now stores the APPLIED amount + a new
  `change` column (surplus never counted as intake), all revenue reports unified on
  NET (`net_total`), concurrency guards around capture/close.
- `d6761c3` **Order audit freeze** — order deletes blocked, refunds only on
  open/unattached shifts (closed Z-reports stay stable), loyalty stamps credited
  exactly once (`orders.loyalty_credited_at`), honest `markPaid`, atomic capture,
  `order_counters` daily sequence.
- `1728a9c` **Ops/infra** — dedicated `schedule` compose service, `storage:link`
  in the entrypoint, `/robots.txt` via `RobotsController` (static file deleted),
  report indexes migration, `AWS_ENDPOINT_URL` for S3.
- `8134983` + `924492a` **Stock/wastage forms** — wastage rows linked to
  `stock_items`, atomic/idempotent PO receiving, Indonesian numeric masks,
  UNIQUE constraints on seeded tables (`menu_items.name`/`stock_items.name`,
  `promos.title`).
- `2b9b22f` **Review role + workflow docs** — fleet boot gains a reviewer pane,
  `docs/workflow.md` synced.
- `76d76e6` **Public-site i18n fixes** — raw JSON-LD price escaped, `SetLocale`
  precedence, lang-switch query-string strip, reservation throttling/honeypot/
  past-time validation, empty states, loyalty threshold config.
- `1086a9d` **Services/messaging hardening** — Fonnte retries + JSON-response
  validation + empty-phone guard, `App\Support\Phone` normalization, AiCopy
  retries + array-content join.

## What shipped this session (2026-08-02, all on `main`)

### Wave 1 — ops/hardening (merged `f9a17da`, `851dfbf`)
- **CI test gate** — `.github/workflows/tests.yml` (PHP 8.3, suite + Pint) gating `deploy.yml`.
- **Automated PostgreSQL backups** — `scripts/db-backup.sh` (pg_dump -Fc, gzip, 14 daily / 8 weekly,
  optional S3 copy), `scripts/restore-drill.sh`, README; VPS crontab `0 2 * * *` documented.
- **Security headers middleware** — HSTS (prod/https only), nosniff, SAMEORIGIN + frame-ancestors,
  Referrer-Policy, CSP report-only; `SecurityHeadersTest`.
- **Laravel Pulse** — `/pulse` gated `auth:admin`, `pulse:check` scheduled, `PulseTest`.
- **CSV/XLSX exports** — Filament exporters on StockItems/Orders/Expenses/PurchaseOrders (`ExportsTest`).
- **Low-stock dashboard widget** — count + one-click stock-in (`LowStockWidgetTest`).

### Wave 2 — booking + shift cash (merged `976eb61`, `a1c6e9d`)
- **Table reservation** — `/reservasi` booking form, Reservations Filament resource, status
  confirm/cancel, `SendReservationConfirmation` job (`ReservationTest`).
- **Mid-shift cash movements** — deposits/petty-cash on ManageShift, `expectedCash()` = opening +
  cashPaid − refunds + deposits − petty_out, Z-report + recent-shifts show movements (`ShiftTest` +17).

### Wave 3 — kitchen + inventory (merged `0596cd8`, `b39607a`)
- **Order/line notes** — nullable notes on orders + order_items, per-line textareas in the cashier
  cart, kitchen ticket sections (`PosCashierTest` +6).
- **PO receiving** — `stock_item_id` on PO lines (description fallback), Receive action → `stockIn()`
  per item + `received` status, `/admin/purchase-orders/restock` suggestions (`PurchaseOrdersTest` +4).

### Wave 4 — POS repeat + P&L (merged `b0646d7`, `fc7762c`)
- **Quick reorder** — `Cashier::repeatOrder()` loads last order, skips unavailable, carries notes.
- **Monthly P&L report** — Filament page `getReportData(from,to)`: revenue − COGS (MenuItem::cogs)
  − expenses by category, margins, inventory valuation (`PnlReportTest` +17).

### Wave 5 — payments + loyalty (merged `12fa63f`, `58c880c`)
- **Split payments** — partial QRIS/e-wallet amounts with pay-rest button, overpay rejected.
- **Loyalty stamps** — phone-keyed `loyalty_cards`, `OrderObserver::saved()` credits on
  paid orders exactly once (`lockForUpdate` re-read + `loyalty_credited_at` stamp,
  hardened 2026-08-03 in `d6761c3`), 10th drink free, `/cek-poin` public page (`LoyaltyTest`).

### Wave 6 — discounts + suppliers (merged `f6a108d`, `e635c55`)
- **POS discounts** — `discount_type` fixed/percent on orders, `Shift::salesTotal()` now NET,
  receipt + WA show discount.
- **Supplier scorecard** — PO count, spend, outstanding, avg lead time, on-time rate +
  `received_at` migration (set by both receive actions).

### Wave 7 — marketing + ops (merged `4c495a1`, `fc873f5`)
- **Promo banners** — `promos` table + resource, dismissible header banner (localStorage),
  AI subtitle action, `PromoSeeder`.
- **Uptime monitoring** — `withoutOverlapping()` + `pingOnSuccessIf(UPTIME_HEARTBEAT_URL)` on all
  4 scheduled commands (Laravel 13 has no `pingWithoutOverlapping()`), README healthchecks/Kuma setup.

### Wave 8 — share + deps (merged `78d07d7`, `146249f`)
- **OG/share images** — branded 1200×630 `og-image.png`, og:image + twitter:image,
  sitemap lastmod + `/qr/{table}` URLs.
- **Dependabot** — weekly composer/npm/github-actions, limit 5, `chore(deps)` prefix.

### Wave 9 + finale — badges, testimonials, forecasting (merged `72e7204`, `b078fbe`, `0bbd92a`)
- **Dietary badges** — `badges` JSON on menu_items, CheckboxList form, chips on menu + home.
- **Testimonials** — visible-scope section on home, `aggregateRating` JSON-LD at ≥3 reviews.
- **Demand forecasting** — `DemandForecastService` (weekday + monthly aggregates) +
  `DemandForecastWidget` chart (`DemandForecastTest` +18).

## Post-marathon notes / future ideas

- `docs/roadmap.md` was the durable mirror; the board is now fully Done — re-open cards for new work.
- Ideas not yet captured as cards: sales by product-class trend alerts, employee time-clock,
  multi-branch, kitchen display screen, MinIO-based menu photo pipeline, Pulse ingestion to prod,
  `migrate:fresh`-safe seeder refresh for demos.
- Ops debt flagged during the run: the docker daemon restarted mid-session (power blip) — all
  containers exited; recovered via `sail up` + container-side cache clears (quirk 8). Worktrees
  now always get `sail down` before workspace close.
