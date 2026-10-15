<?php
/**
 * Settings storage, defaults and sanitising.
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Settings {

	const OPTION = 'rar_as_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		return array(
			// Brand.
			'brand_name'       => '',
			'logo_id'          => 0,
			'color_primary'    => '#0E7A4E',
			'color_sidebar'    => '#0B2E22',
			'color_accent'     => '#C8962E',
			'use_fonts'        => 1,

			// Sign-in page.
			'login_enabled'    => 1,
			'login_headline'   => 'Nabiad workspace',
			'login_text'       => 'Orders, stock and products for the Nabiad team. Sign in with the account your administrator gave you.',
			'login_bg_id'      => 0,
			'login_footer'     => '© {year} Nabiad Distribution Limited',
			'login_logo_link'  => 'home',

			// Workspace (wp-admin).
			'skin_enabled'     => 1,
			'excluded_screens' => '',
			'adminbar_clean'   => 1,
			'replace_howdy'    => 1,
			'hide_nags'        => 1,
			'footer_text'      => '{site} admin panel',
			'users_column'     => 1,

			// Dashboard.
			'cc_enabled'       => 1,
			'clean_core'       => 1,
			'focus_nonadmin'   => 1,
			'custom_links'     => '',

			// Roles and menus.
			'menu_hide'        => array(),
			'menu_block'       => 0,
			'login_redirect'   => array(),

			// Security.
			'guard_enabled'    => 1,
			'guard_max'        => 5,
			'guard_window'     => 15,
			'guard_lock'       => 20,
			'guard_ip_max'     => 20,
			'ip_source'        => 'remote',
			'generic_errors'   => 1,
			'record_login'     => 1,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	public static function flush() {
		self::$cache = null;
	}

	public static function brand_name() {
		$name = trim( (string) self::get( 'brand_name' ) );
		return '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Replace {year} and {site} tokens in short texts.
	 */
	public static function tokens( $text ) {
		return strtr(
			(string) $text,
			array(
				'{year}' => wp_date( 'Y' ),
				'{site}' => self::brand_name(),
			)
		);
	}

	/**
	 * Sanitise the whole settings array posted from the settings screen.
	 *
	 * @param mixed $in Raw input.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$d   = self::defaults();
		$in  = is_array( $in ) ? wp_unslash( $in ) : array();
		$out = array();

		$out['brand_name']     = isset( $in['brand_name'] ) ? sanitize_text_field( $in['brand_name'] ) : '';
		$out['logo_id']        = isset( $in['logo_id'] ) ? absint( $in['logo_id'] ) : 0;
		$out['login_bg_id']    = isset( $in['login_bg_id'] ) ? absint( $in['login_bg_id'] ) : 0;
		foreach ( array( 'color_primary', 'color_sidebar', 'color_accent' ) as $k ) {
			$c         = isset( $in[ $k ] ) ? sanitize_hex_color( $in[ $k ] ) : '';
			$out[ $k ] = $c ? strtoupper( $c ) : $d[ $k ];
		}

		$checkboxes = array( 'use_fonts', 'login_enabled', 'skin_enabled', 'adminbar_clean', 'replace_howdy', 'hide_nags', 'users_column', 'cc_enabled', 'clean_core', 'focus_nonadmin', 'menu_block', 'guard_enabled', 'generic_errors', 'record_login' );
		foreach ( $checkboxes as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}

		$out['login_headline'] = isset( $in['login_headline'] ) ? sanitize_text_field( $in['login_headline'] ) : '';
		$out['login_text']     = isset( $in['login_text'] ) ? sanitize_textarea_field( $in['login_text'] ) : '';
		$out['login_footer']   = isset( $in['login_footer'] ) ? sanitize_text_field( $in['login_footer'] ) : '';
		$out['footer_text']    = isset( $in['footer_text'] ) ? sanitize_text_field( $in['footer_text'] ) : '';
		$out['login_logo_link'] = ( isset( $in['login_logo_link'] ) && 'none' === $in['login_logo_link'] ) ? 'none' : 'home';

		// Screen IDs: one per line, letters, digits, dash, underscore only.
		$screens = array();
		foreach ( preg_split( '/[\r\n,]+/', isset( $in['excluded_screens'] ) ? (string) $in['excluded_screens'] : '' ) as $s ) {
			$s = strtolower( trim( $s ) );
			if ( '' !== $s && preg_match( '/^[a-z0-9_\-]{1,100}$/', $s ) ) {
				$screens[] = $s;
			}
		}
		$out['excluded_screens'] = implode( "\n", array_slice( array_unique( $screens ), 0, 50 ) );

		$out['custom_links'] = self::sanitize_links( isset( $in['custom_links'] ) ? (string) $in['custom_links'] : '' );

		// Menu hiding per role: role => list of menu slugs. Administrators are never restricted.
		$roles             = wp_roles()->get_names();
		$out['menu_hide']  = array();
		if ( isset( $in['menu_hide'] ) && is_array( $in['menu_hide'] ) ) {
			foreach ( $in['menu_hide'] as $role => $slugs ) {
				$role = sanitize_key( $role );
				if ( 'administrator' === $role || ! isset( $roles[ $role ] ) || ! is_array( $slugs ) ) {
					continue;
				}
				$clean = array();
				foreach ( $slugs as $slug ) {
					$slug = self::clean_menu_slug( $slug );
					if ( '' !== $slug ) {
						$clean[] = $slug;
					}
				}
				if ( $clean ) {
					$out['menu_hide'][ $role ] = array_values( array_unique( array_slice( $clean, 0, 200 ) ) );
				}
			}
		}

		// After sign-in landing page per role (admin-relative path only).
		$out['login_redirect'] = array();
		if ( isset( $in['login_redirect'] ) && is_array( $in['login_redirect'] ) ) {
			foreach ( $in['login_redirect'] as $role => $path ) {
				$role = sanitize_key( $role );
				$path = self::clean_admin_path( $path );
				if ( isset( $roles[ $role ] ) && '' !== $path ) {
					$out['login_redirect'][ $role ] = $path;
				}
			}
		}

		$out['guard_max']    = self::clamp( $in, 'guard_max', 3, 20, $d['guard_max'] );
		$out['guard_window'] = self::clamp( $in, 'guard_window', 5, 120, $d['guard_window'] );
		$out['guard_lock']   = self::clamp( $in, 'guard_lock', 5, 1440, $d['guard_lock'] );
		$out['guard_ip_max'] = self::clamp( $in, 'guard_ip_max', 10, 200, $d['guard_ip_max'] );
		if ( $out['guard_ip_max'] < $out['guard_max'] ) {
			$out['guard_ip_max'] = $out['guard_max'];
		}
		$out['ip_source'] = ( isset( $in['ip_source'] ) && 'cloudflare' === $in['ip_source'] ) ? 'cloudflare' : 'remote';

		self::$cache = null;
		RAR_AS_Metrics::flush();
		return $out;
	}

	private static function clamp( $in, $key, $min, $max, $default ) {
		if ( ! isset( $in[ $key ] ) || '' === $in[ $key ] ) {
			return $default;
		}
		return max( $min, min( $max, (int) $in[ $key ] ) );
	}

	/**
	 * Menu slugs come from the admin menu: "index.php", "edit.php?post_type=x", "woocommerce", "admin.php?page=x".
	 */
	public static function clean_menu_slug( $slug ) {
		$slug = trim( wp_strip_all_tags( (string) $slug ) );
		if ( strlen( $slug ) > 300 || ! preg_match( '#^[A-Za-z0-9_\-\.\?=&/%\+:~\#]+$#', $slug ) ) {
			return '';
		}
		return $slug;
	}

	/**
	 * A path inside wp-admin such as "edit.php?post_type=product". No scheme, host, or "..".
	 */
	public static function clean_admin_path( $path ) {
		$path = ltrim( trim( (string) $path ), '/' );
		if ( '' === $path || strlen( $path ) > 200 || false !== strpos( $path, '..' ) || false !== strpos( $path, '//' ) || false !== strpos( $path, ':' ) ) {
			return '';
		}
		if ( 0 === strpos( $path, 'wp-admin/' ) ) {
			$path = substr( $path, 9 );
		}
		return preg_match( '#^[A-Za-z0-9_\-\.\?=&/%\+]+$#', $path ) ? $path : '';
	}

	/**
	 * Custom dashboard links: "Label | target" per line. Target is a wp-admin path, a site path
	 * starting with "/", or an http(s) URL.
	 */
	public static function sanitize_links( $raw ) {
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( count( $parts ) < 2 || '' === $parts[0] || '' === $parts[1] ) {
				continue;
			}
			$label = sanitize_text_field( $parts[0] );
			$url   = self::link_target( $parts[1] );
			if ( '' !== $label && '' !== $url ) {
				$lines[] = mb_substr( $label, 0, 40 ) . ' | ' . $parts[1];
			}
			if ( count( $lines ) >= 8 ) {
				break;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Resolve a custom link target to a full URL, or '' when not allowed.
	 */
	public static function link_target( $target ) {
		$target = trim( (string) $target );
		if ( 0 === strpos( $target, '//' ) || 0 === strpos( $target, '\\' ) ) {
			return ''; // Protocol-relative: could point off-site.
		}
		if ( preg_match( '#^https?://#i', $target ) ) {
			return esc_url_raw( $target, array( 'http', 'https' ) );
		}
		if ( 0 === strpos( $target, '/' ) && 0 !== strpos( $target, '//' ) ) {
			return esc_url_raw( home_url( $target ) );
		}
		$path = self::clean_admin_path( $target );
		return '' === $path ? '' : esc_url_raw( admin_url( $path ) );
	}

	/**
	 * Parsed custom links: array of [label, url].
	 */
	public static function custom_links() {
		$links = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) self::get( 'custom_links' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( count( $parts ) === 2 ) {
				$url = self::link_target( $parts[1] );
				if ( '' !== $url ) {
					$links[] = array( $parts[0], $url );
				}
			}
		}
		return $links;
	}
}
