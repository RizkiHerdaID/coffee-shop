# Owner Tooling & AI Roadmap — Coffee Shop

Research for a single-owner Indonesian coffee shop (Laravel 13 + Filament 5, IDR pricing, production at coffee.rizkilab.my.id). Covers analytics, inventory, WhatsApp integrations, loyalty, AI features, and operations. All costs are IDR/month unless noted.

## Summary

The single most important finding: **no `orders` table exists today** — every analytics, forecasting, and AI feature depends on capturing transaction data first. The stack already has the pieces that make the rest cheap: a Redis queue and Laravel scheduler (notifications, summaries), MinIO/S3 storage (receipts, docs), and Filament widgets (dashboards). Production mail is the one gap — Mailpit is dev-only; use Resend's free tier (3,000 emails/month) or existing SMTP.

Recommended sequence: build order capture + dashboard widgets now (zero new services), add inventory and a low-cost Indonesian WhatsApp gateway next (Fonnte free tier first), and only then layer on AI copy/recommendation features using cheap API models (OpenAI nano/mini or Gemini free tier). Skip local LLMs / GPU rental — API calls are cheaper and maintenance-free at this scale.

## Baseline (current state)

| Item | Status |
| --- | --- |
| Data model | Only `MenuItem` (`name`, `price`, `note`, `sort_order`); no orders, customers, or stock |
| Admin panel | Filament v5 at `/admin`, default Dashboard (AccountWidget only) |
| Queue | Redis + queue worker running — ready for notification jobs |
| Scheduler | Available (Laravel scheduler) |
| Mail | Mailpit (dev only); production SMTP not configured |
| Storage | MinIO (S3-compatible) available for receipts/docs |
| Admin model | `Admin` with Filament auth; login rate-limited |

## Phased Roadmap

### PHASE: NOW — no new paid services

| Feature | Impact | Effort | Notes |
| --- | --- | --- | --- |
| `orders` + `order_items` tables, POS-lite order entry in Filament | High | M | Foundation for everything below; single owner, no device sync needed |
| Dashboard StatsWidgets: today's revenue, order count, average order value | High | S | Built into Filament v5 |
| Revenue-by-day and top-10 items charts | High | S | Filament Charts plugin (apex), free |
| Peak-hour heatmap (hour × day-of-week) + best sellers by revenue | Med | S | Same widget layer, one more query |
| Daily/weekly summary email via scheduler + `Mail::queue` | Med | S | Resend free tier or existing SMTP |
| `stock_items` (beans, milk, cups) with stock-in/stock-out and low-stock thresholds | Med | M | MinIO can store supplier documents |

### PHASE: NEXT — small recurring cost

| Feature | Impact | Effort | Notes |
| --- | --- | --- | --- |
| WhatsApp order confirmations + promo broadcasts | High | M | Fonnte free tier (1,000 text msgs/mo) → Lite Rp25K → Regular Rp66K (10k); or Wablas Nano Rp22K → Small Rp69K (10k) |
| Low-stock alerts pushed to WhatsApp | Med | S | Queued job; same gateway, ~Rp0–25K/mo |
| Recipe/ingredient links: menu_items ↔ stock_items pivot with grams/cups per item → COGS and cost-per-cup | Med-High | M-L | Pure Laravel, no external service |
| Wastage logging (spilled, past-sell-by) with reason codes | Low-Med | S | Feeds COGS and reorder suggestions |
| Loyalty: phone-number points/stamp cards + QR membership | Med | M | `bacon/bacon-qr-code` (free); QR on receipt + `wa.me` link |
| Expense tracking + cash register (open/close float, discrepancy report) | Med | M | Filament resources; SaaS alternatives: Moka, BukuWarung, Paper.id (Rp0–300K/mo) |
| Suppliers + purchase orders | Low | S | Filament CRUD |

### PHASE: LATER — AI + heavier

| Feature | Impact | Effort | Notes |
| --- | --- | --- | --- |
| AI promo copy + menu descriptions | Med | S | OpenAI `gpt-4o-mini` ($0.15/$1.60 per 1M tokens) or `gpt-5-nano` ($0.05/$0.40); ~Rp20–50K/mo at shop volume; Gemini free tier also fits |
| WhatsApp chatbot (menu recommendations, hours, order status) | Med | M | Webhook tier needed: Wablas Large Rp119K or Fonnte Master Rp175K; or stay text-only free tier |
| Demand forecasting (day-of-week + seasonal trend) | Med | M | Pure-PHP moving average is enough; `rubixml/rubix-ml` (free PHP ML) if wanted. No GPU needed |
| Google review auto-response drafts | Med | M | Google Places API (reviews) ~Rp100–200K/mo usage + AI call costs |
| Receipt OCR → auto-expense entry | Low-Med | M | OpenAI vision via `gpt-4o-mini` (~Rp25–100K/mo) or Google Cloud Vision $1.50/1k images; local Tesseract free but poor on Indonesian receipts |
| External BI | Low | S-M | Looker Studio is free (Laravel JSON feed endpoint); Grafana needs ~Rp100–300K/mo VPS. Filament widgets are sufficient for one shop |

## WhatsApp Gateway Comparison

| Gateway | Type | Cost (IDR/mo) | Notes |
| --- | --- | --- | --- |
| Wablas | Unofficial (own number via WhatsApp Web) | Nano Rp22K (1k msgs); Lite Rp36K (5k); Small Rp69K (10k); Medium Rp86K (unlimited text); Large Rp119K (unlimited + media + webhooks); Enterprise Rp139K | Mature, n8n support, webhooks; ban risk on cold broadcasts; free trial is watermarked |
| Fonnte | Unofficial (own number) | Free tier (1k text msgs); Lite Rp25K (1k); Regular Rp66K (10k); Regular Pro Rp110K (25k); Master Rp175K (unlimited) | Easiest API, free tier ideal for start; ban risk; no SLA (unofficial service) |
| WABA (Meta official) | Official Business API | Per-conversation, Indonesia ~Rp350–900 | Requires WhatsApp Business Profile setup + template approval; safest and most reliable; pricing per conversation type (marketing/utility/auth) |
| Qontak | Indonesian B2B SaaS | Rp1.5–5jt (custom quote) | Enterprise-grade, overkill for a single shop |
| Gupshup | BSP (Business Solution Provider) | ~Rp300–1.200 per message | Official channels via BSP; per-message pricing; adds up at promo-blast volume |

**Verdict**: Start with **Fonnte free tier** (zero cost, fastest to integrate with a Laravel HTTP client + queued jobs), move to **Wablas Large (Rp119K)** when webhooks/media/auto-reply matter, and consider **Meta WABA** only when the shop needs guaranteed official messaging for order confirmations at scale.

## AI Feature Details

### Models & pricing (API, per 1M tokens)

| Model | Input | Output | Use case |
| --- | --- | --- | --- |
| OpenAI `gpt-5-nano` | $0.05 | $0.40 | Cheapest; promo copy, descriptions |
| OpenAI `gpt-4o-mini` | $0.15 | $0.60 | Text + vision (receipt OCR), chatbot |
| OpenAI `gpt-5-mini` | $0.25 | $2.00 | Better reasoning when needed |
| Gemini (Flash) | ~$0.30 | ~$2.50 | Free tier available; good Indonesian-language support |

Batch API is 50% off for non-urgent jobs (e.g. nightly description generation).

### Indonesian LLM options
- **Skip Vast.ai / local GPU** (~$0.15–0.60/hr rental): cheaper and maintenance-free to use hosted APIs at this scale.
- Gemini free tier is the best zero-cost option for Indonesian-language text; OpenAI nano/mini is the best low-cost option overall.

### Package recommendations
| Package / service | Purpose | Cost |
| --- | --- | --- |
| `filament` StatsWidget (built-in) | KPI cards | Free |
| Filament Charts (apex) plugin | Revenue/top-items charts | Free |
| `bacon/bacon-qr-code` | Loyalty QR codes | Free |
| `rubixml/rubix-ml` | Demand forecasting ML (optional) | Free |
| OpenAI PHP SDK / direct HTTP + `laravel/notifications` | AI calls, notifications | Pay-per-token |
| Resend | Production transactional mail | Free 3k/mo |
| Google Cloud Vision | Receipt OCR alternative | $1.50/1k images |

## Operations

- **Expense tracking / cash register**: pure Filament resources (models + tables) — best fit for one owner; SaaS alternatives (Moka, BukuWarung, Paper.id) cost Rp0–300K/mo and would split data from the shop's own DB.
- **Supplier management**: simple Filament CRUD; MinIO for supplier documents/invoices.

## Reference URLs

- Wablas pricing: https://wablas.com/pricing
- Fonnte: https://fonnte.com/
- Meta WhatsApp Business: https://business.whatsapp.com
- Qontak: https://www.qontak.com/
- Gupshup: https://www.gupshup.io/
- OpenAI API pricing: https://platform.openai.com/docs/pricing
- Gemini API pricing: https://ai.google.dev/gemini-api/docs/pricing
- Google Looker Studio: https://lookerstudio.google.com
- Google Cloud Vision: https://cloud.google.com/vision
- Google Places API: https://developers.google.com/maps/documentation/places
- Resend (mail): https://resend.com
- Filament (StatsWidget, Charts): https://filamentphp.com
- bacon/bacon-qr-code: https://github.com/bacon/bacon-qr-code
- Rubix ML: https://github.com/RubixML/ML
- Moka: https://moka.co · BukuWarung: https://www.bukukas.co.id · Paper.id: https://www.paper.id

## Research Notes

- Pricing verified during research session: Wablas (wablas.com/pricing), Fonnte (fonnte.com), OpenAI API (platform.openai.com/docs/pricing). Fonnte/Wablas are unofficial gateways with ban risk; verify current plans before purchase.
- Gemini/OCR/Looker costs are approximations from provider docs; Meta WABA prices vary by conversation type and region.
