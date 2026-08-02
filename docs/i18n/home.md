# Home Page Copy — Bahasa Indonesia

Final Indonesian copy for the home page (`resources/views/home.blade.php`), verified
against `lang/id/home.php`. Source of truth for the strings; decisions below explain
the choices so translations are not re-litigated.

Tone: warm, calm, unhurried, specialty-coffee proud; proper Bahasa Indonesia
(KBBI-friendly), no slang, no word-for-word translation. Personal address uses "Anda".

## Hero

| Key | Final string | Decision |
| --- | --- | --- |
| `hero.eyebrow` | `Diseduh perlahan, sejak 2015` | "Diseduh perlahan" names the ritual (seduh = slow brewing verb); "perlahan" is the poetic KBBI adverb, more unhurried than "pelan" |
| `hero.headline_prefix` + `headline_highlight` + `headline_suffix` | `Kopi yang layak ` **`dinikmati pelan-pelan`** `.` | "dinikmati pelan-pelan" is exactly how Indonesians describe savouring good coffee; only the highlighted phrase goes in the amber `<span class="text-amber-500">` |
| `hero.subtext` | `Biji single-origin, disangrai dalam batch kecil, diseduh segar saat pesanan masuk. Sudut yang tenang di tengah hari yang sibuk.` | "saat pesanan masuk" is a native service phrase; "di tengah hari yang sibuk" gives the calm-corner contrast a purpose |
| `hero.cta_menu` | `Lihat Menu` | Standard friendly CTA, matches the menu page's "Menu Kami" |
| `hero.cta_find` | `Kunjungi Kami` | Native phrase for a physical-store invitation, warmer than a literal "find us" |
| `hero.cta_wa` | `Pesan lewat WhatsApp` | "Pesan lewat" is the everyday polite way to order via chat |

## Feature cards

| Key | Final string | Decision |
| --- | --- | --- |
| `cards.whatsapp.title` | `WhatsApp` | Brand name, untranslated |
| `cards.whatsapp.body` | `Pesan lewat chat dan dapatkan balasan dalam hitungan menit — tanpa telpon bolak-balik, tanpa menunggu.` | "telpon bolak-balik" is the natural rendering of "phone tag"; "hitungan menit" is idiomatic for "in minutes" |
| `cards.qris.title` | `Terima QRIS` | Already perfect natural Indonesian, kept as-is |
| `cards.qris.body` | `Bayar dengan dompet digital apa pun — pindai, bayar, selesai. Tanpa uang tunai? Tidak masalah.` | "Dompet digital" is the standard e-wallet term; "pindai" is the KBBI word for scan |
| `cards.delivery.title` | `GoFood & GrabFood` | Brand names, untranslated |
| `cards.delivery.body` | `Pesan menu favorit untuk diantar langsung dari aplikasi yang biasa Anda pakai.` | "biasa Anda pakai" is the everyday phrasing for "already use" |
| `cards.delivery.gofood` / `grabfood` | `GoFood` / `GrabFood` | Brand names, untranslated |

## Favorites section

| Key | Final string | Decision |
| --- | --- | --- |
| `favorites.eyebrow` | `Favorit Pengunjung` | "Pengunjung" (visitors) is the natural crowd word for a café |
| `favorites.heading` | `Menu Andalan` | "Andalan" (signature/trusted picks) is what Indonesian cafés call featured menus |
| `favorites.full_menu` | `Menu Lengkap` | "Menu lengkap" is the natural way to say the full list; arrow is added in the Blade (`→`) |

## Closing CTA band

| Key | Final string | Decision |
| --- | --- | --- |
| `cta.heading` | `Meja Anda sudah menanti.` | "Menanti" is the poetic, patient version of waiting |
| `cta.body` | `Pesan tempat atau langsung datang — apa pun pilihannya, ketel sudah mendidih.` | "Langsung datang" is the everyday phrase for walk-ins; "ketel sudah mendidih" preserves the warm kettle image |
| `cta.button` | `Kontak & Jam Buka` | "Jam buka" is the standard Indonesian for opening hours; contact leads |

## Page title

`title` → `Coffee Shop — Kopi Spesialti, Diseduh Saat Dipesan`

- "Coffee Shop" is the brand, untranslated.
- "Kopi spesialti" is the accepted Indonesian specialty-coffee term.
- "Diseduh saat dipesan" is precise about the brew-to-order process and reads
  naturally in a title tag.

## Implementation notes

- Em-dash spacing (` — `) must be preserved.
- Only `dinikmati pelan-pelan` goes inside the amber span in the headline.
- Brand/app names never translated: Coffee Shop, WhatsApp, QRIS, GoFood, GrabFood,
  single-origin, batch.
