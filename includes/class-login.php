<?php
/**
 * Branded sign-in page (wp-login.php) and per-role landing page after sign-in.
 *
 * Only presentation is changed: the WordPress form, its field names, nonces and
 * hooks stay untouched so other sign-in plugins (e.g. "Sign in with Google") keep working.
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Login {

	public static function init() {
		add_filter( 'login_redirect', array( __CLASS__, 'redirect' ), 20, 3 );

		if ( ! RAR_AS_Settings::get( 'login_enabled' ) ) {
			return;
		}
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_filter( 'login_body_class', array( __CLASS__, 'body_class' ), 10, 2 );
		add_action( 'login_header', array( __CLASS__, 'brand_panel' ), 1 );
		add_filter( 'login_message', array( __CLASS__, 'form_heading' ), 1 );
		add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ) );
		add_filter( 'login_headertext', array( 'RAR_AS_Settings', 'brand_name' ) );
		add_action( 'login_head', array( __CLASS__, 'meta' ), 1 );
	}

	public static function meta() {
		$color = RAR_AS_Settings::get( 'color_sidebar' );
		echo '<meta name="theme-color" content="' . esc_attr( $color ) . '">' . "\n";
	}

	public static function assets() {
		wp_enqueue_style( 'rar-as-login', RAR_AS_URL . 'assets/css/login.css', array( 'login' ), RAR_AS_VERSION );
		$fonts = RAR_AS_Settings::get( 'use_fonts' ) ? RAR_AS_Helpers::font_css() : RAR_AS_Helpers::system_font_css();
		wp_add_inline_style( 'rar-as-login', $fonts . RAR_AS_Helpers::css_vars() . self::bg_css() );
		wp_enqueue_script( 'rar-as-login', RAR_AS_URL . 'assets/js/login.js', array(), RAR_AS_VERSION, true );
		wp_localize_script(
			'rar-as-login',
			'rarAsLogin',
			array(
				'caps'    => __( 'Caps Lock is on.', 'rar-admin-studio' ),
				'signing' => __( 'Signing in…', 'rar-admin-studio' ),
			)
		);
	}

	private static function bg_css() {
		$id = (int) RAR_AS_Settings::get( 'login_bg_id' );
		if ( ! $id ) {
			return '';
		}
		$src = wp_get_attachment_image_url( $id, 'large' );
		return $src ? '.rar-as-brand{--as-login-photo:url("' . esc_url_raw( $src ) . '")}' : '';
	}

	public static function body_class( $classes, $action ) {
		$classes[] = 'rar-as-login';
		$classes[] = 'rar-as-action-' . sanitize_html_class( $action );
		if ( RAR_AS_Settings::get( 'login_bg_id' ) ) {
			$classes[] = 'rar-as-has-photo';
		}
		return $classes;
	}

	public static function header_url() {
		return 'none' === RAR_AS_Settings::get( 'login_logo_link' ) ? '' : home_url( '/' );
	}

	/**
	 * Left brand panel. Printed right after <body> opens.
	 */
	public static function brand_panel() {
		$headline = RAR_AS_Settings::tokens( RAR_AS_Settings::get( 'login_headline' ) );
		$text     = RAR_AS_Settings::tokens( RAR_AS_Settings::get( 'login_text' ) );
		$footer   = RAR_AS_Settings::tokens( RAR_AS_Settings::get( 'login_footer' ) );
		$link     = 'none' === RAR_AS_Settings::get( 'login_logo_link' ) ? '' : home_url( '/' );
		?>
		<aside class="rar-as-brand" aria-label="<?php echo esc_attr( RAR_AS_Settings::brand_name() ); ?>">
			<div class="rar-as-brand__top">
				<?php if ( $link ) : ?>
					<a class="rar-as-brand__home" href="<?php echo esc_url( $link ); ?>"><?php echo RAR_AS_Helpers::brand_mark( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></a>
				<?php else : ?>
					<span class="rar-as-brand__home"><?php echo RAR_AS_Helpers::brand_mark( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<?php endif; ?>
			</div>
			<div class="rar-as-brand__body">
				<?php if ( '' !== $headline ) : ?>
					<p class="rar-as-brand__headline"><?php echo esc_html( $headline ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $text ) : ?>
					<p class="rar-as-brand__text"><?php echo nl2br( esc_html( $text ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
				<?php endif; ?>
			</div>
			<?php self::scent_art(); ?>
			<div class="rar-as-brand__foot">
				<?php if ( '' !== $footer ) : ?>
					<span><?php echo esc_html( $footer ); ?></span>
				<?php endif; ?>
				<?php if ( RAR_AS_Settings::get( 'guard_enabled' ) ) : ?>
					<span class="rar-as-brand__secure">
						<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"/></svg>
						<?php esc_html_e( 'Protected sign-in', 'rar-admin-studio' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</aside>
		<?php
	}

	/**
	 * Fine rising lines, like the trail of a fragrance. Decorative only.
	 */
	private static function scent_art() {
		?>
		<svg class="rar-as-scent" viewBox="0 0 600 520" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">
			<g fill="none" stroke-linecap="round">
				<path class="rar-as-scent__l1" d="M120 540 C 150 420, 60 360, 140 280 S 260 160, 200 40" />
				<path class="rar-as-scent__l2" d="M230 540 C 270 440, 190 380, 260 300 S 380 200, 320 70" />
				<path class="rar-as-scent__l3" d="M340 540 C 390 450, 300 390, 380 310 S 520 210, 470 90" />
				<path class="rar-as-scent__l4" d="M450 540 C 480 470, 420 420, 470 350 S 560 280, 540 180" />
			</g>
			<g class="rar-as-scent__dots">
				<circle cx="198" cy="64" r="2.4" />
				<circle cx="318" cy="96" r="1.8" />
				<circle cx="468" cy="116" r="2.1" />
				<circle cx="538" cy="204" r="1.6" />
				<circle cx="150" cy="250" r="1.4" />
			</g>
		</svg>
		<?php
	}

	/**
	 * Heading above the form, per screen. Keeps WordPress's own message.
	 *
	 * @param string $message Existing message HTML.
	 */
	public static function form_heading( $message ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['checkemail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$action = 'checkemail';
		}
		switch ( $action ) {
			case 'lostpassword':
			case 'retrievepassword':
				$title = __( 'Reset your password', 'rar-admin-studio' );
				$sub   = '';
				break;
			case 'rp':
			case 'resetpass':
				$title = __( 'Choose a new password', 'rar-admin-studio' );
				$sub   = '';
				break;
			case 'register':
				$title = __( 'Create an account', 'rar-admin-studio' );
				$sub   = '';
				break;
			case 'checkemail':
				$title = __( 'Check your e-mail', 'rar-admin-studio' );
				$sub   = '';
				break;
			case 'confirm_admin_email':
				$title = '';
				$sub   = '';
				break;
			default:
				$title = __( 'Sign in', 'rar-admin-studio' );
				/* translators: %s: brand name */
				$sub = sprintf( __( 'Welcome back to %s.', 'rar-admin-studio' ), RAR_AS_Settings::brand_name() );
		}
		if ( '' === $title ) {
			return $message;
		}
		$head = '<div class="rar-as-form-head"><h2>' . esc_html( $title ) . '</h2>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div>';
		return $head . $message;
	}

	/**
	 * Landing page per role. A redirect_to chosen by the request (other than the default admin URL) wins.
	 *
	 * @param string           $redirect_to Destination.
	 * @param string           $requested   Requested destination.
	 * @param WP_User|WP_Error $user        User.
	 */
	public static function redirect( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User ) {
			return $redirect_to;
		}
		$map = (array) RAR_AS_Settings::get( 'login_redirect' );
		if ( ! $map ) {
			return $redirect_to;
		}
		$default = admin_url();
		if ( '' !== (string) $requested && untrailingslashit( $requested ) !== untrailingslashit( $default ) ) {
			return $redirect_to;
		}
		foreach ( (array) $user->roles as $role ) {
			if ( ! empty( $map[ $role ] ) ) {
				$path = RAR_AS_Settings::clean_admin_path( $map[ $role ] );
				if ( '' !== $path ) {
					return admin_url( $path );
				}
			}
		}
		return $redirect_to;
	}
}
