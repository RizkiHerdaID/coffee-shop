# Localization (i18n) — Coffee Shop

How Bahasa Indonesia copy and the ID/EN language switcher work in this app. Final
strings are the source of truth; the per-page docs below list them and the decisions
behind them.

## System overview

| Aspect | Implementation |
| --- | --- |
| Default locale | `id` (`config/app.php`, `.env` `APP_LOCALE=id`) |
| Fallback locale | `en` |
| Supported locales | `id`, `en` — hardcoded in `app/Http/Middleware/SetLocale.php` and the switch route |
| Middleware | `App\Http\Middleware\SetLocale` appended to the web group in `bootstrap/app.php` — reads `?lang=` query → session `locale` → `config('app.locale')`, validates against the allow-list |
| Switch route | `GET /lang/{locale}` (`routes/web.php`, name `lang.switch`) — persists choice in the session, `redirect()->back()`, 404 for unknown locales |
| Switcher UI | `resources/views/partials/language-switcher.blade.php` — segmented `ID | EN` pills, active pill amber-filled, inactive is a link to `lang.switch` |
| Translation files | `lang/{id,en}/` — one file per feature area (see table below) |
| Scope | Public site only; the Filament admin panel is not localized (Filament package chrome stays English) |

Switching keeps the visitor on the same page (`redirect()->back()`), never bounces to
the homepage. There are no URL prefixes — cookie/session-persisted switch only.

## Translation files

| File | Covers |
| --- | --- |
| `site.php` | Brand, nav, footer, meta description, day labels, WhatsApp prefill message |
| `home.php` | Home page copy (hero, feature cards, favorites, closing CTA) |
| `menu.php` | Menu page copy + WhatsApp pickup-ordering strings |
| `contact.php` | Contact page copy (hours, location, QRIS, reservations) |
| `dashboard.php`, `orders.php`, `stock.php`, `expenses.php`, `suppliers.php`, `purchase-orders.php`, `pos.php`, `recipes.php`, `wastage.php`, `qr.php`, `summary.php`, `whatsapp.php`, `ai-copy.php`, `menu-items.php` | Filament admin panel + services |

## Tone guide (applies to all copy)

Warm, calm, unhurried, specialty-coffee proud; proper Bahasa Indonesia (KBBI-friendly),
no slang, no word-for-word translation, no bureaucratic stiffness. Personal address uses
"Anda" (polite) — never "kamu", never "ngobrol"/"beres"/"cash".

- Em-dash spacing (` — `) preserved in all strings.
- Brand/app names never translated: Coffee Shop, WhatsApp, QRIS, GoFood, GrabFood,
  single-origin, batch, espresso, pour over.
- "Kopi spesialti" is the established Indonesian specialty-coffee term (used by Fore
  Coffee, Tanamera) — the meta description and home title use it.

## Key copy decisions (verified against `lang/id/`)

| String | Final Indonesian | Why |
| --- | --- | --- |
| Home title | `Coffee Shop — Kopi Spesialti, Diseduh Saat Dipesan` | "Diseduh saat dipesan" is precise about the brew-to-order process |
| Home hero headline | `Kopi yang layak dinikmati pelan-pelan.` | "dinikmati pelan-pelan" is how Indonesians describe savouring coffee |
| Home hero eyebrow | `Diseduh perlahan, sejak 2015` | Names the brewing ritual, poetic adverb "perlahan" |
| Nav Home | `Beranda` | KBBI-standard term for a website front page, short |
| Nav Menu | `Menu` | KBBI loanword; every Indonesian café site keeps it untranslated |
| Nav Contact | `Kontak` | KBBI loanword, standard nav label, pairs with Beranda/Menu |
| Nav CTA | `Pesan Meja` | Idiomatic for booking a table, fits the pill button |
| Mobile menu aria-label | `Buka menu` | Describes the resting (closed) state for screen readers |
| Footer tagline | `© :year :shop. Diseduh dengan sepenuh hati.` | Keeps the brewing metaphor, "sepenuh hati" is warm and unhurried |
| Footer hours link | `Jam Buka & Lokasi` | "Jam buka" is the everyday phrase for opening hours |
| Meta description | `:shop — kopi spesialti, diseduh perlahan dengan sepenuh hati. Mampir untuk espresso, pour over, dan roti panggang segar.` | Keeps every concept, ~128 chars, under the snippet limit |
| WhatsApp prefill (`site.wa_message`) | `Halo Coffee Shop, saya mau pesan kopi.` | Natural, polite first-person, short |
| Day labels (`site.days`) | `Senin — Jumat` / `Sabtu` / `Minggu` | KBBI-correct; em-dash spacing preserved from `config('shop.hours')` codes |
| Menu title | `Menu — Coffee Shop` | "Menu" untranslated; brand suffix stays |
| Menu eyebrow | `Diseduh saat Anda memesan` | Unhurried, explains the ritual, polite |
| Menu heading | `Menu Kami` | Warm possessive adds hospitality |
| Menu intro | `Semua harga dalam Rupiah. Susu oat atau susu kedelai bisa ditambahkan tanpa biaya ekstra.` | "Susu kedelai" per KBBI (not "soy milk") |
| Contact title/heading | `Kontak & Jam Buka — Coffee Shop` | "Jam Buka" is the everyday phrase |
| Contact eyebrow | `Kami menunggu kedatangan Anda` | Matches the unhurried mood |
| Hours heading | `Jam Buka` | What the sign on the door would say |
| Find-us heading | `Lokasi Kami` | Covers address + maps button |
| Labels | `Telepon:` / `Email:` | "Telepon" is the KBBI word, "Email" is the universal absorbed term |
| Maps button | `Buka di Google Maps` | "Buka" is the standard calm action verb |
| WhatsApp button | `Hubungi via WhatsApp` | Polite and premium |
| QRIS heading | `Terima QRIS` | Already natural Indonesian; kept as-is |
| QRIS body | `Bayar dengan dompet digital apa pun — pindai, bayar, selesai. Tanpa uang tunai? Tidak masalah.` | "Dompet digital" standard term, "pindai" the KBBI word for scan |
| Reservations heading | `Reservasi Rombongan` | "Rombongan" is the natural word for a party of guests |
| Reservations body | `Untuk enam orang atau lebih, cukup telepon sehari sebelumnya — meja pojok akan kami siapkan untuk Anda.` | "Meja pojok" is the warm everyday word for corner table |

## Language switcher behavior

- Segmented `ID | EN` pills (per the research recommendation): active locale is
  amber-highlighted and inert; the other locale is a link. Each link carries an
  `aria-label` in the TARGET language ("Ganti bahasa ke Bahasa Indonesia" /
  "Change language to English").
- Desktop: right end of the header row. Mobile: inside the mobile menu panel.
- Persisted in the session via `lang.switch`; `SetLocale` middleware applies it per
  request (`?lang=` query takes precedence for one-off deep links).

## See also

- `docs/website.md` — public site features (SEO, structured data, QR menu)
- `docs/i18n/home.md` — home page copy detail
- `docs/i18n/menu-contact.md` — menu + contact page copy detail
- `docs/i18n/layout-meta.md` — layout, nav, footer, meta/SEO copy detail
