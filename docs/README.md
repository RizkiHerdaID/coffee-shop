# Documentation — Coffee Shop

Reference documentation for the coffee-shop codebase (Laravel 13 + Filament 5 +
Tailwind 4). See `README.md` at the repo root for setup; `AGENTS.md` for agent
conventions and environment quirks.

## Index

| Doc | Covers |
| --- | --- |
| [`website.md`](website.md) | Public site: landing pages, SEO/structured data, QR table menu, WhatsApp pickup ordering, conversion features |
| [`pos.md`](pos.md) | POS: order capture, payments, shifts/Z-report, receipts, refunds & voids, planned gateway integration |
| [`owner-tools.md`](owner-tools.md) | Owner tooling: dashboard widgets, inventory/stock, suppliers, purchase orders, expenses, cash register, recipes/COGS, low-stock alerts, AI copy, summary email |
| [`i18n/README.md`](i18n/README.md) | Localization system (ID/EN switcher) + copy decisions |
| [`i18n/home.md`](i18n/home.md) | Home page final Indonesian copy |
| [`i18n/menu-contact.md`](i18n/menu-contact.md) | Menu & contact page final Indonesian copy |
| [`i18n/layout-meta.md`](i18n/layout-meta.md) | Layout/nav/footer/meta final Indonesian copy + switcher UX |
| [`roadmap.md`](roadmap.md) | Future work prioritized (P1–P4) — mirror of the Vikunja board backlog, plus recently completed items |

## How to update these docs

- Docs describe the CURRENT state of `main`. When a feature lands, update the
  matching doc in the same change.
- Claims about implemented behavior must be verified against the code (`routes/`,
  `app/`, `config/`) — do not copy stale statements from older research files.
- Code pointers (`file:line`) are preferred over prose descriptions.
- Copy strings are documented in `docs/i18n/`; the `lang/{id,en}/*.php` files are the
  source of truth — never hardcode strings in Blade (see `AGENTS.md` conventions).
- `roadmap.md` mirrors the Vikunja board backlog (project "Coffee Shop", id 6).
  When a card lands, move it to the "Completed since this backlog was captured"
  section with the commit(s) that closed it, and update the matching feature doc.

## History

These docs were reformatted from earlier research/session markdowns
(`research-website.md`, `research-pos.md`, `research-owner-ai.md` and the i18n copy
proposals) on 2026-08-02, after the researched features (POS, QR menu, stock,
expenses, etc.) had been implemented on `main`.
