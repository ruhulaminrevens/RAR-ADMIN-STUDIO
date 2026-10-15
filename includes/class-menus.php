<?php
/**
 * Per-role menu cleanup. Administrators are never restricted.
 *
 * Hiding a menu is a convenience, not a permission: what a person may do is still decided by
 * their role's capabilities. "Block direct links" additionally refuses the hidden screens.
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Menus {

	const SNAPSHOT = 'rar_as_menu_snapshot';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'apply' ), PHP_INT_MAX - 10 );
		add_action( 'admin_init', array( __CLASS__, 'block' ), 1 );
	}

	/** Roles that can be restricted on the settings screen. */
	public static function editable_roles() {
		$roles = wp_roles()->get_names();
		unset( $roles['administrator'], $roles['customer'], $roles['subscriber'] );
		return $roles;
	}

	/** Slugs hidden for the current user (only slugs hidden for every role the user has). */
	public static function hidden_for_current_user() {
		if ( RAR_AS_Helpers::is_admin_user() ) {
			return array();
		}
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() || empty( $user->roles ) ) {
			return array();
		}
		$map    = (array) RAR_AS_Settings::get( 'menu_hide' );
		$hidden = null;
		foreach ( $user->roles as $role ) {
			$list   = isset( $map[ $role ] ) ? (array) $map[ $role ] : array();
			$hidden = null === $hidden ? $list : array_values( array_intersect( $hidden, $list ) );
		}
		return (array) $hidden;
	}

	public static function apply() {
		// Keep the snapshot fresh while an administrator is on the settings screen.
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( RAR_AS_Helpers::is_admin_user() && isset( $_GET['page'] ) && 'rar-admin-studio' === $_GET['page'] ) {
			self::snapshot();
		}
		foreach ( self::hidden_for_current_user() as $slug ) {
			if ( false !== strpos( $slug, '::' ) ) {
				list( $parent, $child ) = explode( '::', $slug, 2 );
				remove_submenu_page( $parent, $child );
			} else {
				remove_menu_page( $slug );
			}
		}
	}

	/** Store the full admin menu (as an administrator sees it) for the settings screen. */
	public static function snapshot() {
		global $menu, $submenu;
		$items = array();
		foreach ( (array) $menu as $m ) {
			if ( empty( $m[2] ) || ( isset( $m[4] ) && false !== strpos( (string) $m[4], 'wp-menu-separator' ) ) ) {
				continue;
			}
			$slug = RAR_AS_Settings::clean_menu_slug( $m[2] );
			if ( '' === $slug ) {
				continue;
			}
			$children = array();
			if ( ! empty( $submenu[ $m[2] ] ) ) {
				foreach ( $submenu[ $m[2] ] as $s ) {
					$cs = RAR_AS_Settings::clean_menu_slug( isset( $s[2] ) ? $s[2] : '' );
					if ( '' === $cs || $cs === $slug ) {
						continue;
					}
					$children[] = array(
						'slug'  => $slug . '::' . $cs,
						'title' => self::label( isset( $s[0] ) ? $s[0] : $cs ),
					);
				}
			}
			$items[] = array(
				'slug'     => $slug,
				'title'    => self::label( $m[0] ),
				'children' => $children,
			);
		}
		update_option( self::SNAPSHOT, $items, false );
	}

	private static function label( $html ) {
		$html = (string) $html;
		// Remove counters and screen-reader extras, innermost first (WordPress nests them).
		for ( $i = 0; $i < 4; $i++ ) {
			$html = preg_replace( '#<span[^>]*class="[^"]*(?:awaiting-mod|update-plugins|count|pending|menu-counter|plugin-count|screen-reader-text)[^"]*"[^>]*>[^<]*</span>#i', '', $html );
		}
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
		return '' !== $text ? $text : '(untitled)';
	}

	/** Refuse hidden screens opened by direct link, when enabled. */
	public static function block() {
		if ( ! RAR_AS_Settings::get( 'menu_block' ) || wp_doing_ajax() ) {
			return;
		}
		global $pagenow;
		if ( in_array( $pagenow, array( 'index.php', 'profile.php', 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
			return;
		}
		foreach ( self::hidden_for_current_user() as $slug ) {
			$target = false !== strpos( $slug, '::' ) ? substr( $slug, strpos( $slug, '::' ) + 2 ) : $slug;
			if ( self::matches_request( $target, $pagenow ) ) {
				wp_die(
					esc_html__( 'This screen is not available for your role. Ask an administrator if you need it.', 'rar-admin-studio' ),
					esc_html__( 'Not available', 'rar-admin-studio' ),
					array(
						'response'  => 403,
						'back_link' => true,
					)
				);
			}
		}
	}

	/**
	 * Does a menu slug point at the current request?
	 *
	 * @param string $slug    Menu slug.
	 * @param string $pagenow Current admin file.
	 */
	public static function matches_request( $slug, $pagenow ) {
		// phpcs:disable WordPress.Security.NonceVerification
		$get = wp_unslash( $_GET );
		// phpcs:enable
		if ( false === strpos( $slug, '.php' ) ) {
			// Plain page slug, served by admin.php?page=slug.
			return 'admin.php' === $pagenow && isset( $get['page'] ) && $get['page'] === $slug;
		}
		$parts = wp_parse_url( $slug );
		$file  = isset( $parts['path'] ) ? basename( $parts['path'] ) : '';
		if ( $file !== $pagenow ) {
			return false;
		}
		$args = array();
		if ( ! empty( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $args );
		}
		if ( ! $args && in_array( $file, array( 'edit.php', 'post-new.php' ), true ) ) {
			return empty( $get['post_type'] ) || 'post' === $get['post_type'];
		}
		if ( 'admin.php' === $file && empty( $args['page'] ) ) {
			return false;
		}
		foreach ( $args as $k => $v ) {
			if ( ! isset( $get[ $k ] ) || (string) $get[ $k ] !== (string) $v ) {
				return false;
			}
		}
		return true;
	}
}
