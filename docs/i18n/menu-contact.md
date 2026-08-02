# Menu & Contact Page Copy — Bahasa Indonesia

Final Indonesian copy for the menu and contact pages (`resources/views/menu.blade.php`,
`resources/views/contact.blade.php`), verified against `lang/id/menu.php` and
`lang/id/contact.php`. Source of truth for the strings; decisions below explain the
choices so translations are not re-litigated.

Tone: warm, calm, unhurried, specialty-coffee proud; proper Bahasa Indonesia
(KBBI-friendly), no slang. Personal address uses "Anda" (polite). "Coffee Shop" is the
brand name and stays untranslated.

## MENU PAGE

| Key | Final string | Decision |
| --- | --- | --- |
| `title` | `Menu — Coffee Shop` | "Menu" is a standard absorbed KBBI loanword; brand suffix stays |
| `eyebrow` | `Diseduh saat Anda memesan` | Unhurried, explains the ritual (seduh = slow brew verb), polite |
| `heading` | `Menu Kami` | Warm possessive adds hospitality |
| `intro` | `Semua harga dalam Rupiah. Susu oat atau susu kedelai bisa ditambahkan tanpa biaya ekstra.` | "Bisa ditambahkan" is how Indonesians talk about milk options; "susu kedelai" per KBBI (not "soy milk") |
| `categories.all` | `Semua` | Filter chip label |
| `categories.coffee` | `Kopi` | Filter chip label |
| `categories.non-coffee` | `Non-Kopi` | Filter chip label |
| `categories.snack` | `Camilan` | Filter chip label |
| `categories.food` | `Makanan` | Filter chip label |
| `sold_out` | `Habis` | Badge on unavailable items |
| `empty` | `Tidak ada menu dalam kategori ini.` | Empty state |

### WhatsApp pickup ordering strings (`menu.pickup.*`)

Used by the pickup-ordering flow on the menu page (see `docs/website.md`):

| Key | Final string |
| --- | --- |
| `add` | `Tambah ke pesanan` |
| `increase_aria` | `Tambah jumlah :item` |
| `decrease_aria` | `Kurangi jumlah :item` |
| `cart_title` | `Pesanan Anda` |
| `cart_empty` | `Keranjang masih kosong. Pilih menu untuk mulai memesan.` |
| `total` | `Total` |
| `order` | `Pesan via WhatsApp` |
| `remove_aria` | `Hapus :item dari pesanan` |
| `item_line` | `:name × :qty = :total` |
| `message_title` | `Pesanan Pickup — :shop` |
| `message_total` | `Total: :total` |
| `message_pickup` | `Ambil di toko (pickup)` |

## CONTACT PAGE

| Key | Final string | Decision |
| --- | --- | --- |
| `title` | `Kontak & Jam Buka — Coffee Shop` | "Kontak" and "Jam Buka" are standard absorbed/plain Indonesian; mirrors the H1 |
| `eyebrow` | `Kami menunggu kedatangan Anda` | Matches the unhurried mood; the invitation is implied, not imposed |
| `heading` | `Kontak & Jam Buka` | "Jam Buka" is the everyday phrase for opening hours |
| `hours_heading` | `Jam Buka` | What the sign on the door would say; "Jam Operasional" is institutional (rejected) |
| `find_heading` | `Lokasi Kami` | Covers address + maps button in one phrase; "Temukan Kami" is too literal (rejected) |
| `phone_label` | `Telepon:` | "Telepon" is the KBBI word matching the premium register |
| `email_label` | `Email:` | Universal absorbed term |
| `maps_button` | `Buka di Google Maps` | "Buka" is the standard calm action verb; Google Maps stays a proper noun |
| `map_title` | `Peta lokasi Coffee Shop` | iframe `title` for accessibility |
| `directions_button` | `Petunjuk Arah ke Sini` | Keyless Google Maps directions link |
| `wa_button` | `Hubungi via WhatsApp` | "Hubungi" is polite and premium |
| `qris.title` | `Terima QRIS` | Already natural Indonesian — "kita terima QRIS" is the phrase every Indonesian merchant uses |
| `qris.body` | `Bayar dengan dompet digital apa pun — pindai, bayar, selesai. Tanpa uang tunai? Tidak masalah.` | "Dompet digital" standard term, "pindai" the KBBI word for scan, rhythm mirrors the original |
| `reservations.heading` | `Reservasi Rombongan` | "Rombongan" is the natural word for a party of guests; "grup" feels corporate (rejected) |
| `reservations.body` | `Untuk enam orang atau lebih, cukup telepon sehari sebelumnya — meja pojok akan kami siapkan untuk Anda.` | "Meja pojok" is the warm everyday word for corner table; "meja sudut" is geometric (rejected) |

## Key decisions at a glance

- "Seduh/diseduh" everywhere (never "seduhan" as a noun) — the slow-brew heart of the brand.
- "Rombongan" over "grup"; "meja pojok" over "meja sudut"; "pindai" over "scan";
  "dompet digital" over "e-wallet".
- "Anda" (polite) throughout; no "kamu", no "ngobrol", no "beres", no "cash".
- Day labels and hours stay em-dash formatted (`Senin — Jumat`), matching
  `config/shop.php` / `site.days` style.
- Em-dash + spacing style (` — `) preserved in all strings.
