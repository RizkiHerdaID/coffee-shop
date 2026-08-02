# Website — Coffee Shop

Documentation for the public-facing website of the coffee-shop codebase (Laravel 13 + Filament 5 + Tailwind 4, live at https://coffee-shop.example). Replaces the pre-implementation research in `docs/research-website.md`; research decisions and references are preserved under [Decisions & rationale](#decisions--rationale) and [References](#references).

Focus areas: conversion and the Indonesian market — mobile-first UX and page speed, QR table menus, WhatsApp ordering, QRIS, local SEO, photography, retention, and common mistakes.

## Overview

The public site is a small set of Blade views rendered by `App\Http\Controllers\PageController` (`app/Http/Controllers/PageController.php`):

| Route | Controller method | Purpose |
| --- | --- | --- |
| `GET /` | `home()` | Hero + WhatsApp/QRIS/delivery CTAs + top-4 available menu items |
| `GET /menu` | `menu()` | DB-driven menu with category filter chips, sold-out states, WhatsApp pickup cart, product structured data |
| `GET /qr/{table}` | `qr()` | Compact table menu for QR codes (`{table}` must be 1..`config('shop.tables')`, else 404) |
| `GET /contact` | `contact()` | NAP/hours from `config('shop.*')`, keyless Maps embed, QRIS section, WhatsApp button |
| `GET /lang/{locale}` | closure | Switch locale (`id`/`en`), persists to session, redirects back |
| `GET /sitemap.xml`, `GET /robots.txt` | closures | `routes/web.php:32-49` |

All routes are in `routes/web.php`. All user-facing copy comes from `lang/{id,en}/` (`site.php`, `home.php`, `menu.php`, `contact.php`, `qr.php`, `menu-items.php`); the default locale is `id`, switched via `app/Http/Middleware/SetLocale.php`.

## Current implementation (verified against code)

### Layout, SEO and meta — `resources/views/layouts/app.blade.php`

- **JSON-LD LocalBusiness** (type `Cafe`): `name`, `telephone`, `email`, `url`, `hasMap`, `address.streetAddress`, and `openingHoursSpecification` — all derived from `config('shop.*')` at render time (`layouts/app.blade.php:17-50`). The hours map keys (`mon_fri`/`sat`/`sun`) expand to English `dayOfWeek` arrays (`Monday`…`Friday` / `Saturday` / `Sunday`); each entry is built by splitting the hour string on the `—` separator (`config('shop.hours')`, `config/shop.php:18-22`).
- **OG/meta tags**: `og:title`, `og:description`, `og:type`, `og:url`, `og:image` (favicon), `og:site_name`, `twitter:card` (`layouts/app.blade.php:9-15`); meta description localized via `site.meta.default_description`.
- **Webfont loading**: `@fonts('instrument-sans')` (Laravel 13 Fonts via the Bunny CDN `fonts` plugin in `vite.config.js`) plus a hero woff2 `<link rel="preload">` resolved from the Vite-generated `public/build/fonts-manifest.json` (`layouts/app.blade.php:54-62`). Added in commit `03759ad` (Web Vitals pass).

Routes for SEO: `/sitemap.xml` (home/menu/contact, `changefreq=weekly`) and `/robots.txt` (`Sitemap:` pointing at the sitemap) are closures in `routes/web.php:32-49`. Note: a **static `public/robots.txt` also exists** and shadows the route in production (static files take precedence over routes) — it points at the relative `/sitemap.xml`, so it works under any domain; switch it to an absolute URL only if a crawler requires one.

### Menu model & admin — `app/Models/MenuItem.php`

Columns (migration `2026_08_02_000009_add_media_fields_to_menu_items_table.php`): `name`, `price` (integer IDR), `note`, `sort_order`, `photo` (string, nullable), `category` (string, nullable), `available` (boolean, default true). Note the column names are `photo`/`available` — earlier research proposed `image`/`is_active`; the implementation chose different names.

- `#[Fillable([...])]` + casts (`price` integer, `photo`/`category` string, `available` boolean).
- `ingredients()` belongsToMany `StockItem` (recipes) and `cogs()` computes ingredient cost — displayed in the admin table as COGS/margin columns (owned by the recipes feature, see `docs/owner-tools.md`).
- Admin: `app/Filament/Resources/MenuItems/` — `MenuItemForm` (`Schemas/MenuItemForm.php`) has price masking (`Rp` prefix, dot thousand separators, `$money` Alpine mask + regex rule + dehydrate to raw int), AI-generated notes via `AiCopyService` (suffix action, visible only when `config('services.deepseek.api_key')` is set), `FileUpload` for `photo` on disk **`public`**, a `Select` for `category` (coffee/non-coffee/snack/food), an `available` toggle, and `sort_order`. `MenuItemsTable` (`Tables/MenuItemsTable.php`) sorts by `sort_order` and shows photo/name/category/availability badge/price/COGS/margin.

**Storage nuance**: the form and views explicitly use `Storage::disk('public')` (local, `storage/app/public`, served via the `public/storage` symlink) — NOT the `s3` disk, even though `.env` sets `FILESYSTEM_DISK=s3` (MinIO in dev). Menu photos are therefore served from local storage; MinIO/S3 remains the default disk for other uploads. If S3-backed menu photos are desired later, the `->disk('public')` pins in `MenuItemForm.php` and the views must change.

### Menu page — `resources/views/menu.blade.php`

- **Category filter chips**: an "All" chip plus one chip per category actually present in the menu (only `coffee`, `non-coffee`, `snack`, `food` are offered by the form/seed; `menu.blade.php:22-36`), plain-JS filtering with an empty-state message (`:104-140`). Fixed in commit `832b69e` after a multi-token `classList` bug.
- **Sold-out handling**: unavailable items render at 50% opacity with a `Habis` badge and no add-to-cart controls (`menu.blade.php:40-54`); the menu schema still emits them with `OutOfStock` availability.
- **Photos**: lazy-loaded (`loading="lazy"` `decoding="async"`) with real `width`/`height` from `getimagesize()` to avoid CLS (`menu.blade.php:42-48`); home page highlights use fixed 80×80 thumbnails.
- **WhatsApp pickup ordering**: per-item add/stepper, a fixed bottom cart bar, and a `wa.me` deep link whose message is assembled client-side from localized templates (`menu.pickup.*` + `site.wa_message`) with `Rp` totals (`menu.blade.php:142-288`). The message format is mirrored server-side by `App\Services\WaPickupMessage` (`app/Services/WaPickupMessage.php` — `build()` and `formatPrice()`, `Rp 25.000` style) for testing; the page itself is pure client-side JS, no route.
- **Product structured data**: `@include('partials.menu-schema')` (`resources/views/partials/menu-schema.blade.php`) emits an `ItemList` of `Product` entries — `name`, `url`, `offers` (`priceCurrency: IDR`, dotted `price`, `InStock`/`OutOfStock` from `available`), plus `image` when a photo exists.

### Home page — `resources/views/home.blade.php`

- Hero CTAs: menu, contact, and a WhatsApp deep-link (`wa.me/<digits>?text=<urlencoded site.wa_message>`), phone digits stripped of formatting via `preg_replace('/\D/', '', config('shop.phone'))`.
- Three feature cards: WhatsApp order, QRIS, and delivery (GoFood/GrabFood buttons in brand colors linking to `config('shop.gofood_url')`/`config('shop.grab_url')` — currently placeholders, see Roadmap).
- "Terima QRIS" badge in the CTA band and an "Order online" GoFood/GrabFood strip.
- Highlights: first 4 **available** items ordered by `sort_order` (`PageController::home()`, `app/Http/Controllers/PageController.php:10-15`).

### Contact page — `resources/views/contact.blade.php`

- Hours rendered from `config('shop.hours')` with day labels `__("site.days.$day")`; NAP (`address`, `phone_display`, `email`), Maps search button (`config('shop.maps_url')`), WhatsApp button.
- **Keyless Maps embed**: `<iframe src="https://maps.google.com/maps?q=…&output=embed">` with `loading="lazy"` plus a Directions button (`https://www.google.com/maps/dir/?api=1&destination=…`) — no API key (commit `90613b6`).
- **QRIS section**: badge + placeholder QR SVG; the real static QRIS image is a TODO (`contact.blade.php:55-56` — drop the real image at `public/images/qris.png` and uncomment the `<img>`).

### QR table menu — `routes/web.php:12`, `app/Http/Controllers/PageController.php:22-29`

`/qr/{table}` (regex `\d+`, name `qr.menu`) renders `resources/views/qr.blade.php` — a compact, phone-width menu listing available items with `Rp` prices and an "open full menu" link. The table number is validated against `config('shop.tables')` (4 in `config/shop.php`); out-of-range/non-numeric tables 404.

**Printable codes**: `app/Filament/Pages/QrCodes.php` (admin page "Kode QR Meja", nav label from `qr.nav_label`) generates one SVG QR per table with `bacon/bacon-qr-code` (`composer.json`) as base64 data URIs pointing at `route('qr.menu', ['table' => $table])`, rendered by `resources/views/filament/pages/qr-codes.blade.php` with a print button and print CSS that hides the Filament chrome. Printing is browser print-to-PDF; codes are not exported as image files.

### Localization

All site copy is in `lang/{id,en}/` (`site.php`, `home.php`, `menu.php`, `contact.php`, `qr.php`, `menu-items.php`); `menu.categories.*` keys drive both the filter chips and the admin `Select` options. Locale switching: `GET /lang/{locale}` (session) via `app/Http/Middleware/SetLocale.php`, switcher partial `resources/views/partials/language-switcher.blade.php`. JSON-LD `dayOfWeek` values stay English by design.

### Tests

`tests/Feature/`: `SeoTest` (LocalBusiness JSON-LD, sitemap XML, robots), `QrMenuTest` (QR page render, `Rp 25.000` formatting, 404 for invalid tables), `MenuPageTest`, `HomePageTest`, `ContactPageTest`, `PageSpeedTest` (webfont/hero preload/lazy-image assertions from commit `03759ad`), `WaPickupTest` (message building + `WaPickupMessage::formatPrice`), `MenuItemFormTest` (photo/category/available form fields), `LocalizationTest`, `SeoTest`.

## Architecture / flow

```
Browser ──► GET /, /menu, /contact, /qr/{table}          routes/web.php
              └─► PageController                          app/Http/Controllers/PageController.php
                    └─► MenuItem query (sort_order, available)
              └─► layouts/app.blade.php                    LocalBusiness JSON-LD + OG/meta + font preload
              └─► partials/menu-schema.blade.php           ItemList/Product/Offer JSON-LD (menu only)
              └─► JS on menu.blade.php                     category filter + pickup cart → wa.me deep link
                  app/Services/WaPickupMessage.php         server-side mirror of the WA message (tests)

Admin: MenuItems resource (form/table)  ──► MenuItem rows (photo/category/available/sort_order)
       QrCodes Filament page             ──► SVG QR codes per table → print
```

Single source of truth: `config/shop.php` for name/phone/address/hours/GoFood-Grab URLs/maps URL; `MenuItem` rows for menu content. Nothing else hardcodes NAP/hours in views.

## Roadmap

Done since the research was written (see git log: `65b3686`, `d759c83`, `9044906`, `b771f92`, `3d583f1`, `03759ad`, `90613b6`, `ff0a6a6`): LocalBusiness schema, OG/meta, sitemap/robots, WhatsApp CTAs, QRIS badge, GoFood/Grab buttons, menu photos + category + availability, category filter chips, QR table menu with printable codes, product structured data, WhatsApp pickup ordering, webfont fix + hero preload, keyless Maps embed.

Still open:

| Item | Effort | Where | Notes |
| --- | --- | --- | --- |
| Real QRIS image | S | `public/images/qris.png` + `contact.blade.php` TODO | Placeholder SVG currently; get the real merchant QRIS QR |
| Real GoFood/Grab merchant URLs | S | `config/shop.php` `gofood_url`/`grab_url` | Currently `https://gofood.co.id/your-merchant` placeholders |
| Google Business Profile verification + NAP/hours audit | S (ops) | — | Hours already single-sourced in `config/shop.php`; keep identical across GBP/GoFood/Grab |
| Menu photography | M | `MenuItem.photo` via admin | Real latte/ambiance shots, not stock |
| Page-speed re-audit | S-M | `layouts/app.blade.php`, Vite | Web Vitals pass landed (`03759ad`); re-check at pagespeed.web.dev after big changes |
| Loyalty/stamp card (10th cup free via WhatsApp) + `LoyaltyProgram` schema | M | New model + Filament resource + partial | P2 |
| Real digital ordering + payment via dynamic QRIS (Xendit/Midtrans) | L | New routes/controllers | Planned as POS M4 (see `docs/pos.md`); the POS already captures in-store QRIS payments, this is the online/auto-QRIS variant — wait for order volume to justify fees |
| `robots.txt` static/route sync | S | `public/robots.txt` vs `routes/web.php` | Static file shadows the route in prod; keep sitemap URL in sync |

## Performance baseline (2026-08-02, worktree localhost — reference only)

Recorded during the Web Vitals pass (commit `03759ad`); numbers are localhost, not
production, but serve as the reference for re-audits.

- Server timing (curl `-w`, 3 runs): `/` TTFB 33–45 ms, `/menu` 36–41 ms,
  `/contact` 23–27 ms (localhost TTFB dominated by PHP boot + DB; Web Vitals are
  browser metrics — measure real users at pagespeed.web.dev / CrUX after deploy).
- Build assets: `app.css` 39.2 KB minified (Tailwind 4), `app.js` 0 B (empty entry),
  Instrument Sans woff2 ×3 ~16.5–17 KB each (self-hosted via `@fonts('instrument-sans')`).
- Runtime third-party requests: **zero** (no analytics, no CDN fonts, no embeds —
  wa.me/GoFood/Grab are plain links).

See `docs/roadmap.md` for open items (e.g. OG image fix, loyalty schema).

## Decisions & rationale

- **LocalBusiness JSON-LD from `config('shop.*')`** — zero new dependencies; the hours map translates 1:1 to `openingHoursSpecification`. Validate with the Rich Results Test.
- **QR menu as a route, not a feature** — `/menu` was already DB-driven and mobile-ready; `/qr/{table}` is one small controller method, QR SVG generation is a Filament page using `bacon/bacon-qr-code` (no external QR API), admin prints via browser print.
- **WhatsApp as the ordering channel** — no payment gateway integration needed for pickup; wa.me deep links with prefilled, localized messages. Keeps the fees of Xendit/Midtrans dynamic QRIS for later (M4) when volume justifies them.
- **Keyless Maps embed over Maps JavaScript API** — no API key, no billing risk; `output=embed` iframe + `maps/dir/?api=1` directions link cover discovery and navigation.
- **Photos on the local `public` disk** — the FileUpload and views pin `disk('public')` even though the app default is S3/MinIO; simpler and directly servable. Revisit if multi-server hosting needs S3-backed images.
- **Column names `photo`/`available`** — research drafts said `image`/`is_active`; implementation uses `photo`/`available` (see model/migration above). Any new code should use the implemented names.
- **Common mistakes to avoid (from research, still relevant)**: stock photos instead of real shots; prices drifting between site and delivery platforms (single source: `MenuItem.price`); no mobile CTA above the fold; hours hardcoded in Blade; PDF menus; heavy hero video hurting LCP; ignoring Google reviews (prominence is a ranking factor); payment mention without QRIS; no sold-out handling (now solved via `available`).

## References

- LocalBusiness structured data (required/recommended properties): https://developers.google.com/search/docs/appearance/structured-data/local-business
- Google Business Profile local-ranking tips: https://support.google.com/business/answer/7091
- Web Vitals thresholds + tools: https://web.dev/articles/vitals
- PageSpeed Insights audit: https://pagespeed.web.dev
- Rich Results Test: https://search.google.com/test/rich-results
- GoFood merchant ecosystem + signup: https://www.gojek.com/gofood/ → https://gofoodmerchant.co.id
- GrabMerchant portal: https://merchant.grab.com/id/id/
- Maps Booking API (reservations/ordering in Search): https://developers.google.com/maps-booking/guides/starter-integration/overview
- LoyaltyProgram schema: https://developers.google.com/search/docs/appearance/structured-data/loyalty-program
