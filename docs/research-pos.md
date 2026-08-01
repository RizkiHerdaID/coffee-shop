# POS Strategy Research — Coffee Shop (Laravel 13 + Filament 5)

**Date:** 2026-08-02 · **Scope:** read-only research, no code changes
**App context:** Laravel 13.23 + Filament 5.7.5, single Indonesian coffee shop, IDR pricing (unsigned int), admin panel at `/admin`, Redis queue worker, PostgreSQL 18, public landing pages (`/`, `/menu`, `/contact`) fed by `menu_items` (`name`, `price`, `note`, `sort_order`).

## Summary

Two viable directions were researched: (A) integrating a third-party Indonesian/international POS service, and (B) building a lightweight POS inside the existing Filament admin panel. **Recommendation: (B)** — build the in-house POS, optionally adding a payment aggregator (Tripay/Xendit/Midtrans) for QRIS auto-confirmation. Third-party Indonesian POS platforms either have no public API (Qasir, BukuKas), sales-gated API access (Moka, Majoo), or — where a real API exists (Pawoon, Digipos) — would force dual catalog management, monthly subscription cost, and vendor approval latency with no compensating benefit for a single-owner, budget-constrained shop. Square is not available in Indonesia (no IDR merchant accounts). The app already owns the menu catalog, the admin panel, Postgres, and a Redis queue — everything an in-house POS needs.

## (A) Third-party POS platforms

### Indonesian platforms

| Platform | Open API / webhooks | Pricing (indicative, IDR) | QRIS | Offline mode | Laravel integration feasibility |
| --- | --- | --- | --- | --- | --- |
| **Pawoon** | Yes — Open/Super API, OAuth via dashboard apply (`dashboard.pawoon.com/integration/apply/oauth`) | ~Rp99k–400k/mo | Yes | Yes | Best of the Indonesian set. OAuth token management + webhook receiver + queue jobs for menu/sales sync. |
| **Digipos** | Documented Open API (openapi.digipos.co.id; unreachable from research env — verify) | ~Rp99k–200k/mo | Yes | Yes | Similar shape to Pawoon; smaller ecosystem and documentation. |
| **Moka** | API exists but partner/sales-gated — no public self-serve developer portal (developer.moka.co unreachable) | ~Rp99k–299k/mo | Yes | Yes | Blocked by an approval process (weeks–months). Worst fit for this project. |
| **Majoo** | Partner-gated | ~Rp99k–200k/mo | Yes | Yes | Same approval-gate problem as Moka. |
| **Qasir** | No public API (app-only; "ekosistem" integrations are business agreements) | Free app + Qasir PRO ~Rp70.792/mo | Yes | Yes | None — no API surface to integrate against. |
| **BukuKas** | No public API | Mostly free (bookkeeping focus) | Yes | Yes | None. |

### International platforms

| Platform | Open API / webhooks | Pricing | QRIS | Offline mode | Verdict |
| --- | --- | --- | --- | --- | --- |
| **Square** | Excellent — POS API, Catalog API, Payments API, webhooks (developer.squareup.com/docs/pos-api/what-it-does) | US$ tiers (0–69+/mo) | No (no IDR) | Yes | **Dealbreaker:** Square does not operate in Indonesia; no IDR merchant accounts. Rejected. |
| **Loyverse** | Yes — open REST API + webhooks (developer.loyverse.com/docs) | Free | No native payment processing (records sales only) | Yes | Technically easy integration, but payments are not processed by Loyverse — no QRIS/e-wallet support. Rejected for this use case. |
| **Lightspeed** | OAuth REST API | ~US$69–119+/mo | No | Yes | Overkill and not Indonesia-localized. Rejected. |

### Integration pattern if (A) were chosen (Pawoon example)

1. Merchant applies via dashboard OAuth flow; app receives `access_token`/`refresh_token`; store tokens encrypted (Laravel `Crypt`) in a `pos_connections` table.
2. Menu sync: webhook receiver (`/webhooks/pawoon/menu`) + scheduled polling job on `queue:work` (Redis) upserting `menu_items`.
3. Sales sync: webhook per transaction → idempotent job (keyed by remote transaction ID) writing to a mirror table; polling fallback on webhook gaps.
4. Token refresh + failure retry via `RetryUntil` queue jobs; dead-letter for reconciliation.

## (B) In-house POS inside Filament

Scope: cashier flow, order creation, order status (`pending` → `paid` → `served`), payment methods (`cash`, `qris`, `ewallet`), daily close (shift open/close + Z-report), kitchen tickets, and simple sales reports — all inside the existing Filament v5 admin panel at `/admin`.

### Plugin landscape

- Filament plugins directory (949 plugins) has **no mature v5 POS plugin** (filamentphp.com/plugins).
- `tomatophp/filament-pos` exists (github.com/tomatophp/filament-pos) but is built for older Filament versions, requires Spatie MediaLibrary + Settings Hub, and is a small, low-star package (68 stars) — not worth the upgrade risk.
- Conclusion: build **custom Filament pages** (cashier Livewire page + `Order` resource), mirroring the existing `app/Filament/Resources/MenuItems/` layout conventions (v5 `Schemas/` + `Tables/` subdirectories).
- Useful libraries: `mike42/escpos-php` for ESC/POS 58mm thermal receipt/kitchen printing; Tripay/Xendit/Midtrans PHP SDKs for dynamic QRIS.

### DB schema implications

- `menu_items` additions: `is_active` (bool, default true), `category` (string, nullable), optionally `image_url` — backward compatible, public pages filter `is_active`.
- New tables:
  - `orders` — id, order_number, status enum (`pending/paid/served`), total (unsigned int), shift_id FK, created_by (admin_id), timestamps.
  - `order_items` — order_id FK, `menu_item_id` FK **nullable**, snapshot `name` + `price` (history survives menu edits/deletes), qty, subtotal.
  - `payments` — order_id FK, method enum (`cash/qris/ewallet`), amount, reference (QRIS transaction ref, nullable), paid_at, admin_id.
  - `shifts` — opened_at, closed_at, opening_cash, closing_cash, expected_total (sum of paid orders), admin_id — the unit of daily close / Z-report.
- Use the existing Postgres + Redis queue; order printing dispatched as queued jobs (`PrintReceipt`, `PrintKitchenTicket`).

## (A) vs (B) comparison

| Criterion | (A) Pawoon/Digipos integration | (B) In-house Filament POS |
| --- | --- | --- |
| Monthly cost | ~Rp100k–400k/mo + vendor approval latency | Rp0 (existing stack: Postgres, Redis queue, admin panel) |
| Time to cashier | Weeks (vendor approval + sync plumbing) | Days–weeks, fully under our control |
| Menu single source of truth | No — dual entry (app `menu_items` + POS catalog) needs sync | Yes — `menu_items` remains the only catalog |
| QRIS | Native, incl. merchant settlement | Static QRIS display today; dynamic QRIS + auto-confirm via Tripay/Xendit/Midtrans webhooks later |
| Offline resilience | Strong (POS device queues offline) | Weak — admin panel needs internet (mitigate: PWA/cached page) |
| Receipt/kitchen printing | Vendor hardware/ecosystem | `mike42/escpos-php` thermal printing, browser print, or WhatsApp receipt |
| Sales reports | In vendor app (split from website analytics) | Filament dashboard widgets/charts, one DB |
| Customization / exit cost | Vendor lock-in, data export hassle | Full ownership, no lock-in |

## Recommendation

**Build (B).** Reasoning:

1. Single-owner, one store, limited budget — the cost and complexity of (A) buys nothing this shop lacks.
2. Indonesian POS APIs are gated (Moka/Majoo) or absent (Qasir/BukuKas); only Pawoon/Digipos have real self-serve APIs, and both would create dual-catalog maintenance.
3. Square (the only platform with a truly excellent API) does not serve Indonesia; Loyverse does not process payments.
4. The codebase already has the admin panel, menu CRUD, Postgres, and a Redis worker — (B) is pure feature work on owned infrastructure.
5. No mature Filament POS plugin exists — but the custom pages are straightforward Filament v5 work.

Keep (A/Pawoon) as a documented fallback only if offline-hardware robustness becomes a hard requirement.

## Integration architecture sketch (B)

```
Browser (cashier tablet)                    AdminPanelProvider (guard: admin)
  |  +--------------------------------------------------------------+
  |  |  POS Page (Livewire)     Order Resource                      |
  |  |  cart -> create order -> pay -> served                       |
  |  |  payment: cash | QRIS(static) | e-wallet                     |
  |  +----------------------+---------------------------------------+
  |                         |
  |                +--------v--------+   +----------------------+
  |                |  Postgres       |   | Redis queue           |
  |                |  orders         |-->| PrintReceipt           |--> ESC/POS 58mm
  |                |  order_items*   |   | PrintKitchenTicket     |    (mike42/escpos-php)
  |                |  payments       |   +----------------------+
  |                |  shifts         |
  |                +--------+--------+
  |    webhook (paid)       |
  +----------------------->+  Tripay / Xendit / Midtrans (dynamic QRIS)
                              ^-- webhook receiver route + idempotent queue job
```

`*` `order_items` snapshots name/price at sale time (FK `menu_item_id` nullable) so order history survives menu edits. `payments` rows per method; `shifts` handle open/close + Z-report reconciliation.

## Milestones (M1–M4) with effort ratings and deliverables

| Milestone | Effort | Deliverables |
| --- | --- | --- |
| **M1** | S | Migrations (`orders`, `order_items`, `payments`, `shifts`); `Order` model + Filament v5 `OrderResource` (list/detail with status badge); cashier Livewire page: menu grid → cart → create pending order; `menu_items` gains `is_active` + `category`. Tests: order creation flow (mirror existing feature-test style). |
| **M2** | M | Payment capture (cash / static QRIS display / e-wallet manual confirmation); status transitions `pending → paid → served` with authorization; queued `PrintReceipt` + `PrintKitchenTicket` jobs; browser-print fallback. Tests: payment + status transition tests. |
| **M3** | M | Shift open/close + daily close (Z-report: total by payment method, cash count check); Filament dashboard widgets (today's sales, top items, payment split) using Filament chart support. Tests: shift math. |
| **M4** | L | Dynamic QRIS via Tripay/Xendit/Midtrans: payment initiation from POS, webhook receiver route (CSRF-exempt, signature-verified), idempotent queue job auto-marking orders `paid`, reconciliation job for stuck orders. Tests: webhook payloads, idempotency. |

## Cost estimates (IDR)

| Item | (A) per month | (B) one-off |
| --- | --- | --- |
| POS subscription | ~Rp100k–400k/mo (Pawoon/Digipos) | Rp0 |
| Payment gateway (dynamic QRIS, M4) | vendor-dependent (per-transaction fee) | gateway per-transaction fee only (Tripay/Xendit/Midtrans) |
| Development | API onboarding + sync plumbing (hours × weeks) | M1–M3 in-repo work; M4 gateway integration |
| Hardware (both) | cashier tablet + 58mm thermal printer (~Rp500k–1.5M once) | same |

Ongoing software cost of (B) = Rp0/month; (A) adds a standing subscription for a catalog the shop already manages.

## Reference URLs

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

## Findings complete

Research sessions are read-only by design; this report contains no code or configuration changes to the repository.
