# Layout, Nav, Footer & Meta Copy — Bahasa Indonesia

Final Indonesian copy for the shared layout (`resources/views/layouts/app.blade.php`),
nav, footer, meta/SEO strings and language switcher UX — verified against
`lang/id/site.php`. Source of truth for the strings; decisions below explain the
choices so translations are not re-litigated.

Tone: hangat (warm), tenang (calm), slow-brew specialty coffee. Proper Bahasa
Indonesia (KBBI-friendly), no slang, never literal translation. Keep nav labels short
(under ~10 characters where possible).

## Brand name "Coffee Shop"

Kept as-is in **both** locales — a proper noun matching `config('shop.name')`, OG
`site_name`, and the JSON-LD `name`. Translating a brand wordmark reads as a
translation error.

## NAV (`site.nav.*`)

| Key | Final string | Decision |
| --- | --- | --- |
| `home` | `Beranda` | KBBI-standard term for a website front page; short (7 chars), calm |
| `menu` | `Menu` | KBBI loanword; every Indonesian café site keeps it untranslated |
| `contact` | `Kontak` | KBBI loanword, short (6 chars), the standard nav label on Indonesian business sites |
| `reserve` | `Pesan Meja` | Idiomatic for booking a table ("pesan" = order/book, KBBI), fits the pill button |
| `toggle_aria` | `Buka menu` | Describes the action in the button's resting (closed) state for screen readers |

## FOOTER (`site.footer.*`)

| Key | Final string | Decision |
| --- | --- | --- |
| `tagline` | `© :year :shop. Diseduh dengan sepenuh hati.` | Keeps the brewing metaphor and adds "sepenuh hati" (wholehearted) — warm, unhurried, short enough for the footer bar |
| `menu` | `Menu` | Identical to the nav label — the only consistent choice |
| `hours_location` | `Jam Buka & Lokasi` | "Jam buka" is the everyday phrase; mirrors the contact page's hours/find split |

## META/SEO (`site.meta.*`)

| Key | Final string | Decision |
| --- | --- | --- |
| `default_description` | `:shop — kopi spesialti, diseduh perlahan dengan sepenuh hati. Mampir untuk espresso, pour over, dan roti panggang segar.` | Keeps every concept (specialty / slow brew / care / espresso / pour over / bakes), "kopi spesialti" is the established Indonesian specialty-coffee term, "mampir" (drop by) is warm and idiomatic; ~128 chars, under the ~155-char snippet limit. Used for both `meta description` and `og:description` |

### Per-page titles

| Page | Final title (`lang/{id,en}/*.php` `title`) | Decision |
| --- | --- | --- |
| Home | `Coffee Shop — Kopi Spesialti, Diseduh Saat Dipesan` | "Diseduh saat dipesan" is precise about the brew-to-order process |
| Menu | `Menu — Coffee Shop` | "Menu" is the KBBI loanword used everywhere in Indonesian F&B; identical in both locales |
| Contact | `Kontak & Jam Buka — Coffee Shop` | Mirrors the footer recommendation, KBBI-friendly |
| Default | `Coffee Shop` (brand from `config('shop.name')`) | Proper noun; the default title stays identical in both locales |

## Day labels (`site.days.*`)

Keyed by the `config('shop.hours')` codes; used in the hours loop on the contact page:

| Code | Final string |
| --- | --- |
| `mon_fri` | `Senin — Jumat` |
| `sat` | `Sabtu` |
| `sun` | `Minggu` |

The only KBBI-correct day names; em-dash spacing kept exactly as in `config/shop.php`.

## WhatsApp prefill (`site.wa_message`)

`Halo Coffee Shop, saya mau pesan kopi.` — natural, polite first-person ("saya"),
short; "mau pesan kopi" is exactly how Indonesians open an order chat. Used as the
`wa.me` query-string prefill across the site.

## Language switcher

- Segmented **ID | EN pills** (research recommendation adopted): active locale is
  amber-highlighted (matching the brand) and inert; the inactive locale is a link.
  Each link has an `aria-label` in the TARGET language ("Ganti bahasa ke Bahasa
  Indonesia" / "Change language to English"). See
  `resources/views/partials/language-switcher.blade.php`.
- Placement: desktop — right end of the header row; mobile — inside the mobile menu
  panel (top).
- Behavior: choice persisted in the session (`GET /lang/{locale}` →
  `redirect()->back()`, stays on the same page — never bounces to the homepage).
  Default locale `id`; `en` only when the user opts in. `<html lang>` is set
  dynamically; OG tags render in the active locale. No URL prefixes (`/en/...`) —
  avoids duplicate-URL SEO issues for a two-locale site.
