# Menu & Contact Pages — Bahasa Indonesia Copy Proposal

Tone guide applied throughout: warm, calm, unhurried, specialty-coffee proud; proper Bahasa Indonesia (KBBI-friendly), no slang, no word-for-word translation, no bureaucratic stiffness. Personal address uses "Anda" (polite) rather than "kamu" (too casual for this premium-calm brand).

Source strings: `resources/views/menu.blade.php`, `resources/views/contact.blade.php`, `config/shop.php`. Note: "Coffee Shop" is the brand name and stays untranslated in page titles. Day labels live in `config/shop.php` (used as `$day` in the hours loop); the em-dash + spacing style (` — `) must be preserved.

---

## MENU PAGE

### 1. Page title: "Menu — Coffee Shop"

| Option | Rationale |
| --- | --- |
| **Menu — Coffee Shop** | "Menu" is a standard absorbed loanword in Indonesian; brand suffix stays. |
| Menu Kopi — Coffee Shop | Adds "Kopi" for flavour but reads redundant ("menu coffee"), not an improvement. |
| Daftar Menu — Coffee Shop | "Daftar menu" is correct but sounds like a price list, not a warm menu page. |

**RECOMMENDED:** Menu — Coffee Shop

---

### 2. Eyebrow: "Brewed to order"

| Option | Rationale |
| --- | --- |
| **Diseduh saat Anda memesan** | Unhurried, explains the ritual (seduh = slow brew verb), polite. |
| Setiap cangkir diseduh setelah ada pesanan | More descriptive, but long for an eyebrow line. |
| Diseduh setiap kali ada pesanan | Fine, but "setiap kali" feels mechanical next to the slow-brew mood. |

**RECOMMENDED:** Diseduh saat Anda memesan

---

### 3. H1: "The Menu"

| Option | Rationale |
| --- | --- |
| **Menu Kami** | Warm ("our menu") and direct; the possessive adds hospitality. |
| Menu | Minimal, correct, but colder than the brand's tone. |
| Pilihan Kami | "Our selection" — premium but strays from the plain menu idea. |

**RECOMMENDED:** Menu Kami

---

### 4. Intro line: "All prices in Indonesian Rupiah. Oat and soy milk available at no extra charge."

| Option | Rationale |
| --- | --- |
| **Semua harga dalam Rupiah. Susu oat atau susu kedelai bisa ditambahkan tanpa biaya ekstra.** | "bisa ditambahkan" is how Indonesians actually talk about milk options; "ekstra" is a normal absorbed word. |
| Semua harga dalam Rupiah. Susu oat dan susu kedelai tersedia tanpa biaya tambahan. | Fully KBBI-worded ("tambahan" over "ekstra") but "tersedia" sounds like a warehouse, not a coffee bar. |
| Harga dalam Rupiah. Mau susu oat atau kedelai? Tidak ada biaya tambahan. | Warm and conversational, but the rhetorical question drops the page's calm formality. |

Note: "susu kedelai" (KBBI) — avoid the English "soy milk".

**RECOMMENDED:** Semua harga dalam Rupiah. Susu oat atau susu kedelai bisa ditambahkan tanpa biaya ekstra.

---

## CONTACT PAGE

### 5. Page title: "Contact & Hours — Coffee Shop"

| Option | Rationale |
| --- | --- |
| **Kontak & Jam Buka — Coffee Shop** | "Kontak" and "Jam Buka" are both standard absorbed/plain Indonesian; mirrors the H1. |
| Hubungi Kami & Jam Buka — Coffee Shop | Warmer verb, but longer and a click-through title doesn't need the verb. |
| Kontak & Jam Operasional — Coffee Shop | "Operasional" is bureaucratic/stiff — exactly what to avoid. |

**RECOMMENDED:** Kontak & Jam Buka — Coffee Shop

---

### 6. Eyebrow: "We would love to see you"

| Option | Rationale |
| --- | --- |
| **Kami menunggu kedatangan Anda** | Matches the unhurried mood ("we're waiting for you"); the invitation is implied, not imposed. |
| Senang sekali bisa menyambut Anda | Very warm ("we'd be delighted to welcome you") but wordy for an eyebrow. |
| Kami ingin bertemu Anda | Too literal ("we want to meet you") — sounds like a job interview. |

**RECOMMENDED:** Kami menunggu kedatangan Anda

---

### 7. H1: "Contact & Hours"

| Option | Rationale |
| --- | --- |
| **Kontak & Jam Buka** | Matches the page title; "Jam Buka" is the everyday Indonesian phrase for opening hours. |
| Hubungi Kami & Jam Buka | Friendlier verb, but the heading is a label, not an invitation. |
| Jam Buka & Kontak | Swaps the order — the page is primarily a contact page, so keep contact first. |

**RECOMMENDED:** Kontak & Jam Buka

---

### 8. Block heading: "Opening hours"

| Option | Rationale |
| --- | --- |
| **Jam Buka** | The natural, warm everyday phrase — what the sign on the door would say. |
| Jam Operasional | Correct but institutional; belongs on a bank website. |
| Waktu Buka | Grammatically fine but uncommon in consumer copy. |

**RECOMMENDED:** Jam Buka

---

### 9. Day labels: "Monday — Friday" / "Saturday" / "Sunday" (em-dash style preserved)

| Option | Rationale |
| --- | --- |
| **Senin — Jumat / Sabtu / Minggu** | The only KBBI-correct day names; em-dash spacing kept exactly as in `config/shop.php`. |
| Hari Senin — Hari Jumat / Hari Sabtu / Hari Minggu | Overly explicit — "hari" is already implied by the day names. |
| Keep English labels | Contradicts localization; Indonesian week order (Monday-first) matches the grouping anyway. |

**RECOMMENDED:** Senin — Jumat / Sabtu / Minggu

---

### 10. Block heading: "Find us"

| Option | Rationale |
| --- | --- |
| **Lokasi Kami** | Standard, warm, and covers address + maps button in one phrase. |
| Temukan Kami | Too literal ("find us" as an imperative) — sounds like a scavenger hunt. |
| Alamat Kami | Accurate but narrow; the block also holds phone/email/buttons. |

**RECOMMENDED:** Lokasi Kami

---

### 11. Labels: "Phone:" / "Email:"

| Option | Rationale |
| --- | --- |
| **Telepon: / Email:** | "Telepon" is the KBBI word and matches the premium register; "Email" is the universal absorbed term. |
| Telp: / Email: | The common abbreviation works in print, but looks clipped next to the warm prose. |
| Telepon: / Surel: | "Surel" is KBBI-sanctioned but reads bureaucratic and unfamiliar to most users. |

**RECOMMENDED:** Telepon: / Email:

---

### 12. Button: "Open in Google Maps"

| Option | Rationale |
| --- | --- |
| **Buka di Google Maps** | "Buka" is the standard, calm action verb; Google Maps stays a proper noun. |
| Lihat di Google Maps | Fine, but "lihat" (see) is weaker for a destination action. |
| Petunjuk arah ke sini | "Directions to us" — warm, but the button opens a search URL, not a directions link, so it would overpromise. |

**RECOMMENDED:** Buka di Google Maps

---

### 13. Button: "Chat on WhatsApp"

| Option | Rationale |
| --- | --- |
| **Hubungi via WhatsApp** | "Hubungi" is polite and premium; "via" is standard absorbed Indonesian for "through". |
| Chat WhatsApp | Ubiquitous in Indonesian commerce, but the English word "chat" sits flat next to the warm copy. |
| Ngobrol di WhatsApp | "Ngobrol" is too casual/slang-adjacent for this register. |

**RECOMMENDED:** Hubungi via WhatsApp

---

### 14. QRIS block heading: "Terima QRIS"

| Option | Rationale |
| --- | --- |
| **Terima QRIS (keep as-is)** | Already perfect, natural Indonesian — "kita terima QRIS" is the phrase every Indonesian merchant uses. |
| Pembayaran QRIS | Correct but turns a welcome into a label. |
| Bayar Pakai QRIS | Fine, but it's an instruction, not a greeting. |

**RECOMMENDED:** Terima QRIS (no change needed)

---

### 15. QRIS body: "Pay with any QRIS wallet — scan, pay, done. No cash? No problem."

| Option | Rationale |
| --- | --- |
| **Bayar dengan dompet digital apa pun — pindai, bayar, selesai. Tanpa uang tunai? Tidak masalah.** | "Dompet digital" is the standard term for e-wallets; "pindai" is the KBBI word for scan; rhythm mirrors the original exactly. |
| Bisa dibayar lewat aplikasi QRIS apa pun — pindai, bayar, beres. Tidak ada uang tunai? Tetap bisa. | Friendly, but "beres" is colloquial and "lewat" slightly loose for premium copy. |
| Scan, bayar, selesai — semua dompet digital bisa. Tidak bawa cash? Santai saja. | Uses English "scan"/"cash" and slang "santai" — off-register. |

**RECOMMENDED:** Bayar dengan dompet digital apa pun — pindai, bayar, selesai. Tanpa uang tunai? Tidak masalah.

---

### 16. Reservation band heading: "Group reservations"

| Option | Rationale |
| --- | --- |
| **Reservasi Rombongan** | "Rombongan" is the natural Indonesian word for a party of guests; warm and precise. |
| Pesan untuk Rombongan | Verb-led and friendly, but a heading reads better as a noun. |
| Reservasi Grup | "Grup" is absorbed but feels corporate next to "rombongan". |

**RECOMMENDED:** Reservasi Rombongan

---

### 17. Reservation body: "For tables of six or more, give us a call at least a day ahead and we will have the corner table ready."

| Option | Rationale |
| --- | --- |
| **Untuk enam orang atau lebih, cukup telepon sehari sebelumnya — meja pojok akan kami siapkan untuk Anda.** | Keeps the em-dash rhythm, "cukup telepon" is gently unhurried, "meja pojok" is the warm everyday word for corner table. |
| Rombongan enam orang atau lebih? Hubungi kami minimal sehari sebelumnya dan meja pojok sudah kami siapkan. | Inviting, but two clauses stack "rombongan" after the heading used it — repetitive. |
| Untuk meja enam orang ke atas, telepon kami H-1 dan kami siapkan meja sudut. | "H-1" is office jargon and "meja sudut" sounds geometric; drops the warmth. |

Note: "meja pojok" (everyday) over "meja sudut" (formal/geometric).

**RECOMMENDED:** Untuk enam orang atau lebih, cukup telepon sehari sebelumnya — meja pojok akan kami siapkan untuk Anda.

---

### 18. WhatsApp pre-filled message (query string on wa.me links): "Hi Coffee Shop, I would like to order a coffee."

| Option | Rationale |
| --- | --- |
| **Halo Coffee Shop, saya ingin memesan kopi.** | Mirrors the original's structure and politeness; "memesan" is the natural verb, "saya" is the right register. |
| Halo, saya mau pesan kopi. | Shorter and friendly ("mau" is proper, not slang), but skips the shop name and loses a touch of warmth. |

**RECOMMENDED:** Halo Coffee Shop, saya ingin memesan kopi.

---

## Key decisions at a glance

- "Seduh/diseduh" everywhere (never "seduhan" as a noun here) — the slow-brew heart of the brand.
- "Rombongan" over "grup"; "meja pojok" over "meja sudut"; "pindai" over "scan"; "dompet digital" over "e-wallet".
- "Anda" (polite) throughout; no "kamu", no "ngobrol", no "beres", no "cash".
- Day labels and hours stay em-dash formatted (`Senin — Jumat`), matching `config/shop.php` style.
