# RAR Admin Studio

Branded sign-in page, modern wp-admin design, role-aware **Command Center** dashboard, per-role menu cleanup and sign-in protection for the Nabiad WooCommerce store (nabiad.com).

Version 1.0.0 · WordPress 6.5+ (tested 7.1.3) · WooCommerce 8.0+ (tested 11.2.1) · PHP 7.4+ (tested 8.3) · HPOS on and off.

## Install / update (Hostinger)

1. **Back up first**: hPanel → Files → Backups (or download `wp-content/plugins`) and export the database in phpMyAdmin.
2. WordPress → Plugins → Add Plugin → Upload Plugin → choose `rar-admin-studio-v1.0.0.zip` → Install → Activate.
3. LiteSpeed Cache → Toolbox → Purge All (so the admin bar and sign-in page load the new CSS).
4. Settings → **Admin Studio**:
   - Brand: upload a light/white logo for the dark panel; check the three colours.
   - Roles & menus: press **Suggest** under *Shop manager* and your staff role, review the ticks, save.
   - Security: if the site goes behind Cloudflare, switch *How to read the visitor's network address* to Cloudflare.
5. Open `/wp-login.php` in a private window and sign in as a shop manager to check the result.

**Rollback**: deactivate the plugin (nothing else changes), or add `define( 'RAR_AS_DISABLE', true );` to `wp-config.php` to switch everything off while keeping settings.

## What it changes

| Area | Behaviour |
|---|---|
| Sign-in page | Brand panel (logo, headline, text, optional photo), clean form, Caps Lock hint, "Signing in…" state. Field names, nonces and hooks unchanged, so "Sign in with Google" and password reset keep working. |
| wp-admin | Side menu, top bar, page background, buttons, fields, list tables (sticky header), cards, notices, order-status and stock labels. Block editor, Elementor and Woodmart Theme Settings keep their own UI. Extra exclusions: *Workspace → Excluded screens*. |
| Dashboard | Command Center: booked sales today / month / 7 days, orders to handle, latest orders, stock watch, best sellers (30 days), 14-day chart. Non-admins see only the Command Center (optional). |
| Roles & menus | Hide menu items per role (never for administrators), optional 403 for hidden screens, landing page per role after sign-in. |
| Security | Pause after 5 wrong passwords per network + username (20 per network), 20-minute pause, generic error, last sign-in per user, admin unpause list. |

## Figures on the Command Center

* **Booked sales** = order totals (including shipping and tax as stored by WooCommerce) of orders *created* in the period, excluding `pending`, `failed`, `cancelled`, `refunded`, `checkout-draft`, `trash`; partial refunds created in the period on included orders are deducted. Site time zone (Asia/Dhaka).
* *Today vs yesterday* compares with yesterday up to the same clock time.
* **Orders to handle** = all orders not `completed`, `cancelled`, `refunded`, `failed`, draft or trash (custom statuses such as *Confirmed* are included).
* **Running low** = stock-managed items with stock above 0 and at or below their own low-stock amount, else the store default.
* **Best sellers** = quantity sold and line totals after discounts, excluding tax and shipping, last 30 days, included orders only.
* Cached for 5 minutes; cleared when an order is created, changes status, is refunded or stock changes. *Refresh* recalculates at once.
* These are operational figures, not accounting revenue. WooCommerce Analytics / Tally remain the reference.

Filters for developers: `rar_as_excluded_statuses`, `rar_as_closed_statuses`, `rar_as_ui_excluded_screens`; action `rar_as_login_locked`.

## Files

```
rar-admin-studio.php            bootstrap, HPOS declaration, emergency switch
includes/class-settings.php     defaults and sanitising
includes/class-helpers.php      colours, fonts, BDT money format, order URLs
includes/class-guard.php        sign-in protection (table wp_rar_as_login)
includes/class-login.php        sign-in page and per-role landing page
includes/class-skin.php         wp-admin design, admin bar, footer, Users column
includes/class-menus.php        per-role menu hiding and direct-link refusal
includes/class-metrics.php      Command Center figures (read-only SQL, cached)
includes/class-dashboard.php    Command Center rendering
includes/class-admin-page.php   Settings → Admin Studio
assets/css, assets/js           styles and small scripts (no jQuery on the sign-in page)
assets/fonts                    Archivo, Unbounded, Hind Siliguri (SIL OFL 1.1)
uninstall.php                   removes options, table, user meta, cache
tests/smoke.php                 WP-CLI smoke tests (run on a local/staging copy only)
```

## Tests

`wp eval-file wp-content/plugins/rar-admin-studio/tests/smoke.php` (local or staging only: it changes settings and sign-in counters).
