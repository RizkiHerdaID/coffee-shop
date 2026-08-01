# Coffee Shop Website: Conversion & Indonesia-Market Research

Research summary for the coffee-shop codebase (Laravel 13 + Filament 5 + Tailwind 4, public site at coffee.rizkilab.my.id). Focus: conversion and the Indonesian market — mobile-first UX and page speed, QR menus, digital ordering, QRIS, local SEO, photography, retention, and common mistakes.

## Summary

The highest-leverage moves are all low-effort and use the current stack: LocalBusiness JSON-LD schema derived from `config('shop.*')`, Google Business Profile verification with consistent NAP/hours, WhatsApp deep-link CTAs, QRIS visibility, and buttons to GoFood/GrabFood merchant pages. The `/menu` page is already database-driven, which makes a QR table menu nearly free. Menu photography and page-speed optimization are the main medium-effort investments that drive conversion. Full payment-integrated ordering (Xendit/Midtrans dynamic QRIS) is only worth it once order volume justifies the fees.

## Prioritized Feature List

### P0 — Quick wins (S effort, current stack)

| Feature | Impact | Effort | Placement |
|---|---|---|---|
| LocalBusiness/"Cafe" JSON-LD schema (name, address, phone, hours, geo, menu URL) built from `config('shop.*')` | High | S | `layouts/app.blade.php` or `home.blade.php` head |
| Google Business Profile: verify, complete NAP + hours + category, respond to reviews, add photos | High | S | Ops task; hours already single-sourced in `config/shop.php` |
| WhatsApp CTA (wa.me prefilled order text) on nav/hero/contact | High | S | `home.blade.php`, `contact.blade.php`, `config/shop.php` |
| QRIS mention: "Terima QRIS" badge + printable static QRIS QR image | Med-High | S | `contact.blade.php` / menu footer |
| GoFood/GrabFood buttons linking to merchant pages | High | S | Footer + `home.blade.php` (no API integration needed) |
| OG/meta tags, `sitemap.xml`, `robots.txt` | Med | S | Layout head; new route or static files |

### P1 — Next (M effort)

| Feature | Impact | Effort | Placement |
|---|---|---|---|
| QR table menu: `/qr/{table}` compact mobile layout (existing `/menu` is DB-driven; QR links with `?table=`) | High | S-M | New route + view; print QR via Filament action |
| Menu photos + `category` + `available` flag on `MenuItem`; Filament `FileUpload` → MinIO/S3; responsive lazy-loaded images with fixed dimensions | High | M | Migration + `MenuItemForm.php` + `menu.blade.php` |
| Page-speed pass: LCP ≤2.5s, INP ≤200ms, CLS ≤0.1 (75th percentile); preload hero, `loading="lazy"`, `font-display: swap`, no heavy third-party embeds; audit at pagespeed.web.dev | High | M | `layouts/app.blade.php`, Vite/Tailwind |
| WhatsApp-based pickup ordering: checkbox list on menu → generated wa.me message | Med-High | M | New route/JS on `menu.blade.php` |
| Product structured data (name, price IDR, image) on menu page | Med | S-M | `menu.blade.php` |

### P2 — Later

| Feature | Impact | Effort | Placement |
|---|---|---|---|
| Real digital ordering + payment via Xendit/Midtrans dynamic QRIS API | High | L | New routes/controllers; wait for volume to justify fees |
| Loyalty/stamp card (e.g., 10th cup free via WhatsApp) + Google `LoyaltyProgram` schema | Med | M | New model + Filament resource |
| Keyless Maps embed iframe + Directions link | Low | S | `contact.blade.php` |

## Quick Wins with Current Stack

1. JSON-LD schema + meta tags in the layout from `config('shop.*')` — zero new dependencies; the existing hours map translates 1:1 to `openingHoursSpecification`; validate with the Rich Results Test.
2. WhatsApp deep-link + QRIS mention on contact/home — pure Blade edits.
3. QR menus: `/menu` is already DB-driven and mobile-ready; a `/qr/{table}` route is one small controller method. Admin just prints the QR code.
4. Footer links to GoFood/GrabFood merchant pages — ~15 minutes of work, real discovery value.
5. Keep NAP/hours identical across site, Google Business Profile, GoFood, and Grab — mismatch is the #1 local SEO killer.

## Common Mistakes to Avoid

- Stock photos instead of real latte/ambiance shots.
- Prices drifting between the site and delivery platforms (single source of truth: `MenuItem.price`).
- No mobile CTA above the fold.
- Hours hardcoded in Blade instead of `config('shop.*')`.
- PDF menus (slow, unmobile-friendly, not indexable).
- Heavy hero video hurting LCP.
- Ignoring Google reviews (prominence is a ranking factor).
- Payment mention without QRIS (cashless is the norm in Indonesia).
- No "sold out" handling — add an `available` flag to the menu model.

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
