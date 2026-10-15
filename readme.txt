=== RAR Admin Studio ===
Contributors: ruhulaminrevens
Tags: admin theme, login page, dashboard, woocommerce, white label
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 1.0.0
License: GPLv2 or later

Branded sign-in page, a modern wp-admin design, a role-aware Command Center dashboard, per-role menu cleanup and sign-in protection for a WooCommerce store.

== Description ==

* Sign-in page (wp-login.php): brand panel with logo, headline and an optional photo; clean form; Caps Lock hint; works with password reset and other sign-in plugins.
* wp-admin design: side menu, top bar, buttons, fields, tables, cards, notices, order-status and stock labels. Self-hosted fonts with Bangla support (Hind Siliguri).
* Command Center on the dashboard: booked sales today / this month / last 7 days (BDT in lakh grouping), orders to handle by status, latest orders, stock watch, best sellers, 14-day chart. Each role sees only what its permissions allow.
* Roles & menus: hide menu items per role, optionally refuse hidden screens opened by a direct link, choose a landing page per role after sign-in.
* Security: pauses sign-in after repeated wrong passwords per network + username and per network; one generic error message; last sign-in recorded per user.
* HPOS (custom order tables) and legacy order storage are both supported.

Settings: Settings → Admin Studio.

Emergency switch: add `define( 'RAR_AS_DISABLE', true );` to wp-config.php to turn off every visual change and the sign-in limiter without deactivating the plugin.

== Changelog ==

= 1.0.0 =
* First release.
