# POS — Coffee Shop

How the in-house point-of-sale works: cashier flow, payment capture, shift
open/close with Z-report, receipt/kitchen printing, and the sales dashboard
widgets — all inside the Filament v5 admin panel at `/admin`.

Supersedes `docs/research-pos.md` (2026-08-02 research); this document reflects
the **current** state of the codebase (checkout `d84d21b`). The research's
third-party-platform analysis and cost tables are preserved under
[Decisions & rationale](#decisions--rationale).

## Overview

The shop chose option (B) from the research — build a lightweight POS inside the
existing Filament panel instead of integrating a third-party POS (Pawoon,
Digipos, Moka, Majoo, Qasir, BukuKas, Square, Loyverse, Lightspeed — all
rejected for API-gate/absence/region reasons, see [Decisions](#decisions--rationale)).

Milestones M1–M3 are implemented and merged to `main`:

| Milestone | Status | Deliverables (as built) |
| --- | --- | --- |
| **M1** | Done (`ffffb34`, `c943ead`) | `orders`/`order_items`/`payments`/`shifts` migrations; `Order` model; cashier page (menu grid → cart → order); `menu_items` gained `photo`, `category`, `available`; recipe stock deduction |
| **M2** | Done (`37e06ea`) | Payment capture (cash / static QRIS / e-wallet), status `pending → paid → served`, queued `PrintReceipt` + `PrintKitchenTicket`, browser-print receipt fallback |
| **M3** | Done (`651d832`, `e0d3afe`) | Shift open/close + Z-report (`/admin/shift-report/{record}` + standalone printable `/pos/z-report/{shift}`), dashboard widgets (Revenue, TopItems, BestSellers, PeakHours, PaymentSplit) |
| **M3.5** | Done (`55e1217` / `d84d21b`) | Refunds + voids with shift-safe totals |
| **M4** | **Planned — NOT implemented** | Dynamic QRIS via Tripay/Xendit/Midtrans (see [Roadmap](#roadmap)) |

## Current implementation

### Component map

| Component | Location |
| --- | --- |
| Cashier page (cart → order → pay → served) | `app/Filament/Pages/Cashier.php` (slug `cashier`, nav sort 1, icon `OutlinedShoppingCart`) |
| Shift open/close page | `app/Filament/Pages/ManageShift.php` (slug `shift`, nav sort 2, icon `OutlinedClock`) |
| Z-report page (panel) | `app/Filament/Pages/ShiftReport.php` (route path `/shift-report/{record}`, **not** in nav — reached by redirect after closing a shift) |
| Printable Z-report (standalone) | `app/Http/Controllers/PosZReportController.php` → `resources/views/filament/pos/z-report.blade.php` at `GET /pos/z-report/{shift}` (auth:admin) |
| Printable receipt (standalone) | `app/Http/Controllers/PosReceiptController.php` → `resources/views/filament/pos/receipt.blade.php` at `GET /pos/receipt/{order}` (auth:admin) |
| Orders resource (admin CRUD + row actions) | `app/Filament/Resources/Orders/` — `OrderResource.php`, `Schemas/OrderForm.php`, `Tables/OrdersTable.php` (row actions: markPaid, markServed, refund, void) |
| Dashboard widgets | `app/Filament/Widgets/` — `TodayStats`, `RevenueChart`, `TopItemsChart`, `BestSellersChart`, `PeakHoursChart`, `PaymentSplitChart` (registered in `app/Providers/Filament/AdminPanelProvider.php:48-67`) |
| Print jobs (queued) | `app/Jobs/PrintReceipt.php`, `app/Jobs/PrintKitchenTicket.php`, shared ESC/POS rendering in `app/Jobs/Concerns/PrintsThermal.php` |
| WhatsApp order confirmation (queued) | `app/Jobs/SendOrderConfirmation.php` (dispatched from `Order::booted()` `created` hook when `config('whatsapp.enabled')` && `customer_phone` set) |
| Models | `app/Models/Order.php`, `OrderItem.php`, `Payment.php`, `Shift.php` |
| Enums (string-backed, `HasLabel`) | `app/Enums/OrderStatus.php` (`pending/paid/served/refunded/cancelled`), `app/Enums/PaymentMethod.php` (`cash/qris/ewallet`) |
| POS config | `config/pos.php` (printer, static QRIS image, stock deduction flag) |
| Localization | `lang/{id,en}/pos.php` (all UI strings via `__()`; Indonesian primary) |
| Routes | `routes/web.php:24-30` (`pos.receipt`, `pos.zreport`) |

### Schema

Built by migrations `2026_08_02_000002`–`000006` (plus `000012` and `000013`):

| Table | Columns |
| --- | --- |
| `shifts` | `opened_at`, `closed_at` (nullable), `opening_cash` (unsigned int, default 0), `closing_cash` (nullable), `expected_total` (nullable, set at close), `admin_id` → `admins` |
| `orders` | `order_number` (unique, format `ORD-YYYYMMDD-NNNN`), `status` (default `pending`), `total` (unsigned int), `customer_phone` (nullable, WhatsApp confirmation), `shift_id` (nullable, `nullOnDelete`), `created_by` → `admins` |
| `order_items` | `order_id` → `orders` (cascade), `menu_item_id` (nullable, `nullOnDelete`), **snapshot** `name` + `price` (history survives menu edits/deletes), `qty`, `subtotal` |
| `payments` | `order_id` → `orders` (cascade), `method` (default `cash`), `amount` (**signed** int — refunds are negative rows, migration `000013`), `reference` (nullable: QRIS/e-wallet ref or refund reason), `paid_at` (nullable), `admin_id` → `admins` |

`menu_items` additions for the POS: `photo`, `category`, `available` (bool,
default true) — see migration `2026_08_02_000009`. Note the research proposed
`is_active` + `image_url`; the code uses **`available`** and **`photo`**
(`app/Models/MenuItem.php:13`).

### Cashier flow

`app/Filament/Pages/Cashier.php` is a single Livewire page (`resources/views/filament/pages/cashier.blade.php`):

1. **Menu grid** — `getMenuItemsProperty()` lists `available` items, filtered
   by the category chips (`getCategoriesProperty()`), ordered by `sort_order`.
2. **Cart** — `cart` property (`menu_item_id => qty`); add/increment/decrement/
   remove/clear; `getCartLinesProperty()` re-reads live items (subtotals =
   qty × current price); `getCartTotalProperty()` sums.
3. **Create order** (`createOrder()`, `Cashier.php:120`) — runs in a DB
   transaction: creates the `pending` order with `shift_id` = `Shift::active()?->id`
   (**null when no shift is open** — the cashier view shows a notice banner),
   then `order_items` with name/price snapshots. If `config('pos.deduct_stock')`
   is true (default), `consumeRecipeStock()` (`Cashier.php:195`) deducts recipe
   ingredients as `out` stock movements (linked via `order_item_id`), **lenient
   mode**: insufficient stock skips the ingredient and shows one warning
   notification (`pos.stock.*`) instead of blocking the sale. Order numbering:
   `ORD-YYYYMMDD-` + zero-padded daily sequence (`generateOrderNumber()`,
   `Cashier.php:408`).
4. **Pay** (`capturePayment()`, `Cashier.php:242`) — cash: tendered amount
   (Indonesian separators allowed, e.g. `100.000`), change computed; QRIS /
   e-wallet: settle the **exact remaining** amount (static QRIS image from
   `config('pos.qris.image')`, placeholder box when unconfigured; e-wallet
   reference optional). **Partial payments are allowed** — the order stays
   `pending` until `paid_total` ≥ `total`.
5. **Auto-paid transition** — `Order::markPaidIfCovered()` (`Order.php:70`)
   flips `pending → paid` when covered and dispatches `PrintReceipt` +
   `PrintKitchenTicket` **exactly once, on the transition**.
6. **Serve** (`markServed()`, `Cashier.php:319`) — `paid → served`.

Order confirmation via WhatsApp: `Order::created` dispatches
`SendOrderConfirmation` (`Order.php:20-24`) when `config('whatsapp.enabled')`
and the order has a `customer_phone`; message keys in `lang/{id,en}/whatsapp.php`
(`confirmation`, `confirmation_with_items`).

### Printing

- `PrintReceipt` / `PrintKitchenTicket` are `ShouldQueue` jobs (3 tries) that
  render 58mm thermal lines via the shared `PrintsThermal` concern
  (mike42/escpos-php `^5.0`, `composer.json:15`).
- Connection config in `config/pos.php` `printer` block: `enabled`
  (`POS_PRINTER_ENABLED`), `connection` (`network | file | windows`),
  `address`, `port` (9100), `chars_per_line` (32). When disabled/unconfigured
  the job logs `pos.printer_disabled` and returns without failing.
- **Browser-print fallback**: the receipt view `GET /pos/receipt/{order}`
  (auth:admin) renders a self-contained Blade document for `window.print()`.
  The kitchen ticket has **no** standalone view — it only prints via the job.

### Shifts & Z-report

- Open: `ManageShift::openShift()` (`ManageShift.php:119`) requires opening
  cash (`Rp` formatted, `isValidAmount()`/`parseAmount()`), creates a `shift`
  with `opened_at`; **only one shift can be open** (`Shift::active()`
  = latest shift with `closed_at IS NULL`).
- Close: `closeShift()` (`ManageShift.php:157`) requires counted closing cash,
  sets `closed_at`, `closing_cash`, `expected_total = salesTotal()`, then
  redirects to the Z-report page. `ManageShift` also lists the 10 most recent
  closed shifts with per-method totals and discrepancy.
- Shift math on `app/Models/Shift.php`:
  - `paidOrders()` (`Shift.php:54`) — orders whose status is **not**
    pending/refunded/cancelled (paid + served count toward reports).
  - `salesTotal()` / `paidOrdersCount()` — sum/count of paid orders.
  - `paymentsByMethod()` (`Shift.php:89`) — `['cash' => int, 'qris' => int,
    'ewallet' => int]` from payment rows of paid orders.
  - `expectedCash()` (`Shift.php:133`) — `opening_cash + cashPaid() +
    cashRefunds()` (cash refunds are negative rows).
  - `discrepancy()` (`Shift.php:141`) — `closing_cash − expectedCash()`,
    **0 while the shift is still open**.
- Z-report content (`z-report.blade.php`): shift number/period/closed-by,
  order count, total sales, payment breakdown by method, cash check
  (opening cash / cash payments / cash refunds / expected / counted /
  discrepancy, rendered `COCOK` when zero, `+`/`−` otherwise).

### Refunds & voids

Implemented in `Order.php` with row actions in `OrdersTable.php`:

- **Refund** (`Order::refund()`, `Order.php:103`) — allowed when status is
  paid/served **and** the order's shift is still open or unattached
  (`canBeRefunded()`, `Order.php:88` — closed shifts keep the Z-report
  stable). Records a **negative** `payments` row (method + reason as
  reference); when net paid drops to zero the status flips to `refunded`.
  Amount is validated (must be > 0 and ≤ paid total).
- **Void** (`Order::void()`, `Order.php:145`) — allowed only for `pending`
  orders with an open/unattached shift; flips status to `cancelled`.
- Shift-safe totals: `Shift::paidOrders()` excludes `refunded`/`cancelled`
  orders, and partial refunds net within `paymentsByMethod()`/`cashPaid()`.

### Dashboard widgets

Registered in `AdminPanelProvider.php:54-67`; all widgets exclude
pending/refunded/cancelled orders:

| Widget | Type | Content |
| --- | --- | --- |
| `TodayStats` | stats overview | Today revenue, order count, average order value |
| `RevenueChart` | line | Revenue, last 14 days |
| `TopItemsChart` | bar | Top 5 items by revenue (order item name snapshot) |
| `BestSellersChart` | bar | Top 10 items by revenue (localized heading) |
| `PeakHoursChart` | bar | Day-of-week × hour grid (7×24), filter `revenue`/`count`, last 30 days |
| `PaymentSplitChart` | doughnut | Today's payments by method |

### Orders resource

`app/Filament/Resources/Orders/` — list/create/edit pages, `OrderForm`
schema, `OrdersTable` with status badge and the markPaid/markServed/refund/void
row actions (all labels from `lang/{id,en}/pos.php`).

## Architecture / flow

```
Browser (cashier tablet)                 Filament panel (guard: admin)
  |                                          /admin/cashier  (Cashier page)
  |   menu grid -> cart -> createOrder (DB transaction)
  |       |  orders / order_items (snapshot) / stock_movements (recipe deduction)
  |       v
  |   capturePayment: cash (tendered + change) | static QRIS | e-wallet (exact remaining)
  |       |  payments rows (+ partial payments supported)
  |       v
  |   markPaidIfCovered(): pending -> paid  --once--> queue
  |                                                    | PrintReceipt / PrintKitchenTicket
  |                                                    |   (mike42/escpos-php, config/pos.php printer)
  |                                                    |   -> 58mm thermal OR log+skip
  |                                                    `-> browser-print fallback: /pos/receipt/{order}
  |   markServed(): paid -> served
  |
  |   shift lifecycle: /admin/shift (open: opening_cash) ... (close: closing_cash,
  |   expected_total, redirect) -> /admin/shift-report/{record} (panel) +
  |   printable /pos/z-report/{shift} (auth:admin)
  |
  |   dashboard widgets: TodayStats, RevenueChart, TopItemsChart,
  |   BestSellersChart, PeakHoursChart, PaymentSplitChart (all exclude
  |   pending/refunded/cancelled orders)
```

Orders attach `shift_id` when a shift is active (`Shift::active()`), otherwise
null — the cashier page shows a notice prompting the cashier to open a shift.
Queued jobs run on the Laravel queue (compose.yaml provisions a `queue` worker
service; `.env` sets `QUEUE_CONNECTION=sync` in dev, so jobs execute inline
locally).

## Roadmap

- **M4 (planned, not implemented): dynamic QRIS** via Tripay/Xendit/Midtrans —
  payment initiation from the POS, webhook receiver route (CSRF-exempt,
  signature-verified), idempotent queue job auto-marking orders `paid`,
  reconciliation job for stuck orders. Today QRIS is a **static image**
  (`config('pos.qris.image')`) that the cashier shows; confirmation is manual.
- Kitchen ticket browser-print fallback (the standalone view exists for
  receipts only).
- PWA/offline cache for the cashier page (research's offline-resilience gap).
- `mike42/escpos-php` receipt printing is implemented but gated behind
  `POS_PRINTER_ENABLED`; no printer hardware has been validated on the shop
  floor (see `.env.example` / `config/pos.php`).

## Decisions & rationale

### (A) Third-party POS platforms vs (B) in-house

| Criterion | (A) Pawoon/Digipos integration | (B) In-house Filament POS |
| --- | --- | --- |
| Monthly cost | ~Rp100k–400k/mo + vendor approval latency | Rp0 (existing stack: Postgres, Redis queue, admin panel) |
| Time to cashier | Weeks (vendor approval + sync plumbing) | Days–weeks, fully under control |
| Menu single source of truth | No — dual entry (app `menu_items` + POS catalog) needs sync | Yes — `menu_items` remains the only catalog |
| QRIS | Native, incl. merchant settlement | Static QRIS display today; dynamic QRIS + auto-confirm via Tripay/Xendit/Midtrans webhooks later (M4, planned) |
| Offline resilience | Strong (POS device queues offline) | Weak — admin panel needs internet (mitigate: PWA/cached page) |
| Receipt/kitchen printing | Vendor hardware/ecosystem | `mike42/escpos-php` thermal printing (implemented), browser print, WhatsApp receipt |
| Sales reports | In vendor app (split from website analytics) | Filament dashboard widgets/charts, one DB |
| Customization / exit cost | Vendor lock-in, data export hassle | Full ownership, no lock-in |

### Indonesian platforms surveyed

| Platform | Open API / webhooks | Pricing (indicative, IDR) | Verdict |
| --- | --- | --- | --- |
| **Pawoon** | Yes — Open/Super API, OAuth via dashboard apply (`dashboard.pawoon.com/integration/apply/oauth`) | ~Rp99k–400k/mo | Best of the Indonesian set, but dual-catalog + subscription |
| **Digipos** | Documented Open API (openapi.digipos.co.id) | ~Rp99k–200k/mo | Similar shape to Pawoon; smaller ecosystem |
| **Moka** | API exists but partner/sales-gated | ~Rp99k–299k/mo | Blocked by approval process (weeks–months) |
| **Majoo** | Partner-gated | ~Rp99k–200k/mo | Same approval-gate problem as Moka |
| **Qasir** | No public API (app-only; "ekosistem" integrations are business agreements) | Free app + PRO ~Rp70.792/mo | No API surface to integrate against |
| **BukuKas** | No public API | Mostly free (bookkeeping focus) | None |

### International platforms surveyed

| Platform | Open API / webhooks | Pricing | Verdict |
| --- | --- | --- | --- |
| **Square** | Excellent — POS API, Catalog API, Payments API, webhooks | US$ tiers (0–69+/mo) | **Dealbreaker:** does not operate in Indonesia; no IDR merchant accounts. Rejected. |
| **Loyverse** | Yes — open REST API + webhooks | Free | No native payment processing — no QRIS/e-wallet support. Rejected. |
| **Lightspeed** | OAuth REST API | ~US$69–119+/mo | Overkill and not Indonesia-localized. Rejected. |

### Plugin landscape

- No mature Filament v5 POS plugin exists (`filamentphp.com/plugins`).
- `tomatophp/filament-pos` targets older Filament versions, requires Spatie
  MediaLibrary + Settings Hub, and is a small, low-star package (68 stars).
- Conclusion: build **custom Filament pages** mirroring the existing
  `app/Filament/Resources/MenuItems/` layout conventions (v5 `Schemas/` +
  `Tables/` subdirectories) — done, plus standalone printable Blade views.

### Cost estimates (IDR)

| Item | (A) per month | (B) one-off |
| --- | --- | --- |
| POS subscription | ~Rp100k–400k/mo (Pawoon/Digipos) | Rp0 |
| Payment gateway (dynamic QRIS, M4) | vendor-dependent (per-transaction fee) | gateway per-transaction fee only (Tripay/Xendit/Midtrans) |
| Development | API onboarding + sync plumbing (hours × weeks) | M1–M3 in-repo work; M4 gateway integration |
| Hardware (both) | cashier tablet + 58mm thermal printer (~Rp500k–1.5M once) | same |

Ongoing software cost of (B) = Rp0/month; (A) adds a standing subscription for
a catalog the shop already manages.

### Deviations from the research (code wins)

- Status enum extended from `pending/paid/served` to
  `pending/paid/served/refunded/cancelled` (refunds + voids, `d84d21b`).
- `menu_items` uses `available` (not `is_active`) and `photo` (not
  `image_url`).
- `payments.amount` is **signed** to hold negative refund rows (migration
  `000013`).
- `orders.customer_phone` added for WhatsApp order confirmations.
- Printing is gated by `config('pos.printer')`; browser-print receipt +
  Z-report views are the fallback path.
- Dev `.env` runs `QUEUE_CONNECTION=sync`; the research assumed Redis-driven
  printing in dev.

## References

- Pawoon Open API: https://www.pawoon.com/developer
- Digipos: https://digipos.co.id/ (API docs: openapi.digipos.co.id)
- Moka: https://www.moka.co/
- Majoo: https://majoo.id/
- Qasir (free + PRO ~Rp70.792/mo, QRIS): https://www.qasir.id/
- BukuKas: https://www.bukukas.co.id/
- Square POS API (no Indonesia/IDR support): https://developer.squareup.com/docs/pos-api/what-it-does
- Loyverse API: https://developer.loyverse.com/docs
- Filament plugins (no mature v5 POS): https://filamentphp.com/plugins
- Older Filament POS plugin: https://github.com/tomatophp/filament-pos
- Thermal receipt printing: https://github.com/mike42/escpos-php
- QRIS/payment gateways: https://tripay.co.id/ · https://www.xendit.co/ · https://docs.midtrans.com/
- Filament v5 docs: https://filamentphp.com/docs/5.x
