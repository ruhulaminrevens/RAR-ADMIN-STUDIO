# Changelog

## 1.0.0 — 2026-10-15
First release.
- Branded sign-in page (brand panel, logo, headline, optional photo, Caps Lock hint, busy state).
- wp-admin design (side menu, top bar, components, order-status and stock labels), bundled Archivo / Unbounded / Hind Siliguri fonts.
- Command Center dashboard: booked sales today / month / 7 days, orders to handle, latest orders, stock watch, best sellers, 14-day chart; role-aware; HPOS and legacy storage.
- Roles & menus: hide menu items per role, optional refusal of hidden screens, landing page per role.
- Sign-in protection: per network + username and per network pauses, generic errors, last sign-in record, admin unpause list.
- Settings → Admin Studio with six sections; emergency switch `RAR_AS_DISABLE`.
- Verified: 46 WP-CLI smoke tests and 33 HTTP tests, each with HPOS on and off (WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.3, SQLite test database); screens checked at 360/390/768/1366/1920 px.
