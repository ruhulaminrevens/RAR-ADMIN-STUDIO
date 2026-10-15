<?php
/**
 * wp-admin design: colours, type, components, admin bar and footer.
 *
 * Two layers, controlled by body classes:
 *   rar-as     → "chrome": admin bar, side menu, page background, fonts (every admin screen)
 *   rar-as-ui  → components inside the page: buttons, fields, tables, cards, notices
 *                (skipped on the block editor and on screens listed in "Excluded screens").
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Skin {

	public static function init() {
		if ( RAR_AS_Settings::get( 'skin_enabled' ) ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 5 );
			add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		}
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 9999 );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ), 99 );
		add_filter( 'update_footer', array( __CLASS__, 'footer_version' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'nags' ) );

		if ( RAR_AS_Settings::get( 'users_column' ) ) {
			add_filter( 'manage_users_columns', array( __CLASS__, 'users_column' ) );
			add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_column_value' ), 10, 3 );
		}
	}

	/** Screens where nothing of ours should load (full-screen builders). */
	public static function is_builder_request() {
		// phpcs:disable WordPress.Security.NonceVerification
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable
		return in_array( $action, array( 'elementor', 'elementor_library' ), true );
	}

	public static function ui_enabled_for_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return true;
		}
		if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return false;
		}
		$excluded = array_filter( array_map( 'trim', explode( "\n", (string) RAR_AS_Settings::get( 'excluded_screens' ) ) ) );
		/**
		 * Screens that keep their own component styling. Woodmart's Theme Settings has its own design system.
		 *
		 * @param string[] $excluded Screen IDs.
		 */
		$excluded = (array) apply_filters( 'rar_as_ui_excluded_screens', array_merge( $excluded, array( 'toplevel_page_xtemos_options' ) ) );
		return ! in_array( $screen->id, $excluded, true );
	}

	public static function assets() {
		if ( self::is_builder_request() ) {
			return;
		}
		wp_enqueue_style( 'rar-as-admin', RAR_AS_URL . 'assets/css/admin.css', array(), RAR_AS_VERSION );
		$fonts = RAR_AS_Settings::get( 'use_fonts' ) ? RAR_AS_Helpers::font_css() : RAR_AS_Helpers::system_font_css();
		wp_add_inline_style( 'rar-as-admin', $fonts . RAR_AS_Helpers::css_vars() );
	}

	public static function body_class( $classes ) {
		if ( self::is_builder_request() ) {
			return $classes;
		}
		$classes .= ' rar-as';
		if ( self::ui_enabled_for_screen() ) {
			$classes .= ' rar-as-ui';
		}
		return $classes;
	}

	/**
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( RAR_AS_Settings::get( 'adminbar_clean' ) ) {
			$bar->remove_node( 'wp-logo' );
			if ( ! RAR_AS_Helpers::is_admin_user() ) {
				foreach ( array( 'comments', 'new-content', 'updates', 'customize', 'search' ) as $id ) {
					$bar->remove_node( $id );
				}
			}
		}
		if ( RAR_AS_Settings::get( 'replace_howdy' ) ) {
			$node = $bar->get_node( 'my-account' );
			if ( $node && is_string( $node->title ) ) {
				/* translators: %s: user display name (WordPress core string). */
				$howdy       = sprintf( __( 'Howdy, %s' ), '' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				$node->title = str_replace( $howdy, '', $node->title );
				if ( ! empty( $node->meta['menu_title'] ) && is_string( $node->meta['menu_title'] ) ) {
					$node->meta['menu_title'] = str_replace( $howdy, '', $node->meta['menu_title'] );
				}
				$bar->add_node( (array) $node );
			}
		}
	}

	public static function footer_text( $text ) {
		$custom = trim( RAR_AS_Settings::tokens( RAR_AS_Settings::get( 'footer_text' ) ) );
		if ( '' === $custom ) {
			return $text;
		}
		return '<span id="footer-thankyou" class="rar-as-footer">' . esc_html( $custom ) . '</span>';
	}

	public static function footer_version( $text ) {
		return RAR_AS_Helpers::is_admin_user() ? $text : '';
	}

	/** Hide WordPress update/maintenance nags for people who cannot act on them. */
	public static function nags() {
		if ( RAR_AS_Settings::get( 'hide_nags' ) && ! current_user_can( 'update_core' ) ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
			remove_action( 'network_admin_notices', 'update_nag', 3 );
			remove_action( 'admin_notices', 'maintenance_nag', 10 );
		}
	}

	public static function users_column( $cols ) {
		if ( current_user_can( 'list_users' ) ) {
			$cols['rar_as_last_login'] = __( 'Last sign-in', 'rar-admin-studio' );
		}
		return $cols;
	}

	public static function users_column_value( $value, $column, $user_id ) {
		if ( 'rar_as_last_login' !== $column ) {
			return $value;
		}
		$last = get_user_meta( $user_id, 'rar_as_last_login', true );
		if ( ! is_array( $last ) || empty( $last['t'] ) ) {
			return '<span class="rar-as-muted">' . esc_html__( 'Not recorded yet', 'rar-admin-studio' ) . '</span>';
		}
		$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['t'] );
		$ago  = human_time_diff( (int) $last['t'], time() );
		/* translators: %s: time span, e.g. "3 hours" */
		return '<span title="' . esc_attr( $when ) . '">' . esc_html( sprintf( __( '%s ago', 'rar-admin-studio' ), $ago ) ) . '</span>';
	}
}
