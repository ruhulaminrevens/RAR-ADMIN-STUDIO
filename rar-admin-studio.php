<?php
/**
 * Plugin Name:       RAR Admin Studio
 * Plugin URI:        https://nabiad.com/
 * Description:       Branded sign-in page, a modern wp-admin design, a role-aware Command Center dashboard, menu cleanup per role and sign-in protection for WooCommerce stores.
 * Version:           1.0.0
 * Author:            Ruhul Amin Revens
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 * Text Domain:       rar-admin-studio
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Emergency switch: add  define( 'RAR_AS_DISABLE', true );  to wp-config.php
 * to turn off every visual change and the sign-in limiter without deactivating.
 */

defined( 'ABSPATH' ) || exit;

define( 'RAR_AS_VERSION', '1.0.0' );
define( 'RAR_AS_FILE', __FILE__ );
define( 'RAR_AS_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAR_AS_URL', plugin_dir_url( __FILE__ ) );

require_once RAR_AS_DIR . 'includes/class-settings.php';
require_once RAR_AS_DIR . 'includes/class-helpers.php';
require_once RAR_AS_DIR . 'includes/class-guard.php';
require_once RAR_AS_DIR . 'includes/class-login.php';
require_once RAR_AS_DIR . 'includes/class-skin.php';
require_once RAR_AS_DIR . 'includes/class-menus.php';
require_once RAR_AS_DIR . 'includes/class-metrics.php';
require_once RAR_AS_DIR . 'includes/class-dashboard.php';
require_once RAR_AS_DIR . 'includes/class-admin-page.php';

// Declare HPOS (custom order tables) compatibility.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RAR_AS_FILE, true );
		}
	}
);

register_activation_hook( __FILE__, array( 'RAR_AS_Guard', 'install' ) );
register_deactivation_hook( __FILE__, array( 'RAR_AS_Guard', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		RAR_AS_Guard::maybe_upgrade();
		RAR_AS_Admin_Page::init(); // Settings stay reachable even in emergency mode.

		if ( defined( 'RAR_AS_DISABLE' ) && RAR_AS_DISABLE ) {
			return;
		}
		RAR_AS_Guard::init();
		RAR_AS_Login::init();
		RAR_AS_Skin::init();
		RAR_AS_Menus::init();
		RAR_AS_Metrics::init();
		RAR_AS_Dashboard::init();
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=rar-admin-studio' ) ) . '">' . esc_html__( 'Settings', 'rar-admin-studio' ) . '</a>' );
		return $links;
	}
);
