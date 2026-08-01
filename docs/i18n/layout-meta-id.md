# Lokalisasi Bahasa Indonesia — Layout & Meta/SEO

**Scope:** shared layout (nav + footer), meta/SEO strings, language switcher UX. Research only — no code changes.
**Brand mood:** hangat (warm), tenang (calm), slow-brew specialty coffee. Tone: proper Bahasa Indonesia (KBBI-friendly), no slang, never literal translation.
**Context notes:**
- Brand name "Coffee Shop" is a proper noun defined in `config/shop.php` and stays as-is in **both** locales; only the surrounding labels are translated.
- The site already mixes one Indonesian heading (`Terima QRIS` on home + contact), so Indonesian copy sits naturally alongside the existing design.
- Real-site conventions observed (research): Tanamera Coffee (specialty roastery, Indonesia) uses short uppercase nav labels (SHOP / SERVICES / ABOUT US / FAQ), a plain-language footer line `© 2026 Tanamera Coffee Indonesia`, and a bare "English" text link in the nav as its language switcher. Indonesian café/restaurant sites conventionally use **Beranda / Menu / Kontak / Hubungi Kami**, **Pesan Meja / Reservasi**, and **Jam Buka / Jam Operasional**; "Menu" is a KBBI loanword and is rarely translated.
- Halamannya kecil — prefer short, calm labels over long sentences. Keep nav labels under 10 characters where possible.

---

## NAV

### Brand name "Coffee Shop" (nav wordmark)

1. **Keep "Coffee Shop" as-is in both locales** — it is a proper noun (matches `config('shop.name')`, OG `site_name`, and the JSON-LD `name`); translating a brand wordmark reads as a translation error, and the amber ☕ wordmark carries the identity.
2. Append a tagline "Kedai Kopi Spesialti" under the wordmark — informative but visually noisy in the slim fixed header; the home hero already communicates this.
3. Swap to an Indonesian brand ("Kedai Kopi") — out of scope: the brand name is explicitly fixed in config.

**RECOMMENDED: Keep "Coffee Shop" untranslated in both locales; no nav-label changes needed for the brand itself.**

### "Home"

1. **Beranda** — the KBBI-standard term for a website's front page; short (7 chars), universally understood in Indonesian web conventions, calm and friendly.
2. **Halaman Utama** — literal "main page", correct but longer and sounds more like an admin/help link than a café nav.
3. **Utama** — terse, but ambiguous as a standalone noun ("utama" is an adjective); avoid.

**RECOMMENDED: Beranda**

### "Menu"

1. **Menu** — "menu" is a listed KBBI loanword; every Indonesian café site keeps it untranslated. Zero cost, identical in both locales, trivially consistent with the footer link.
2. **Daftar Menu** — "menu list"; more explicit but redundant in a nav that already points at a page literally titled with prices.
3. **Menu & Harga** — common on small restaurant sites but it over-promises the page's scope in a nav label and is too long for the pill layout.

**RECOMMENDED: Menu** (unchanged; keep the same string in both locales)

### "Contact"

1. **Kontak** — KBBI loanword, short (6 chars), the standard nav label on Indonesian business sites; pairs cleanly with "Beranda" / "Menu".
2. **Hubungi Kami** — "Contact us"; warmer, and very common on Indonesian café sites, but 11 chars — noticeably longer than its nav siblings.
3. **Kontak & Alamat** — informative but duplicates footer wording; too long for the nav row.

**RECOMMENDED: Kontak**

### "Reserve a Table" (nav CTA button)

1. **Pesan Meja** — natural, idiomatic Indonesian for booking a table ("pesan" = order/book, KBBI); short (10 chars), fits the pill button, warm without being slangy.
2. **Buat Reservasi** — "make a reservation"; "reservasi" is KBBI and common in F&B, but slightly formal/loanword-heavy for a small button.
3. **Pesan Tempat Duduk** — literal "book a seat"; awkward — nobody says this; reject.

**RECOMMENDED: Pesan Meja**

### Mobile menu button aria-label: "Toggle menu"

1. **Buka menu** — "open menu" describes the action in the button's resting (closed) state, which is what screen-reader users need; natural and calm.
2. **Alihkan menu** — literal "toggle menu"; technically accurate ("alih" = KBBI) but stilted; screen readers announce it as an action, not a state.
3. **Buka atau tutup menu** — most explicit, but verbose for an aria-label; unnecessary since the state is conveyed by the button itself.

**RECOMMENDED: Buka menu**

---

## FOOTER

### "© {year} Coffee Shop. Brewed with care."

1. **© {year} Coffee Shop. Diseduh dengan sepenuh hati.** — keeps the brewing metaphor ("diseduh" = brewed, KBBI) and adds "sepenuh hati" (wholehearted) — warm, unhurried, precisely the brand register; short enough for the footer bar.
2. **© {year} Coffee Shop. Diseduh dengan penuh ketelitian.** — "dengan penuh ketelitian" is a closer literal of "with care" (meticulousness) but sounds clinical, like a QA report.
3. **© {year} Coffee Shop. Setiap cangkir, diseduh dengan cinta.** — brand-y and poetic ("every cup, brewed with love") but twice as long as the current line and "cinta" edges toward slangy marketing.

**RECOMMENDED: © {year} Coffee Shop. Diseduh dengan sepenuh hati.**

### "Menu" (footer link)

1. **Menu** — same KBBI loanword as the nav; keeping both identical is the only consistent choice.
2. **Daftar Menu** — see nav rationale; redundant.
3. **Menu Lengkap** — "full menu"; adds no value in the footer.

**RECOMMENDED: Menu** (unchanged, identical to nav)

### "Hours & Location"

1. **Jam Buka & Lokasi** — "jam buka" (opening hours) is the everyday Indonesian phrase and "lokasi" is standard; short, clear, and mirrors the contact page's "Opening hours" / "Find us" split.
2. **Jam Operasional & Alamat** — "jam operasional" is the formal/business variant and "alamat" is precise; stiffer, better for B2B sites.
3. **Kunjungi Kami** — "visit us" is warm but hides the hours/address information the label promises; reject as a direct replacement.

**RECOMMENDED: Jam Buka & Lokasi**

---

## META/SEO

### Default meta description + OG description
Current: "Coffee Shop — specialty coffee, slow-brewed with care. Visit us for espresso, pour over, and fresh bakes."

1. **Coffee Shop — kopi spesialti, diseduh perlahan dengan sepenuh hati. Mampir untuk espresso, pour over, dan roti panggang segar.** — keeps every concept (specialty / slow brew / care / espresso / pour over / bakes), "kopi spesialti" is the established Indonesian specialty-coffee term, "mampir" (drop by) is warm and idiomatic; ~128 chars, comfortably under the ~155-char snippet limit.
2. **Coffee Shop — kopi spesialti yang diseduh pelan-pelan dengan penuh perhatian. Nikmati espresso, pour over, dan roti segar buatan sendiri.** — "pelan-pelan" (slowly) is warm and conversational, but "penuh perhatian" + "buatan sendiri" push it over ~150 chars and risk truncation.
3. **Kopi spesialti diseduh dengan tenang dan penuh perhatian di Coffee Shop. Mampirlah untuk espresso, pour over, dan kudapan segar.** — leading with mood instead of the brand is a valid SEO variant, but the brand should lead for the shared default description; "kudapan" (snacks) dilutes "fresh bakes".

**RECOMMENDED: Coffee Shop — kopi spesialti, diseduh perlahan dengan sepenuh hati. Mampir untuk espresso, pour over, dan roti panggang segar.** (use the same string for `meta description` and `og:description`)

### Home title
Current: "Coffee Shop — Specialty Coffee, Brewed to Order"

1. **Coffee Shop — Kopi Spesialti, Diseduh Saat Dipesan** — "brewed to order" rendered as "diseduh saat dipesan" (brewed when ordered): precise, honest about the shop's process, and reads naturally; ~52 chars.
2. **Coffee Shop — Kopi Spesialti, Segar dari Seduhan** — poetic ("fresh from the brew") but vague about the "to order" promise; weaker for search intent.
3. **Coffee Shop — Kedai Kopi Spesialti di Jakarta Selatan** — SEO-rich with location (matches the config address), but "kedai" contradicts the intended café positioning and the string drifts from the given copy.

**RECOMMENDED: Coffee Shop — Kopi Spesialti, Diseduh Saat Dipesan**

### Menu title
Current: "Menu — Coffee Shop"

1. **Menu — Coffee Shop** — "Menu" is the KBBI loanword used everywhere in Indonesian F&B; identical in both locales, zero ambiguity.
2. **Daftar Menu — Coffee Shop** — redundant expansion; no SEO or UX gain.
3. **Menu & Harga — Coffee Shop** — adds "& Harga" (prices) which the page does show, but changes the promise of the string and breaks consistency with the nav label.

**RECOMMENDED: Menu — Coffee Shop** (unchanged)

### Contact title
Current: "Contact & Hours — Coffee Shop"

1. **Kontak & Jam Buka — Coffee Shop** — mirrors the footer recommendation ("Jam Buka & Lokasi"), KBBI-friendly, and keeps the two concepts from the original; ~31 chars.
2. **Hubungi Kami & Jam Buka — Coffee Shop** — warmer opening, but 39 chars and inconsistent with the short nav label "Kontak".
3. **Kontak & Jam Operasional — Coffee Shop** — the formal "jam operasional" mismatches the calm, everyday register.

**RECOMMENDED: Kontak & Jam Buka — Coffee Shop**

### Default page title
Current: "Coffee Shop"

1. **Coffee Shop** — the brand is a proper noun; the default title should stay identical in both locales.
2. **Coffee Shop — Kopi Spesialti** — adds descriptor context when a page forgets to set a title; harmless but unrequested.
3. **Coffee Shop | Kedai Kopi Spesialti, Jakarta Selatan** — SEO-rich default, but the "kedai" framing and location claim belong in a deliberate per-page title, not a fallback.

**RECOMMENDED: Coffee Shop** (unchanged)

---

## LANGUAGE SWITCHER (recommendation)

**Context:** only two locales (`id` primary, `en` secondary). Real Indonesian sites (e.g. Tanamera) use a bare text link ("English") in the nav — simple, but it only announces the *other* language, not the current state.

### Label wording

1. **Segmented "ID | EN" pills** — two compact, always-visible options; the active one is highlighted (amber, matching the brand), the other is clickable. Unambiguous, tiny footprint, works in both locales without translating the labels themselves.
2. "Bahasa Indonesia" / "English" text links — most human, but ~15 chars each; crowds the slim header and the CTA.
3. Globe icon only — least text, but needs a tooltip/aria-label and is less discoverable for the target audience.

**RECOMMENDED: "ID | EN" segmented pills**, each with `aria-label` ("Ganti bahasa ke Bahasa Indonesia" / "Change language to English" — label in the target language). Display the non-active language prominently (amber hover), keep the active one muted.

### Placement

- **Desktop:** right side of the header row, between the nav links and the "Pesan Meja" CTA (or after the CTA if spacing is tight) — visually a natural "settings" position at the row's end.
- **Mobile:** keep the pills in the mobile menu panel (top of the panel, above the links) so the already-crowded header row with the hamburger stays untouched; optionally also a compact "ID|EN" in the header row itself.

**RECOMMENDED: header row (desktop, rightmost, before the CTA if space allows) + inside the mobile menu panel (top).**

### Behavior

- **Persist the choice** in a cookie (e.g. `locale`) read by middleware, so the choice survives navigation and return visits; Laravel `app()->setLocale()` per request.
- **Stay on the same page**: switching must keep the visitor on the current page (same route, new locale) — never bounce to the homepage. This is the single most common local-switcher sin and it breaks the calm feel.
- Default locale is `id` (primary); `en` only when the user opts in. Set `<html lang>` dynamically (`id` / `en`) and keep OG tags in the active locale.
- Don't use URL prefixes (`/en/...`) for a two-locale site with a cookie-persisted switch — simpler, and avoids duplicate-URL SEO issues; if URLs ever go public per-locale, revisit with `hreflang`.

**RECOMMENDED: cookie-persisted switch (`id` default) that stays on the current page; no URL prefixes.**
