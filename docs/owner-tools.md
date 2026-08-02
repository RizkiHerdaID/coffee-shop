# Owner Tools — Coffee Shop

Documentation for the owner-facing tooling of a single-owner Indonesian coffee shop (Laravel 13 + Filament 5, IDR pricing, production at coffee.rizkilab.my.id). Covers inventory, low-stock alerts, WhatsApp integrations, suppliers & purchase orders, expenses & cash register, recipes/COGS, AI copy generation, and the daily summary email. Derived from `docs/research-owner-ai.md` (pre-implementation research) and verified against the current codebase; all costs below are IDR/month unless noted.

## Overview

The original research was written when the app had **no orders table and no stock tracking**. Since then, the full owner-tooling stack landed on `main` (commits `962ba62` through `5239ca3`, merged by `0e27f7b` HEAD): inventory with stock movements, low-stock thresholds with WhatsApp alerts, suppliers, purchase orders, expenses, cash register sessions, recipe (menu_item ↔ stock_item) links with COGS, AI copy generation via DeepSeek, a daily/weekly summary email, and WhatsApp order confirmations via Fonnte.

The pieces the research predicted would make this cheap held up: the Redis queue + scheduler (notifications, summaries), MinIO/S3 storage, and Filament widgets. The one gap called out in the research — **production mail** — is still env-driven: `config/mail.php` defaults to `MAIL_MAILER=log` and `.env.example` wires `smtp` → Mailpit (dev only); the summary email works, but a production SMTP/Resend key must be set in production `.env`.

## Current implementation (verified)

### Inventory (stock items + stock movements)

| Piece | Location |
| --- | --- |
| Model | `app/Models/StockItem.php` — `name`, `unit`, `cost`, `quantity`, `min_threshold`, `note` (all numerics integer-cast) |
| Movements | `app/Models/StockMovement.php` — `stock_item_id`, `order_item_id`, `type` (`in`/`out`), `quantity`, `note` |
| Migrations | `database/migrations/2026_08_02_000006_create_stock_items_table.php`, `2026_08_02_000007_create_stock_movements_table.php`, `2026_08_02_000013_add_order_item_id_to_stock_movements_table.php` |
| Resource | `app/Filament/Resources/StockItems/` — form `Schemas/StockItemForm.php`, table `Tables/StockItemsTable.php`, `RelationManagers/StockMovementsRelationManager.php` + `WastagesRelationManager.php` |

- `StockItem::stockIn()/stockOut()` (`app/Models/StockItem.php:48-98`) run inside `DB::transaction` with `lockForUpdate`, write a `StockMovement` row, and adjust `quantity`. `stockOut` refuses to go below zero (returns `false`).
- Low-stock detection: `scopeLowStock()` + `isLowStock()` = `quantity <= min_threshold` (`app/Models/StockItem.php:38-46`). The table renders a low-stock badge (warning color) on the quantity column (`app/Filament/Resources/StockItems/Tables/StockItemsTable.php:29-34`).
- Row actions `stockIn` / `stockOut` open modals; all numeric inputs use the codebase's money mask idiom (`$money($input, ',', '.', 0)` + regex rule + `formatStateUsing`/`dehydrateStateUsing`, `Schemas/StockItemForm.php:22-43`).
- Movements are linked to `order_item_id` so POS sales can be traced to stock consumption (see Recipes).

### Low-stock WhatsApp alerts

| Piece | Location |
| --- | --- |
| Command | `app/Console/Commands/SendLowStockAlerts.php` — `stock:alert-low` |
| Schedule | `bootstrap/app.php:41` — hourly |
| State | `low_stock_notified_at` column (migration `2026_08_02_000010_add_low_stock_notified_at_to_stock_items_table.php`) |
| Recipient | `config('whatsapp.low_stock.phone')` → `WHATSAPP_LOW_STOCK_PHONE` env |

Flow: items back above threshold get `low_stock_notified_at` reset; then every low-stock item that was never notified gets a WhatsApp message (`__('stock.alert.*')`, `lang/{id,en}/stock.php`) and is marked notified. Failures are skipped (no retry loop); missing phone just logs a warning. One message per item per low-stock episode — no spam on a nightly restock.

### WhatsApp gateway (Fonnte) + order confirmations

| Piece | Location |
| --- | --- |
| Config | `config/whatsapp.php` — `enabled` (`WHATSAPP_ENABLED`), `fonnte.token` (`FONNTE_TOKEN`), `fonnte.url` (default `https://api.fonnte.com/send`), `low_stock.phone` |
| Service | `app/Services/FonnteWhatsApp.php` — `send(phone, message)` via form POST with token as `Authorization` header; returns `false` (logged) on any failure |
| Job | `app/Jobs/SendOrderConfirmation.php` — dispatched from `App\Models\Order::booted()` (created), only when `whatsapp.enabled` AND the order has `customer_phone`; message from `lang/{id,en}/whatsapp.php` (order number, items, total `Rp 25.000` style, shop phone) |

This matches the research verdict — **Fonnte free tier first** — with no paid tier configured. The WhatsApp pickup ordering on `/menu` (`app/Services/WaPickupMessage.php`, commit `ff0a6a6`) is documented in `docs/website.md`.

### Suppliers

| Piece | Location |
| --- | --- |
| Model | `app/Models/Supplier.php` — `name`, `contact_person`, `phone`, `email`, `address`, `note`; `hasMany(PurchaseOrder)` |
| Resource | `app/Filament/Resources/Suppliers/` — full CRUD (form `Schemas/SupplierForm.php`, table `Tables/SuppliersTable.php`) |

### Purchase orders

| Piece | Location |
| --- | --- |
| Models | `app/Models/PurchaseOrder.php` + `app/Models/PurchaseOrderItem.php` |
| Enums | `app/Enums/PurchaseOrderStatus.php` — `pending` / `received` / `cancelled` |
| Resource | `app/Filament/Resources/PurchaseOrders/` with `RelationManagers/PurchaseOrderItemsRelationManager.php` |
| Migrations | `2026_08_02_000009_create_purchase_orders_table.php`, `2026_08_02_000010_create_purchase_order_items_table.php` |

PO fields: `supplier_id`, `ordered_at`, `expected_at`, `status`, `total` (integer), `note`; line items are free-text `description` + `quantity` + `unit_price` (integer) — they do NOT auto-stock-in; receiving stock is a separate manual `stockIn` on the stock item (the research's MinIO supplier-document storage idea was not built).

### Expenses + cash register sessions

| Piece | Location |
| --- | --- |
| Models | `app/Models/Expense.php` (category enum `app/Enums/ExpenseCategory.php`), `app/Models/CashRegisterSession.php` |
| Resources | `app/Filament/Resources/Expenses/`, `app/Filament/Resources/CashRegisterSessions/` |
| Migrations | `2026_08_02_000008_create_expenses_table.php`, `2026_08_02_000009_create_cash_register_sessions_table.php` |

- Expense: `category`, `description`, `amount`, `spent_at`, `note`.
- Cash register session: `opened_at`, `closed_at`, `opening_float`, `expected_amount`, `counted_amount`, `discrepancy`, `status` (`open`/`closed`, enum `app/Enums/CashRegisterStatus.php`), `admin_id`.
- Math on the model (`app/Models/CashRegisterSession.php:41-59`): `revenue()` = `SUM(orders.total)` for orders with `created_at` within `[opened_at, closed_at ?? now]`; `expectedAmount()` = `opening_float + revenue()`. The form (`Schemas/CashRegisterSessionForm.php`) recomputes `expected_amount` and `discrepancy` live (mirror of the model formula) and stores them — this is the research's "open/close float + discrepancy report" feature.

### Recipes (menu_item ↔ stock_item) and COGS

| Piece | Location |
| --- | --- |
| Pivot | `database/migrations/2026_08_02_000011_create_menu_item_stock_item_table.php` (pivot column `quantity`) |
| Model | `app/Models/MenuItem.php:27-37` — `ingredients()` BelongsToMany with pivot `quantity`; `cogs()` = Σ `stock_item.cost × pivot.quantity` |
| UI | `app/Filament/Resources/MenuItems/RelationManagers/RecipesRelationManager.php` — attach stock items with quantity, columns for quantity/cost/line COGS (money-formatted), `MenuItemResource` embeds it |
| POS consumption | `app/Filament/Pages/Cashier.php:195+` `consumeRecipeStock()` — on sale, each ingredient is deducted via `stockOut()` with the `order_item_id` recorded; **lenient fallback**: if a deduction fails (insufficient stock) the sale proceeds and the notification lists the skipped ingredient names (commit `c943ead`) |

### Monthly P&L report

| Piece | Location |
| --- | --- |
| Page | `app/Filament/Pages/PnlReport.php` (`getReportData(?string $from, ?string $to): array` — protected, reflection-tested) + `resources/views/filament/pages/pnl-report.blade.php` |
| Route | `admin/pnl-report` (`filament.admin.pages.pnl-report`), registered in `AdminPanelProvider::pages()` |
| Lang | `lang/{id,en}/pnl.php` |

- Period picker (from/to, default = current month, inclusive `whereDate` bounds on `orders.created_at` / `expenses.spent_at`); "Dari" after "Sampai" shows a localized error instead of figures.
- Revenue = `SUM(orders.total)` for paid orders (status NOT IN pending/refunded/cancelled — mirrors `Shift::paidOrders()`); COGS = Σ `order_items.qty × MenuItem::cogs()` (lines with a deleted `menu_item_id` contribute 0); expenses grouped by `ExpenseCategory` (all 8 cases 0-filled).
- Statement shows revenue − COGS = gross margin − expenses = net margin, plus gross/net margin percentages and a period-independent inventory valuation (Σ `stock_items.cost × quantity`).

### Demand forecasting widget (day-of-week + seasonal)

| Piece | Location |
| --- | --- |
| Service | `app/Services/DemandForecastService.php` — `paidOrders(?int $months)` (paid/served orders in the last N months, current month inclusive), `weekdayAggregate()` (`['count' => mon..sun ints, 'revenue' => ...]`, zero-filled), `monthAggregate()` (`['Y-m' => ['count'=>int,'revenue'=>int]]`, oldest→newest, zero-filled); `DEFAULT_MONTHS = 3`, no migration |
| Widget | `app/Filament/Widgets/DemandForecastWidget.php` — bar `ChartWidget`, heading + 4 filters (`weekday_revenue` default, `weekday_count`, `month_revenue`, `month_count`); month labels via `Carbon::translatedFormat('M Y')` in the app locale |
| Wiring | Registered in `AdminPanelProvider::widgets()`; localized labels in `lang/{id,en}/dashboard.php` (`demand_forecast_heading`, `filter.weekday`, `filter.month`) |
| Tests | `tests/Feature/DemandForecastTest.php` (service aggregates/windows/status exclusion, widget `getData()` via reflection) |

- Status window mirrors the other revenue widgets: excludes `Pending`/`Refunded`/`Cancelled`. Aggregations are computed in PHP from a single light `paidOrders()` query (id/created_at/total only) — fine at single-shop volume, keeps the widget free of raw SQL and the trend lines stackable (weekday bars answer "which day sells most", month bars answer seasonality).

### AI copy generation (DeepSeek)

| Piece | Location |
| --- | --- |
| Service | `app/Services/AiCopyService.php` — `generateDescription(name, priceIdr?)` calls `POST {base_url}/chat/completions` with model `deepseek-chat` (default), `thinking` disabled, Indonesian system prompt (one short sentence, 10-15 words, no quotes/prelude) |
| Config | `config/services.php:38-41` — `services.deepseek.api_key` (`DEEPSEEK_API_KEY`), `base_url` (default `https://api.deepseek.com`), `model` (`DEEPSEEK_MODEL`, default `deepseek-chat`) |
| Command | `app/Console/Commands/GenerateMenuCopy.php` — `menu:generate-copy`, fills empty `menu_items.note` in `sort_order`; skips items on missing key (`app/Exceptions/MissingAiKeyException.php`), reports per-item failures, exits non-zero if any failed |
| Tests | `tests/Feature/AiCopyTest.php` (mocked HTTP) |

Note the divergence from research: the research priced OpenAI (`gpt-5-nano`/`gpt-4o-mini`) and Gemini; the implementation went with **DeepSeek** (`deepseek-chat`), which is cheaper at shop volume and strong in Indonesian — see Decisions.

### Promo banners (public site)

| Piece | Location |
| --- | --- |
| Table | `database/migrations/2026_08_02_180000_create_promos_table.php` — `title`, `subtitle`/`badge`/`cta_text`/`cta_url` nullable, `starts_at` required, `ends_at` nullable, `active` default true, `sort_order` |
| Model | `app/Models/Promo.php` — `#[Fillable]`, datetime/boolean/integer casts, `scopeVisible()` (active + `starts_at <= now` + `ends_at` null or `>= now`) |
| Resource | `app/Filament/Resources/Promos/` — list (with `ToggleColumn` active toggle), create/edit; subtitle has a DeepSeek "generate with AI" suffix action (`AiCopyService::generatePromoSubtitle`) visible only when `DEEPSEEK_API_KEY` is set |
| Banner | `resources/views/partials/promo-banner.blade.php` — amber bar (badge + title + subtitle + optional CTA) yielded into the fixed header via `@section('promo-banner')` on home + menu only; dismiss is client-side `localStorage['promo-dismissed-<id>']`, so the banner never reappears until another promo is active |
| Wiring | `app/Http/Controllers/PageController.php` `home()`/`menu()` — `Promo::query()->visible()->orderBy('sort_order')->first()` passed as `$promo` (only the first promo renders) |
| Seed | `database/seeders/PromoSeeder.php` — idempotent `updateOrCreate` "Promo Kopi Pagi", registered in `DatabaseSeeder` |
| Lang | `lang/{id,en}/promos.php` (resource strings) + `site.banner.dismiss_aria` (only banner key added to `site.php`) |
| Tests | `tests/Feature/PromoTest.php` (scope window logic, home/menu render/hide, sort-order-first, admin resource pages) |

### Daily/weekly summary email

| Piece | Location |
| --- | --- |
| Command | `app/Console/Commands/SendSummaryEmail.php` — `summary:send --period=daily|weekly [--date=YYYY-MM-DD] [--to=email]` |
| Schedule | `bootstrap/app.php:39-40` — daily at `summary.daily.time` (08:00), weekly Monday at `summary.weekly.time` (08:00) |
| Config | `config/summary.php` — `recipient` (default `MAIL_FROM_ADDRESS`), daily/weekly times |
| Mailable | `app/Mail/SalesSummary.php` (queued; localized subject/view) |

Aggregation (`SendSummaryEmail.php:63-106`): daily = previous day, weekly = trailing 7 days (Asia/Jakarta), computes revenue, order count, average order value, and top-5 items by qty; queues `Mail::to($recipient)`.

## Architecture / flow

```
[StockItem.stockIn/stockOut]  →  stock_movements (type in/out, order_item_id)
        │                                │
        └── low-stock scope (qty <= min_threshold) ──▶ stock:alert-low (hourly)
                                                          └─▶ FonnteWhatsApp → WHATSAPP_LOW_STOCK_PHONE
Order created (Cashier page)
   ├── recipes: consumeRecipeStock() → stockOut() per ingredient (lenient)
   └── Order::booted() → SendOrderConfirmation job (if whatsapp.enabled + customer_phone)
                                          └─▶ FonnteWhatsApp → customer phone
Scheduler (bootstrap/app.php)
   ├── summary:send --period=daily    (08:00)  ─▶ Mail::queue(SalesSummary) → summary.recipient
   ├── summary:send --period=weekly   (Mon 08:00)
   └── stock:alert-low                (hourly)
Cash register: expected_amount = opening_float + SUM(orders.total in [opened_at, closed_at ?? now])
```

## Roadmap (what remains, from the research)

Implemented since the research was written: orders/order_items + payments + shifts (POS — see `docs/pos.md`), dashboard widgets (RevenueChart, TopItemsChart, BestSellersChart, PeakHoursChart, PaymentSplitChart), demand forecasting widget (see below), daily summary email, stock items + movements + thresholds + low-stock alerts, WhatsApp order confirmations (Fonnte), recipes/COGS, expense tracking + cash register, suppliers + purchase orders, wastage logging (`app/Filament/Resources/Wastages/`).

Still **planned** (not implemented — mark as future work):

| Feature | Impact | Effort | Notes |
| --- | --- | --- | --- |
| M4: dynamic QRIS auto-confirmation (Tripay/Xendit/Midtrans) | High | M | Currently QRIS is captured manually as a static payment method; aggregator API auto-confirmation is NOT wired |
| Loyalty points / stamp cards + QR membership | Med | M | `bacon/bacon-qr-code` IS installed (used for QR table codes at `/qr/{table}` via `app/Filament/Pages/QrCodes.php`) but not wired to loyalty; QR on receipt + `wa.me` link still open |
| WhatsApp chatbot (menu recs, hours, order status) | Med | M | Needs webhook tier: Wablas Large Rp119K or Fonnte Master Rp175K; currently text-only outbound |
| Google review auto-response drafts | Med | M | Google Places API ~Rp100-200K/mo usage + AI call costs |
| Receipt OCR → auto-expense entry | Low-Med | M | OpenAI vision or Google Cloud Vision $1.50/1k images; local Tesseract poor on Indonesian receipts |
| External BI | Low | S-M | Looker Studio free via Laravel JSON feed; Filament widgets sufficient for one shop |
| PO auto-stock-in on "received" | Low | S | POs are recorded but receiving stock is a manual `stockIn` |

## Decisions & rationale (kept from research)

### WhatsApp gateway comparison

| Gateway | Type | Cost (IDR/mo) | Notes |
| --- | --- | --- | --- |
| Wablas | Unofficial (own number via WhatsApp Web) | Nano Rp22K (1k msgs); Lite Rp36K (5k); Small Rp69K (10k); Medium Rp86K (unlimited text); Large Rp119K (unlimited + media + webhooks); Enterprise Rp139K | Mature, n8n support, webhooks; ban risk on cold broadcasts; free trial watermarked |
| Fonnte | Unofficial (own number) | Free tier (1k text msgs); Lite Rp25K (1k); Regular Rp66K (10k); Regular Pro Rp110K (25k); Master Rp175K (unlimited) | Easiest API, free tier ideal for start; ban risk; no SLA |
| WABA (Meta official) | Official Business API | Per-conversation, Indonesia ~Rp350-900 | Needs WhatsApp Business Profile + template approval; safest; per-conversation type pricing |
| Qontak | Indonesian B2B SaaS | Rp1.5-5jt (custom quote) | Enterprise-grade, overkill for a single shop |
| Gupshup | BSP | ~Rp300-1.200 per message | Official channels via BSP; adds up at promo-blast volume |

**Verdict (followed)**: start with **Fonnte free tier** (zero cost, fastest Laravel HTTP integration + queued jobs) — implemented in `config/whatsapp.php` + `FonnteWhatsApp`; move to **Wablas Large** when webhooks/media/auto-reply matter; consider **Meta WABA** only when guaranteed official messaging matters at scale.

### AI models & pricing (API, per 1M tokens — research-era data, verify current)

| Model | Input | Output | Use case |
| --- | --- | --- | --- |
| OpenAI `gpt-5-nano` | $0.05 | $0.40 | Cheapest; promo copy, descriptions |
| OpenAI `gpt-4o-mini` | $0.15 | $0.60 | Text + vision (receipt OCR), chatbot |
| OpenAI `gpt-5-mini` | $0.25 | $2.00 | Better reasoning when needed |
| Gemini (Flash) | ~$0.30 | ~$2.50 | Free tier; good Indonesian-language support |

Batch API is 50% off for non-urgent jobs. **Implementation choice**: the project uses **DeepSeek `deepseek-chat`** (Indonesian-friendly, cheaper than OpenAI at shop volume, key via `DEEPSEEK_API_KEY`) — the research's OpenAI/Gemini table remains valid as an alternative. Skip local LLMs / GPU rental (Vast.ai ~$0.15-0.60/hr): hosted APIs are cheaper and maintenance-free at this scale.

### Other decisions

- **In-house Filament resources over SaaS** (Moka, BukuWarung, Paper.id Rp0-300K/mo): keeps all data in the shop's own DB; expense + cash register + supplier management are plain resources, which is the right fit for one owner. No external POS/accounting API was needed.
- **Production mail**: Mailpit is dev-only; the summary email uses the default mailer — set a real SMTP/Resend key in production (Resend free tier is 3k emails/mo).
- **Numeric inputs**: quantities and money use the Indonesian-thousand-separator mask idiom (see `StockItemForm`) — display `25.000`, store raw integer.

## References

- Wablas pricing: https://wablas.com/pricing
- Fonnte: https://fonnte.com/
- Meta WhatsApp Business: https://business.whatsapp.com
- Qontak: https://www.qontak.com/
- Gupshup: https://www.gupshup.io/
- OpenAI API pricing: https://platform.openai.com/docs/pricing
- Gemini API pricing: https://ai.google.dev/gemini-api/docs/pricing
- DeepSeek API: https://platform.deepseek.com
- Google Looker Studio: https://lookerstudio.google.com
- Google Cloud Vision: https://cloud.google.com/vision
- Google Places API: https://developers.google.com/maps/documentation/places
- Resend (mail): https://resend.com
- Filament (StatsWidget, Charts): https://filamentphp.com
- bacon/bacon-qr-code: https://github.com/bacon/bacon-qr-code
- Rubix ML: https://github.com/RubixML/ML
- Moka: https://moka.co · BukuWarung: https://www.bukukas.co.id · Paper.id: https://www.paper.id

## Research notes

- Gateway/AI prices were verified during the research session and are indicative; Fonnte/Wablas are unofficial gateways with ban risk — verify current plans before purchase.
- Gemini/OCR/Looker costs are approximations from provider docs; Meta WABA prices vary by conversation type and region.
