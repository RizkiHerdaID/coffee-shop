# Roadmap — Coffee Shop

Future work, prioritized with the Eisenhower labels used on the Vikunja board
(project "Coffee Shop", id 6). This is the durable, docs-side mirror of the board's
backlog (snapshot 2026-08-02); the live board remains authoritative for status.

Priority legend: **P1** = Urgent & Important · **P2** = Important / Not Urgent ·
**P3** = Urgent / Not Important · **P4** = Neither.

## P1 — urgent & important

| Feature | Effort | Notes |
| --- | --- | --- |
| CI: run test suite + Pint on every push, gate deploys | S | `deploy.yml` ships to prod without running tests. Add `.github/workflows/tests.yml` (PHP 8.3, composer install, `PAO_DISABLE=1 artisan test --no-coverage`, `pint --test`; sqlite `:memory:` needs no Docker) + `needs: tests` on `deploy.yml`. ~1h, largest safety gap. |
| Automated PostgreSQL backups + restore drill | S-M | **ZERO backups exist** for irreplaceable order/payment/shift data. `scripts/db-backup.sh`: `docker exec pg_dump -Fc` nightly, gzip, retention (14 daily / 8 weekly), optional S3 copy via existing MinIO/AWS config; `scripts/restore-drill.sh` + README; VPS crontab `0 2 * * *`. |

## P2 — important, not urgent

| Feature | Effort | Notes |
| --- | --- | --- |
| Table reservation (booking) page + admin resource | M | Public booking form (name/phone/date/time/party/notes) → reservations table → Filament resource; confirmation via existing FonnteWhatsApp. **CTA "Pesan Meja" is currently a dead end.** Touches: migration, Reservation model, Reservations resource, `PageController::reservation()` + blade, `lang/{id,en}`. |
| Loyalty/stamps program keyed by phone | M | Stamp per order keyed by `customer_phone` (Order create hook next to WA notification), 10th drink free; public "Cek poin" page + redemption via Filament resource. Deps: none. |
| Order/line notes to kitchen ticket (modifiers) | S (notes) / M (presets) | Nullable notes on `orders` + `order_items` (or JSON modifiers), Textarea in cashier cart flow, printed under each line in `PrintKitchenTicket`; later structured presets on `MenuItem`. Baristas can't fulfill custom orders today. |
| Mid-shift cash movements (deposits / petty cash) | M | `shift_cash_movements` table (type in/out, amount, note, admin) + ManageShift UI; `expectedCash()` = opening + cashPaid + cashRefunds − deposits + petty_out; Z-report + recent-shifts SQL update. Fixes most common cashier discrepancy. **Changes shift-math contract → extend ShiftTest.** |
| PO receiving + stock-linked line items | M | Nullable `stock_item_id` on `purchase_order_items` (keep description fallback), auto-fill total from lines, Receive action → `stockIn()` per item + status received; restock suggestions view from low-stock items. Closes the low-stock-alert → restock loop. FLAG: `PurchaseOrdersTest` assertions on description need updates. |
| Uptime + scheduler heartbeat monitoring | S | External check of `/up` every 5 min (healthchecks.io free or self-hosted Uptime Kuma on homelab) + scheduler heartbeat so a dead cron/queue alerts instead of silently missing the 08:00 summary + hourly low-stock alerts; `pingWithoutOverlapping` on scheduled commands in `bootstrap/app.php`. |

## P3 — urgent, not important

| Feature | Effort | Notes |
| --- | --- | --- |
| Fix OG/social share images (og:image points at empty favicon) | S | `og:image` references `favicon.ico` which is 0 bytes → every shared link looks broken. Add branded 1200×630 PNG, `twitter:image`, sitemap `lastmod` + `/qr/{table}` URLs. |
| Promo banners / campaigns (admin-managed) | M | `promos` table (title/subtitle/badge/start-end/active/CTA) + Filament resource + dismissible banner on home/menu; `AiCopyService` can generate promo copy. |
| Split payments / multi-method checkout | S | Allow QRIS/e-wallet to take a partial amount instead of auto-settling the remaining (UI-only: `Cashier.php` `capturePayment` + `cashier.blade.php` pay-rest button); the receipt already loops all payments. Backend already repeatable. |
| Quick reorder (repeat last order) | S | One tap loads the most recent order's items (skip unavailable) into the cart via `Cashier::repeatOrder($orderId)`; cart is `menu_item_id => qty`. No schema change. |
| POS discounts / promos at checkout | M | `discount` column on `orders` (fixed/%), net used for remaining/payment cover; DECIDE: `salesTotal()` gross vs net. Drawer math safe (`expectedCash()` derives from payment rows). |
| Low-stock dashboard widget | S | `StatsOverviewWidget` count of `lowStock()` items + mini table with one-click stock-in linking to `StockItemResource`; register in `AdminPanelProvider::widgets()`. Zero migration. |
| Security headers middleware | S | In-repo middleware: HSTS, X-Content-Type-Options, frame-ancestors (admin unframable), Referrer-Policy, CSP report-only initially (Filament/Livewire inline styles need care); register in web group + SecurityHeadersTest. Caddyfile is out-of-repo, so this makes hardening auditable. |
| Laravel Pulse runtime dashboard | S-M | `laravel/pulse` (MIT, fits Laravel 13.23) `/pulse` route gated `auth:admin`; slow queries, exceptions, Redis, queue — zero observability today (`LOG_CHANNEL=stderr`). `phpunit.xml` already has `PULSE_ENABLED=false`. Include pulse table in backups. |
| Dependabot for composer/npm/actions | S | `.github/dependabot.yml` for `composer.json` + `package.json` + github-actions; CI test run becomes the merge gate. ~130-package graph on bleeding-edge versions (Filament 5.7, Tailwind 4, PG18). |

## P4 — neither urgent nor important

| Feature | Effort | Notes |
| --- | --- | --- |
| Testimonials section + `aggregateRating` JSON-LD | S | `testimonials` table (name/rating/text/visible) + Filament resource + home section; `aggregateRating` in the Cafe JSON-LD once 3+ reviews. |
| Dietary / allergen badges on menu cards | S | `badges` column (vegan, spicy, gluten-free, halal…) rendered as chips on menu cards, rides the existing data-driven category filters. |
| Supplier scorecard stats | S-M | Per-supplier aggregates on `SuppliersTable`: PO count, total spend, outstanding POs, avg lead time, on-time rate (status timestamps already exist). Richer price-drift stats depend on the PO `stock_item_id` link. |
| CSV/XLSX export for resource tables | S | Filament v5 native ExportAction (CSV+XLSX downloaders ship in `vendor/filament/actions`) + `make:filament-exporter` classes on StockItems/Orders/Expenses/PurchaseOrders tables; localized labels. No new dependency. |

## Completed since this backlog was captured (2026-08-02)

| Card | Landed in |
| --- | --- |
| POS refunds + voids (audit-safe corrections) | `55e1217` / `d84d21b` — negative payment rows, `refunded`/`cancelled` statuses, shift-safe totals (see `docs/pos.md`) |
| Recipe-based stock consumption on sales | `c943ead` — `consumeRecipeStock()` on POS orders, lenient fallback, `stock_movements.order_item_id` (see `docs/owner-tools.md`) |
| Monthly P&L report page | pnl-report branch — `PnlReport` page at `admin/pnl-report`, period picker, revenue − COGS − expenses by category, gross/net margin + inventory valuation (see `docs/owner-tools.md`) |
