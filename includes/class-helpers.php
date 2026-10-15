<?php
/**
 * Shared helpers: colours, brand mark, fonts, money formatting.
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Helpers {

	/**
	 * Mix two hex colours. $weight is the share of $b (0..1).
	 */
	public static function mix( $a, $b, $weight ) {
		$a = self::rgb( $a );
		$b = self::rgb( $b );
		$o = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$o[] = (int) round( $a[ $i ] * ( 1 - $weight ) + $b[ $i ] * $weight );
		}
		return sprintf( '#%02X%02X%02X', $o[0], $o[1], $o[2] );
	}

	public static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			$hex = '000000';
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/** WCAG relative luminance. */
	public static function luminance( $hex ) {
		$c = array_map(
			static function ( $v ) {
				$v = $v / 255;
				return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
			},
			self::rgb( $hex )
		);
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	public static function contrast( $a, $b ) {
		$l1 = self::luminance( $a );
		$l2 = self::luminance( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	/** Pick white or near-black text for a background. */
	public static function on_color( $bg ) {
		return self::contrast( $bg, '#FFFFFF' ) >= 4.5 ? '#FFFFFF' : '#10201A';
	}

	/** Darken a colour until white text reaches 4.5:1 (keeps buttons readable whatever is chosen). */
	public static function readable_primary( $hex ) {
		$c = $hex;
		for ( $i = 0; $i < 12 && self::contrast( $c, '#FFFFFF' ) < 4.5; $i++ ) {
			$c = self::mix( $c, '#000000', 0.08 );
		}
		return $c;
	}

	/**
	 * CSS custom properties derived from the three brand colours.
	 */
	public static function css_vars() {
		$primary = self::readable_primary( RAR_AS_Settings::get( 'color_primary' ) );
		$side    = RAR_AS_Settings::get( 'color_sidebar' );
		$accent  = RAR_AS_Settings::get( 'color_accent' );
		$side_lt = self::luminance( $side ) > 0.35; // A light sidebar needs dark text.

		$vars = array(
			'--as-primary'      => $primary,
			'--as-primary-700'  => self::mix( $primary, '#000000', 0.18 ),
			'--as-primary-100'  => self::mix( $primary, '#FFFFFF', 0.86 ),
			'--as-primary-50'   => self::mix( $primary, '#FFFFFF', 0.93 ),
			'--as-on-primary'   => self::on_color( $primary ),
			'--as-side'         => $side,
			'--as-side-deep'    => self::mix( $side, '#000000', 0.28 ),
			'--as-side-2'       => self::mix( $side, $side_lt ? '#000000' : '#FFFFFF', 0.06 ),
			'--as-side-hover'   => $side_lt ? 'rgba(0,0,0,.06)' : 'rgba(255,255,255,.07)',
			'--as-side-current' => $side_lt ? 'rgba(0,0,0,.10)' : 'rgba(255,255,255,.11)',
			'--as-side-text'    => $side_lt ? '#1D2B24' : self::mix( $side, '#FFFFFF', 0.86 ),
			'--as-side-muted'   => $side_lt ? '#4E5F56' : self::mix( $side, '#FFFFFF', 0.58 ),
			'--as-side-strong'  => $side_lt ? '#0A1510' : '#FFFFFF',
			'--as-accent'       => $accent,
			'--as-accent-soft'  => self::mix( $accent, '#FFFFFF', 0.82 ),
			'--as-accent-ink'   => self::contrast( $accent, '#FFFFFF' ) >= 3 ? self::mix( $accent, '#000000', 0.35 ) : self::mix( $accent, '#000000', 0.55 ),
			'--as-on-accent'    => self::on_color( $accent ),
			'--as-canvas'       => self::mix( $primary, '#F4F6F5', 0.94 ),
		);
		$css = ':root{';
		foreach ( $vars as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		return $css . '}';
	}

	/**
	 * @font-face rules for the bundled fonts (Archivo, Hind Siliguri for Bangla, Unbounded for figures).
	 */
	public static function font_css() {
		if ( ! RAR_AS_Settings::get( 'use_fonts' ) ) {
			return '';
		}
		$base    = RAR_AS_URL . 'assets/fonts/';
		$latin   = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
		$bengali = 'U+0951-0952,U+0964-0965,U+0980-09FE,U+1CD0-1CF9,U+200C-200D,U+20B9,U+25CC,U+A830-A839';
		$css     = '';
		foreach ( array( 400, 500, 600, 700 ) as $w ) {
			$css .= "@font-face{font-family:'RAR UI';font-style:normal;font-weight:{$w};font-display:swap;src:url('{$base}archivo-latin-{$w}-normal.woff2') format('woff2');unicode-range:{$latin}}";
		}
		foreach ( array( 400 => 400, 500 => 500, 600 => 600, 700 => 600 ) as $w => $file ) {
			$css .= "@font-face{font-family:'RAR UI';font-style:normal;font-weight:{$w};font-display:swap;src:url('{$base}hind-siliguri-bengali-{$file}-normal.woff2') format('woff2');unicode-range:{$bengali}}";
		}
		foreach ( array( 500, 600 ) as $w ) {
			$css .= "@font-face{font-family:'RAR Display';font-style:normal;font-weight:{$w};font-display:swap;src:url('{$base}unbounded-latin-{$w}-normal.woff2') format('woff2');unicode-range:{$latin}}";
		}
		return $css . ":root{--as-font:'RAR UI',-apple-system,BlinkMacSystemFont,'Segoe UI','Nirmala UI',Roboto,'Noto Sans Bengali',sans-serif;--as-font-display:'RAR Display','RAR UI',-apple-system,'Segoe UI',sans-serif}";
	}

	/** Fallback font stacks when bundled fonts are switched off. */
	public static function system_font_css() {
		return ":root{--as-font:-apple-system,BlinkMacSystemFont,'Segoe UI','Nirmala UI',Roboto,'Noto Sans Bengali',sans-serif;--as-font-display:var(--as-font)}";
	}

	/**
	 * Brand mark: uploaded logo, else site icon, else a monogram of the brand initial.
	 *
	 * @param string $context 'login' or 'admin'.
	 */
	public static function brand_mark( $context = 'login' ) {
		$name    = RAR_AS_Settings::brand_name();
		$logo_id = (int) RAR_AS_Settings::get( 'logo_id' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_url( $logo_id, 'medium' );
			if ( $src ) {
				return '<img class="rar-as-mark rar-as-mark--logo" src="' . esc_url( $src ) . '" alt="' . esc_attr( $name ) . '" decoding="async">';
			}
		}
		$icon = get_site_icon_url( 128 );
		if ( $icon ) {
			return '<img class="rar-as-mark rar-as-mark--icon" src="' . esc_url( $icon ) . '" alt="" decoding="async"><span class="rar-as-mark__name">' . esc_html( $name ) . '</span>';
		}
		$initial = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );
		return '<span class="rar-as-mark rar-as-mark--mono" aria-hidden="true">' . esc_html( $initial ) . '</span><span class="rar-as-mark__name">' . esc_html( $name ) . '</span>';
	}

	/**
	 * Money with Bangladesh-style grouping for BDT (1,23,45,678) and WooCommerce rules otherwise.
	 *
	 * @param float $amount   Amount.
	 * @param bool  $decimals Show decimals.
	 */
	public static function money( $amount, $decimals = false ) {
		$amount   = (float) $amount;
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'BDT';
		$symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' ) : '৳';
		$symbol   = trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $symbol ) );
		$neg      = $amount < 0;
		$amount   = abs( $amount );
		$dec      = $decimals ? 2 : 0;
		if ( 'BDT' === $currency || 'INR' === $currency ) {
			$parts = explode( '.', number_format( $amount, $dec, '.', '' ) );
			$whole = $parts[0];
			$frac  = isset( $parts[1] ) ? '.' . $parts[1] : '';
			if ( strlen( $whole ) > 3 ) {
				$last  = substr( $whole, -3 );
				$rest  = substr( $whole, 0, -3 );
				$rest  = preg_replace( '/\B(?=(\d{2})+(?!\d))/', ',', $rest );
				$whole = $rest . ',' . $last;
			}
			$num = $whole . $frac;
		} else {
			$dsep = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.';
			$tsep = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',';
			$num  = number_format( $amount, $dec, $dsep, $tsep );
		}
		return ( $neg ? '−' : '' ) . $symbol . "\u{00A0}" . $num;
	}

	/** Compact number for chart labels: 12.4k / 1.2L (lakh) for BDT. */
	public static function compact( $amount ) {
		$amount   = (float) $amount;
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'BDT';
		if ( 'BDT' === $currency && $amount >= 100000 ) {
			return rtrim( rtrim( number_format( $amount / 100000, 1, '.', '' ), '0' ), '.' ) . 'L';
		}
		if ( $amount >= 1000 ) {
			return rtrim( rtrim( number_format( $amount / 1000, 1, '.', '' ), '0' ), '.' ) . 'k';
		}
		return (string) round( $amount );
	}

	/** Is the current admin user an administrator (manage_options)? */
	public static function is_admin_user() {
		return current_user_can( 'manage_options' );
	}

	/** Orders list URL (HPOS or legacy), optionally filtered by status (without "wc-"). */
	public static function orders_url( $status = '' ) {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		if ( $hpos ) {
			$url = admin_url( 'admin.php?page=wc-orders' );
			return $status ? add_query_arg( 'status', 'wc-' . $status, $url ) : $url;
		}
		$url = admin_url( 'edit.php?post_type=shop_order' );
		return $status ? add_query_arg( 'post_status', 'wc-' . $status, $url ) : $url;
	}

	public static function order_edit_url( $order_id ) {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url( (int) $order_id );
		}
		return admin_url( 'post.php?post=' . (int) $order_id . '&action=edit' );
	}

	public static function new_order_url() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_new_url();
		}
		return admin_url( 'post-new.php?post_type=shop_order' );
	}
}
