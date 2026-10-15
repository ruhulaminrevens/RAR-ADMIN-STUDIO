<?php
/**
 * Settings → Admin Studio.
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Admin_Page {

	const SLUG = 'rar-admin-studio';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_rar_as_unlock', array( __CLASS__, 'post_unlock' ) );
		add_action( 'admin_post_rar_as_clear', array( __CLASS__, 'post_clear' ) );
		add_action( 'admin_post_rar_as_reset', array( __CLASS__, 'post_reset' ) );
	}

	public static function menu() {
		$hook = add_options_page(
			__( 'Admin Studio', 'rar-admin-studio' ),
			__( 'Admin Studio', 'rar-admin-studio' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
		add_action( 'admin_print_scripts-' . $hook, array( __CLASS__, 'assets' ) );
	}

	public static function register() {
		register_setting(
			'rar_as',
			RAR_AS_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'RAR_AS_Settings', 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	public static function assets() {
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'rar-as-settings', RAR_AS_URL . 'assets/js/settings.js', array( 'jquery', 'wp-color-picker' ), RAR_AS_VERSION, true );
		wp_localize_script(
			'rar-as-settings',
			'rarAsSettings',
			array(
				'choose' => __( 'Choose image', 'rar-admin-studio' ),
				'use'    => __( 'Use this image', 'rar-admin-studio' ),
				'keep'   => array( 'index.php', 'profile.php', 'upload.php', 'woocommerce', 'edit.php?post_type=product', 'wc-orders', 'shop_order', 'rar-', 'rar_', 'courier', 'stock-order', 'stock_order', 'order-workflow', 'order_workflow', 'invoice', 'advance-pay', 'advance_pay' ),
			)
		);
		if ( ! RAR_AS_Settings::get( 'skin_enabled' ) || ( defined( 'RAR_AS_DISABLE' ) && RAR_AS_DISABLE ) ) {
			// The page still needs its own layout styles when the skin is off.
			wp_enqueue_style( 'rar-as-admin', RAR_AS_URL . 'assets/css/admin.css', array(), RAR_AS_VERSION );
			wp_add_inline_style( 'rar-as-admin', RAR_AS_Helpers::system_font_css() . RAR_AS_Helpers::css_vars() );
		}
	}

	/* ------------------------------------------------------------ Actions */

	private static function back( $tab, $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab, 'rar_as_msg' => $msg ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function post_unlock() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rar-admin-studio' ), 403 );
		}
		check_admin_referer( 'rar_as_unlock' );
		RAR_AS_Guard::unlock( isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '' );
		self::back( 'security', 'unlocked' );
	}

	public static function post_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rar-admin-studio' ), 403 );
		}
		check_admin_referer( 'rar_as_clear' );
		RAR_AS_Guard::clear_all();
		self::back( 'security', 'cleared' );
	}

	public static function post_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rar-admin-studio' ), 403 );
		}
		check_admin_referer( 'rar_as_reset' );
		delete_option( RAR_AS_Settings::OPTION );
		RAR_AS_Settings::flush();
		self::back( 'brand', 'reset' );
	}

	/* ------------------------------------------------------------ Fields */

	private static function name( $key ) {
		return RAR_AS_Settings::OPTION . '[' . $key . ']';
	}

	private static function check( $key, $label, $help = '' ) {
		printf(
			'<label class="rar-as-check"><input type="checkbox" name="%1$s" value="1" %2$s><span><b>%3$s</b>%4$s</span></label>',
			esc_attr( self::name( $key ) ),
			checked( 1, (int) RAR_AS_Settings::get( $key ), false ),
			esc_html( $label ),
			$help ? '<small>' . esc_html( $help ) . '</small>' : ''
		);
	}

	private static function text( $key, $label, $help = '', $type = 'text', $attrs = '' ) {
		$id = 'rar-as-' . $key;
		printf(
			'<div class="rar-as-field"><label for="%1$s">%2$s</label><input type="%3$s" id="%1$s" name="%4$s" value="%5$s" class="regular-text" %6$s>%7$s</div>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( self::name( $key ) ),
			esc_attr( (string) RAR_AS_Settings::get( $key ) ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput -- static attributes.
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);
	}

	private static function textarea( $key, $label, $help = '', $rows = 3 ) {
		$id = 'rar-as-' . $key;
		printf(
			'<div class="rar-as-field"><label for="%1$s">%2$s</label><textarea id="%1$s" name="%3$s" rows="%4$d" class="large-text">%5$s</textarea>%6$s</div>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( self::name( $key ) ),
			(int) $rows,
			esc_textarea( (string) RAR_AS_Settings::get( $key ) ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);
	}

	private static function color( $key, $label, $help ) {
		printf(
			'<div class="rar-as-field rar-as-field--color"><label for="rar-as-%1$s">%2$s</label><input type="text" id="rar-as-%1$s" name="%3$s" value="%4$s" class="rar-as-color" data-var="%1$s" data-default-color="%5$s"><p class="description">%6$s</p></div>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::name( $key ) ),
			esc_attr( RAR_AS_Settings::get( $key ) ),
			esc_attr( RAR_AS_Settings::defaults()[ $key ] ),
			esc_html( $help )
		);
	}

	private static function image( $key, $label, $help ) {
		$id  = (int) RAR_AS_Settings::get( $key );
		$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		printf(
			'<div class="rar-as-field rar-as-image" data-key="%1$s"><span class="rar-as-label">%2$s</span><div class="rar-as-image__row"><span class="rar-as-image__preview">%3$s</span><input type="hidden" name="%4$s" value="%5$d"><button type="button" class="button rar-as-image__pick">%6$s</button><button type="button" class="button-link rar-as-image__clear"%7$s>%8$s</button></div><p class="description">%9$s</p></div>',
			esc_attr( $key ),
			esc_html( $label ),
			$src ? '<img src="' . esc_url( $src ) . '" alt="">' : '',
			esc_attr( self::name( $key ) ),
			(int) $id,
			esc_html__( 'Choose image', 'rar-admin-studio' ),
			$id ? '' : ' hidden',
			esc_html__( 'Remove', 'rar-admin-studio' ),
			esc_html( $help )
		);
	}

	/* ------------------------------------------------------------ Page */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'brand';
		$msg = isset( $_GET['rar_as_msg'] ) ? sanitize_key( wp_unslash( $_GET['rar_as_msg'] ) ) : '';
		// phpcs:enable
		$tabs = array(
			'brand'     => __( 'Brand', 'rar-admin-studio' ),
			'login'     => __( 'Sign-in page', 'rar-admin-studio' ),
			'workspace' => __( 'Workspace', 'rar-admin-studio' ),
			'dashboard' => __( 'Dashboard', 'rar-admin-studio' ),
			'roles'     => __( 'Roles & menus', 'rar-admin-studio' ),
			'security'  => __( 'Security', 'rar-admin-studio' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'brand';
		}
		$notices = array(
			'unlocked' => __( 'Sign-in unpaused for that entry.', 'rar-admin-studio' ),
			'cleared'  => __( 'All sign-in counters cleared.', 'rar-admin-studio' ),
			'reset'    => __( 'Settings reset to the defaults.', 'rar-admin-studio' ),
		);
		?>
		<div class="wrap rar-as-settings">
			<header class="rar-as-settings__head">
				<h1><?php esc_html_e( 'Admin Studio', 'rar-admin-studio' ); ?></h1>
				<p><?php esc_html_e( 'How the sign-in page, the admin area and the dashboard look and behave for your team.', 'rar-admin-studio' ); ?></p>
			</header>
			<hr class="wp-header-end">
			<?php if ( defined( 'RAR_AS_DISABLE' ) && RAR_AS_DISABLE ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'RAR_AS_DISABLE is set in wp-config.php, so none of these settings are applied right now.', 'rar-admin-studio' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $notices[ $msg ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $msg ] ); ?></p></div>
			<?php endif; ?>

			<nav class="rar-as-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'rar-admin-studio' ); ?>">
				<?php foreach ( $tabs as $k => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $k ), admin_url( 'options-general.php' ) ) ); ?>" role="tab" id="rar-as-tab-<?php echo esc_attr( $k ); ?>" aria-controls="rar-as-panel-<?php echo esc_attr( $k ); ?>" aria-selected="<?php echo $k === $tab ? 'true' : 'false'; ?>" data-tab="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="rar-as-form">
				<?php settings_fields( 'rar_as' ); ?>
				<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab ), 'options-general.php' ) ); ?>" class="rar-as-referer">

				<?php
				foreach ( array_keys( $tabs ) as $k ) {
					echo '<section class="rar-as-panel" id="rar-as-panel-' . esc_attr( $k ) . '" role="tabpanel" aria-labelledby="rar-as-tab-' . esc_attr( $k ) . '"' . ( $k === $tab ? '' : ' hidden' ) . '>';
					call_user_func( array( __CLASS__, 'panel_' . $k ) );
					echo '</section>';
				}
				?>
				<div class="rar-as-savebar">
					<?php submit_button( __( 'Save changes', 'rar-admin-studio' ), 'primary', 'submit', false ); ?>
					<span class="rar-as-savebar__hint"><?php esc_html_e( 'Saves every section at once.', 'rar-admin-studio' ); ?></span>
				</div>
			</form>
			<?php self::action_forms(); ?>
		</div>
		<?php
	}

	private static function panel_brand() {
		?>
		<div class="rar-as-cols">
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Name and logo', 'rar-admin-studio' ); ?></h2>
				<?php
				self::text( 'brand_name', __( 'Brand name', 'rar-admin-studio' ), __( 'Leave empty to use the site title.', 'rar-admin-studio' ), 'text', 'placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '"' );
				self::image( 'logo_id', __( 'Logo', 'rar-admin-studio' ), __( 'Shown on the dark sign-in panel. A light or white logo (PNG or SVG, about 320 px wide) looks best. Without one, the site icon or the first letter of the brand name is used.', 'rar-admin-studio' ) );
				self::check( 'use_fonts', __( 'Use the bundled fonts', 'rar-admin-studio' ), __( 'Archivo for text, Hind Siliguri for Bangla and Unbounded for headline figures, served from your own site (about 360 KB, cached by the browser). Turn off to use the computer\'s system fonts.', 'rar-admin-studio' ) );
				?>
			</div>
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Colours', 'rar-admin-studio' ); ?></h2>
				<?php
				self::color( 'color_primary', __( 'Main colour', 'rar-admin-studio' ), __( 'Buttons, links and highlights. It is darkened automatically if white text would be hard to read on it.', 'rar-admin-studio' ) );
				self::color( 'color_sidebar', __( 'Side menu colour', 'rar-admin-studio' ), __( 'The left menu, the top bar and the sign-in panel.', 'rar-admin-studio' ) );
				self::color( 'color_accent', __( 'Accent colour', 'rar-admin-studio' ), __( 'The current menu item marker and today\'s bar in the sales chart. Use it sparingly.', 'rar-admin-studio' ) );
				?>
				<div class="rar-as-preview" aria-hidden="true">
					<div class="rar-as-preview__side">
						<span class="rar-as-preview__item"></span>
						<span class="rar-as-preview__item is-current"></span>
						<span class="rar-as-preview__item"></span>
						<span class="rar-as-preview__item"></span>
					</div>
					<div class="rar-as-preview__main">
						<span class="rar-as-preview__title"></span>
						<span class="rar-as-preview__btn"><?php esc_html_e( 'Save', 'rar-admin-studio' ); ?></span>
						<span class="rar-as-preview__bars"><i></i><i></i><i></i><i class="is-today"></i></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function panel_login() {
		?>
		<div class="rar-as-cols">
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Sign-in page', 'rar-admin-studio' ); ?></h2>
				<?php
				self::check( 'login_enabled', __( 'Use the branded sign-in page', 'rar-admin-studio' ), __( 'Changes only how wp-login.php looks. The form itself, password reset and other sign-in plugins keep working as before.', 'rar-admin-studio' ) );
				self::text( 'login_headline', __( 'Headline', 'rar-admin-studio' ), __( 'Large text on the brand panel. Bangla works too.', 'rar-admin-studio' ) );
				self::textarea( 'login_text', __( 'Short description', 'rar-admin-studio' ), '', 3 );
				self::text( 'login_footer', __( 'Footer line', 'rar-admin-studio' ), __( '{year} becomes the current year, {site} the brand name.', 'rar-admin-studio' ) );
				?>
				<div class="rar-as-field">
					<label for="rar-as-login_logo_link"><?php esc_html_e( 'Logo link', 'rar-admin-studio' ); ?></label>
					<select id="rar-as-login_logo_link" name="<?php echo esc_attr( self::name( 'login_logo_link' ) ); ?>">
						<option value="home" <?php selected( 'home', RAR_AS_Settings::get( 'login_logo_link' ) ); ?>><?php esc_html_e( 'Opens the shop home page', 'rar-admin-studio' ); ?></option>
						<option value="none" <?php selected( 'none', RAR_AS_Settings::get( 'login_logo_link' ) ); ?>><?php esc_html_e( 'Not a link', 'rar-admin-studio' ); ?></option>
					</select>
				</div>
			</div>
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Background', 'rar-admin-studio' ); ?></h2>
				<?php self::image( 'login_bg_id', __( 'Photo behind the brand panel', 'rar-admin-studio' ), __( 'Optional. It is tinted with the side menu colour so the text stays readable. A wide product or team photo works well.', 'rar-admin-studio' ) ); ?>
				<p><a class="button" href="<?php echo esc_url( wp_login_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the sign-in page in a new tab', 'rar-admin-studio' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'You stay signed in; the page shows the "already signed in" form. Use a private window to see it as your team does.', 'rar-admin-studio' ); ?></p>
			</div>
		</div>
		<?php
	}

	private static function panel_workspace() {
		?>
		<div class="rar-as-cols">
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Admin design', 'rar-admin-studio' ); ?></h2>
				<?php
				self::check( 'skin_enabled', __( 'Use the Admin Studio design in wp-admin', 'rar-admin-studio' ), __( 'Side menu, top bar, page background, buttons, fields, tables, order and stock labels.', 'rar-admin-studio' ) );
				self::textarea( 'excluded_screens', __( 'Excluded screens', 'rar-admin-studio' ), __( 'Screen IDs, one per line, where buttons, fields and tables keep the plain WordPress look (the menu and top bar still change). Useful if a plugin\'s own page looks wrong. The block editor and Elementor are always left alone.', 'rar-admin-studio' ), 3 );
				$screen = get_current_screen();
				if ( $screen ) {
					/* translators: %s: screen id */
					echo '<p class="description">' . esc_html( sprintf( __( 'Tip: this page\'s screen ID is %s. Other pages show theirs under Help → Screen ID only for developers, so ask if unsure.', 'rar-admin-studio' ), $screen->id ) ) . '</p>';
				}
				?>
			</div>
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Top bar and footer', 'rar-admin-studio' ); ?></h2>
				<?php
				self::check( 'adminbar_clean', __( 'Tidy the top bar', 'rar-admin-studio' ), __( 'Removes the WordPress logo menu for everyone, and Comments, New, Updates and Customize for people who are not administrators.', 'rar-admin-studio' ) );
				self::check( 'replace_howdy', __( 'Show just the name instead of "Howdy, name"', 'rar-admin-studio' ) );
				self::check( 'hide_nags', __( 'Hide WordPress update reminders from staff', 'rar-admin-studio' ), __( 'People who cannot install updates no longer see the "please notify the site administrator" banner.', 'rar-admin-studio' ) );
				self::check( 'users_column', __( 'Add a "Last sign-in" column to Users', 'rar-admin-studio' ) );
				self::text( 'footer_text', __( 'Footer text', 'rar-admin-studio' ), __( 'Replaces "Thank you for creating with WordPress". {site} becomes the brand name. Leave empty to keep the original.', 'rar-admin-studio' ) );
				?>
			</div>
		</div>
		<?php
	}

	private static function panel_dashboard() {
		?>
		<div class="rar-as-cols">
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Command Center', 'rar-admin-studio' ); ?></h2>
				<?php
				self::check( 'cc_enabled', __( 'Show the Command Center on the dashboard', 'rar-admin-studio' ), __( 'Sales, orders to handle, latest orders, stock watch and best sellers. Each person sees only what their role allows: sales figures need the "View WooCommerce reports" permission.', 'rar-admin-studio' ) );
				self::check( 'focus_nonadmin', __( 'Show only the Command Center to non-administrators', 'rar-admin-studio' ), __( 'Shop managers and staff get a clean dashboard without other plugins\' boxes.', 'rar-admin-studio' ) );
				self::check( 'clean_core', __( 'Remove WordPress news, Quick Draft and the welcome box', 'rar-admin-studio' ), __( 'For non-administrators this also removes At a Glance, Activity and Site Health.', 'rar-admin-studio' ) );
				?>
			</div>
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Extra quick links', 'rar-admin-studio' ); ?></h2>
				<?php self::textarea( 'custom_links', __( 'Links next to "New order"', 'rar-admin-studio' ), __( 'One per line as  Label | address. The address can be a wp-admin page (admin.php?page=wc-reports), a page of your site (/staff/) or a full https:// link. Up to 8 links.', 'rar-admin-studio' ), 5 ); ?>
				<p class="description"><?php esc_html_e( 'Example:', 'rar-admin-studio' ); ?> <code>Staff app | /staff/</code></p>
			</div>
		</div>
		<?php
	}

	private static function panel_roles() {
		$roles    = RAR_AS_Menus::editable_roles();
		$snapshot = get_option( RAR_AS_Menus::SNAPSHOT, array() );
		$hide     = (array) RAR_AS_Settings::get( 'menu_hide' );
		$redirect = (array) RAR_AS_Settings::get( 'login_redirect' );
		?>
		<div class="rar-as-box">
			<h2><?php esc_html_e( 'Hide menu items by role', 'rar-admin-studio' ); ?></h2>
			<p class="description rar-as-wide"><?php esc_html_e( 'Tick what each role should not see in the left menu. Administrators always see everything. Hiding a menu tidies the screen; what a role is allowed to do still comes from its permissions.', 'rar-admin-studio' ); ?></p>
			<?php if ( ! $roles ) : ?>
				<p><?php esc_html_e( 'There are no roles to configure besides Administrator, Customer and Subscriber.', 'rar-admin-studio' ); ?></p>
			<?php elseif ( ! $snapshot ) : ?>
				<p><?php esc_html_e( 'The menu list appears after this page is reloaded once.', 'rar-admin-studio' ); ?></p>
			<?php else : ?>
				<div class="rar-as-tablewrap">
					<table class="widefat rar-as-menu-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Menu item', 'rar-admin-studio' ); ?></th>
								<?php foreach ( $roles as $slug => $name ) : ?>
									<th scope="col" class="rar-as-role-col">
										<span><?php echo esc_html( translate_user_role( $name ) ); ?></span>
										<button type="button" class="button-link rar-as-suggest" data-role="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Suggest', 'rar-admin-studio' ); ?></button>
										<button type="button" class="button-link rar-as-none" data-role="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Clear', 'rar-admin-studio' ); ?></button>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $snapshot as $item ) {
								self::menu_row( $item, $roles, $hide, false );
								foreach ( (array) $item['children'] as $child ) {
									self::menu_row( $child, $roles, $hide, true );
								}
							}
							?>
						</tbody>
					</table>
				</div>
				<p class="description"><?php esc_html_e( '"Suggest" ticks everything except the dashboard, profile, media, WooCommerce orders and products and the RAR plugins. Check the result before saving.', 'rar-admin-studio' ); ?></p>
			<?php endif; ?>
			<?php self::check( 'menu_block', __( 'Also refuse hidden screens opened by a direct link', 'rar-admin-studio' ), __( 'Shows "This screen is not available for your role" instead of the page. Leave off if a role still needs a hidden screen through a link inside another page.', 'rar-admin-studio' ) ); ?>
		</div>

		<div class="rar-as-box">
			<h2><?php esc_html_e( 'Landing page after sign-in', 'rar-admin-studio' ); ?></h2>
			<p class="description rar-as-wide"><?php esc_html_e( 'A wp-admin address per role, for example admin.php?page=wc-orders. Leave empty for the normal dashboard. A link someone followed to the sign-in page still takes them where they were going.', 'rar-admin-studio' ); ?></p>
			<div class="rar-as-grid2">
				<?php
				$all_roles = wp_roles()->get_names();
				unset( $all_roles['customer'], $all_roles['subscriber'] );
				foreach ( $all_roles as $slug => $name ) :
					?>
					<div class="rar-as-field">
						<label for="rar-as-redirect-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( translate_user_role( $name ) ); ?></label>
						<input type="text" id="rar-as-redirect-<?php echo esc_attr( $slug ); ?>" name="<?php echo esc_attr( RAR_AS_Settings::OPTION . '[login_redirect][' . $slug . ']' ); ?>" value="<?php echo esc_attr( isset( $redirect[ $slug ] ) ? $redirect[ $slug ] : '' ); ?>" placeholder="index.php" class="regular-text code">
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function menu_row( $item, $roles, $hide, $child ) {
		echo '<tr class="' . ( $child ? 'rar-as-sub' : 'rar-as-top' ) . '"><th scope="row"><span>' . esc_html( $item['title'] ) . '</span> <code>' . esc_html( $child ? substr( $item['slug'], strpos( $item['slug'], '::' ) + 2 ) : $item['slug'] ) . '</code></th>';
		foreach ( $roles as $slug => $name ) {
			$checked = isset( $hide[ $slug ] ) && in_array( $item['slug'], (array) $hide[ $slug ], true );
			printf(
				'<td><label class="rar-as-cell"><input type="checkbox" name="%1$s" value="%2$s" data-role="%3$s" data-slug="%2$s" %4$s><span class="screen-reader-text">%5$s</span></label></td>',
				esc_attr( RAR_AS_Settings::OPTION . '[menu_hide][' . $slug . '][]' ),
				esc_attr( $item['slug'] ),
				esc_attr( $slug ),
				checked( $checked, true, false ),
				/* translators: 1: menu item, 2: role */
				esc_html( sprintf( __( 'Hide %1$s for %2$s', 'rar-admin-studio' ), $item['title'], translate_user_role( $name ) ) )
			);
		}
		echo '</tr>';
	}

	private static function panel_security() {
		$rows = RAR_AS_Guard::recent( 30 );
		$now  = time();
		?>
		<div class="rar-as-cols">
			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Wrong-password protection', 'rar-admin-studio' ); ?></h2>
				<?php self::check( 'guard_enabled', __( 'Pause sign-in after repeated wrong passwords', 'rar-admin-studio' ), __( 'Applies to wp-login.php, the My Account sign-in and XML-RPC. Counted per network and username, so nobody can lock a real person out from somewhere else.', 'rar-admin-studio' ) ); ?>
				<div class="rar-as-grid2">
					<?php
					self::text( 'guard_max', __( 'Wrong passwords per username', 'rar-admin-studio' ), __( 'From the same network (3–20).', 'rar-admin-studio' ), 'number', 'min="3" max="20"' );
					self::text( 'guard_window', __( 'Counted within (minutes)', 'rar-admin-studio' ), '', 'number', 'min="5" max="120"' );
					self::text( 'guard_lock', __( 'Pause length (minutes)', 'rar-admin-studio' ), '', 'number', 'min="5" max="1440"' );
					self::text( 'guard_ip_max', __( 'Wrong passwords per network, any username', 'rar-admin-studio' ), __( 'Stops one network guessing many usernames (10–200).', 'rar-admin-studio' ), 'number', 'min="10" max="200"' );
					?>
				</div>
				<?php self::check( 'generic_errors', __( 'Use one message for a wrong username or a wrong password', 'rar-admin-studio' ), __( 'So the sign-in form does not reveal which usernames exist. It also tells people how many tries are left.', 'rar-admin-studio' ) ); ?>
				<?php self::check( 'record_login', __( 'Remember each person\'s last sign-in time and network', 'rar-admin-studio' ), __( 'Shown to them on the dashboard and to administrators in Users.', 'rar-admin-studio' ) ); ?>
				<div class="rar-as-field">
					<label for="rar-as-ip_source"><?php esc_html_e( 'How to read the visitor\'s network address', 'rar-admin-studio' ); ?></label>
					<select id="rar-as-ip_source" name="<?php echo esc_attr( self::name( 'ip_source' ) ); ?>">
						<option value="remote" <?php selected( 'remote', RAR_AS_Settings::get( 'ip_source' ) ); ?>><?php esc_html_e( 'Direct connection (default)', 'rar-admin-studio' ); ?></option>
						<option value="cloudflare" <?php selected( 'cloudflare', RAR_AS_Settings::get( 'ip_source' ) ); ?>><?php esc_html_e( 'Behind Cloudflare', 'rar-admin-studio' ); ?></option>
					</select>
					<p class="description">
						<?php
						/* translators: %s: IP address */
						echo esc_html( sprintf( __( 'Your address as the site sees it now: %s. If this is the same for every person in different places, the site is behind a proxy; choose Cloudflare only when the site really uses Cloudflare (its header is trusted only from Cloudflare\'s own addresses).', 'rar-admin-studio' ), RAR_AS_Guard::ip() ) );
						?>
					</p>
				</div>
			</div>

			<div class="rar-as-box">
				<h2><?php esc_html_e( 'Recent wrong passwords', 'rar-admin-studio' ); ?></h2>
				<?php if ( ! $rows ) : ?>
					<p class="rar-as-empty"><?php esc_html_e( 'Nothing recorded. Wrong passwords from the last two days will be listed here.', 'rar-admin-studio' ); ?></p>
				<?php else : ?>
					<div class="rar-as-tablewrap">
						<table class="widefat striped rar-as-guard-table">
							<thead><tr>
								<th scope="col"><?php esc_html_e( 'Network', 'rar-admin-studio' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Username tried', 'rar-admin-studio' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'rar-admin-studio' ); ?></th>
								<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'rar-admin-studio' ); ?></span></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $rows as $r ) : ?>
								<tr>
									<td><code><?php echo esc_html( $r->ip ); ?></code></td>
									<td><?php echo 'i' === $r->kind ? '<em>' . esc_html__( 'any username', 'rar-admin-studio' ) . '</em>' : esc_html( $r->label ); ?></td>
									<td>
										<?php
										if ( (int) $r->locked_until > $now ) {
											/* translators: %s: time span */
											echo '<span class="rar-as-pill rar-as-pill--bad">' . esc_html( sprintf( __( 'Paused, %s left', 'rar-admin-studio' ), human_time_diff( $now, (int) $r->locked_until ) ) ) . '</span>';
										} else {
											/* translators: 1: count, 2: time span */
											echo esc_html( sprintf( _n( '%1$d wrong, %2$s ago', '%1$d wrong, %2$s ago', (int) $r->fails, 'rar-admin-studio' ), (int) $r->fails, human_time_diff( (int) $r->last_at, $now ) ) );
										}
										?>
									</td>
									<td>
										<button type="submit" class="button button-small" form="rar-as-unlock-form" name="key" value="<?php echo esc_attr( $r->id_hash ); ?>"><?php echo (int) $r->locked_until > $now ? esc_html__( 'Unpause', 'rar-admin-studio' ) : esc_html__( 'Reset', 'rar-admin-studio' ); ?></button>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p><button type="submit" class="button" form="rar-as-clear-form"><?php esc_html_e( 'Clear all counters', 'rar-admin-studio' ); ?></button></p>
				<?php endif; ?>
				<h3><?php esc_html_e( 'Locked out yourself?', 'rar-admin-studio' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Wait for the pause to end, sign in from another network (for example mobile data), or add define( \'RAR_AS_DISABLE\', true ); to wp-config.php in hPanel → File Manager, sign in, then remove it.', 'rar-admin-studio' ); ?></p>
			</div>
		</div>

		<div class="rar-as-box rar-as-box--quiet">
			<h2><?php esc_html_e( 'Reset', 'rar-admin-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Puts every Admin Studio setting back to its default. Sign-in counters and last sign-in records are kept.', 'rar-admin-studio' ); ?></p>
			<p><button type="submit" class="button" form="rar-as-reset-form" onclick="return confirm('<?php echo esc_js( __( 'Reset all Admin Studio settings to the defaults?', 'rar-admin-studio' ) ); ?>');"><?php esc_html_e( 'Reset settings', 'rar-admin-studio' ); ?></button></p>
		</div>
		<?php
	}

	/** Small forms used by buttons inside the settings form (forms cannot be nested; buttons use form="…"). */
	private static function action_forms() {
		foreach ( array( 'unlock', 'clear', 'reset' ) as $a ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="rar-as-' . esc_attr( $a ) . '-form" hidden>';
			wp_nonce_field( 'rar_as_' . $a );
			echo '<input type="hidden" name="action" value="rar_as_' . esc_attr( $a ) . '"></form>';
		}
	}
}
