<?php
/**
 * Command Center: role-aware panel at the top of the WordPress dashboard.
 *
 * What each person sees depends on capabilities:
 *   view_woocommerce_reports → sales figures, 14-day chart, best sellers
 *   edit_shop_orders         → orders to handle, recent orders
 *   edit_products            → stock watch
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Dashboard {

	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'clean' ), PHP_INT_MAX );
		if ( ! RAR_AS_Settings::get( 'cc_enabled' ) ) {
			return;
		}
		add_action( 'load-index.php', array( __CLASS__, 'load' ) );
	}

	private static function focus_mode() {
		return RAR_AS_Settings::get( 'cc_enabled' ) && RAR_AS_Settings::get( 'focus_nonadmin' ) && ! RAR_AS_Helpers::is_admin_user();
	}

	public static function load() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['rar_as_refresh'] ) ) {
			check_admin_referer( 'rar_as_refresh' );
			RAR_AS_Metrics::flush();
			wp_safe_redirect( admin_url( 'index.php' ) );
			exit;
		}
		add_action( 'all_admin_notices', array( __CLASS__, 'render' ), PHP_INT_MAX );
		add_filter(
			'admin_body_class',
			static function ( $c ) {
				return $c . ' rar-as-cc-on' . ( self::focus_mode() ? ' rar-as-focus' : '' );
			}
		);
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	/** Remove clutter widgets. Third-party widgets stay for administrators. */
	public static function clean() {
		global $wp_meta_boxes;
		if ( RAR_AS_Settings::get( 'clean_core' ) ) {
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
			if ( ! RAR_AS_Helpers::is_admin_user() ) {
				remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
				remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
				remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
			}
		}
		if ( self::focus_mode() && isset( $wp_meta_boxes['dashboard'] ) ) {
			$wp_meta_boxes['dashboard'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}

	/* ------------------------------------------------------------ Render */

	public static function render() {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		$can_reports  = current_user_can( 'view_woocommerce_reports' );
		$can_orders   = current_user_can( 'edit_shop_orders' );
		$can_products = current_user_can( 'edit_products' );
		$m            = ( $can_reports || $can_orders || $can_products ) ? RAR_AS_Metrics::get() : null;
		$user         = wp_get_current_user();
		$first        = $user->first_name ? $user->first_name : $user->display_name;
		$hour         = (int) wp_date( 'G' );
		if ( $hour < 12 ) {
			$hello = __( 'Good morning', 'rar-admin-studio' );
		} elseif ( $hour < 17 ) {
			$hello = __( 'Good afternoon', 'rar-admin-studio' );
		} else {
			$hello = __( 'Good evening', 'rar-admin-studio' );
		}
		?>
		<section class="rar-as-cc" aria-labelledby="rar-as-cc-title">
			<header class="rar-as-cc__head">
				<div class="rar-as-cc__intro">
					<h1 id="rar-as-cc-title" class="rar-as-cc__hello"><?php echo esc_html( $hello . ', ' . $first ); ?></h1>
					<p class="rar-as-cc__date">
						<?php echo esc_html( wp_date( 'l, j F Y' ) ); ?>
						<?php if ( $m ) : ?>
							<span class="rar-as-cc__updated">
								<?php
								/* translators: %s: time */
								echo esc_html( sprintf( __( 'Figures updated %s', 'rar-admin-studio' ), wp_date( get_option( 'time_format' ), (int) $m['generated'] ) ) );
								?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'index.php?rar_as_refresh=1' ), 'rar_as_refresh' ) ); ?>"><?php esc_html_e( 'Refresh', 'rar-admin-studio' ); ?></a>
							</span>
						<?php endif; ?>
					</p>
				</div>
				<?php self::quick_links( $can_orders, $can_products ); ?>
			</header>

			<?php if ( $m ) : ?>
				<?php self::tiles( $m, $can_reports, $can_orders, $can_products ); ?>
				<div class="rar-as-cc__grid">
					<?php
					if ( $can_reports ) {
						self::card_chart( $m );
					}
					if ( $can_orders ) {
						self::card_pipeline( $m );
						self::card_recent( $m );
					}
					if ( $can_products ) {
						self::card_stock( $m );
					}
					if ( $can_reports ) {
						self::card_top( $m );
					}
					?>
				</div>
			<?php endif; ?>

			<?php self::foot( $user, $can_reports && $m ); ?>
		</section>
		<?php
	}

	private static function quick_links( $can_orders, $can_products ) {
		$links = array();
		if ( $can_orders ) {
			$links[] = array( __( 'New order', 'rar-admin-studio' ), RAR_AS_Helpers::new_order_url(), 'primary' );
			$links[] = array( __( 'Orders', 'rar-admin-studio' ), RAR_AS_Helpers::orders_url(), '' );
		}
		if ( $can_products ) {
			$links[] = array( __( 'Add product', 'rar-admin-studio' ), admin_url( 'post-new.php?post_type=product' ), '' );
			$links[] = array( __( 'Products', 'rar-admin-studio' ), admin_url( 'edit.php?post_type=product' ), '' );
		}
		foreach ( RAR_AS_Settings::custom_links() as $l ) {
			$links[] = array( $l[0], $l[1], '' );
		}
		if ( ! $links ) {
			$links[] = array( __( 'Your profile', 'rar-admin-studio' ), admin_url( 'profile.php' ), '' );
		}
		echo '<nav class="rar-as-cc__links" aria-label="' . esc_attr__( 'Quick actions', 'rar-admin-studio' ) . '">';
		foreach ( $links as $l ) {
			printf(
				'<a class="rar-as-btn%s" href="%s">%s</a>',
				$l[2] ? ' rar-as-btn--primary' : '',
				esc_url( $l[1] ),
				esc_html( $l[0] )
			);
		}
		echo '</nav>';
	}

	/**
	 * Change line: "+৳ 1,200 (+12%) vs …".
	 */
	private static function delta( $cur, $prev, $label ) {
		$diff = $cur - $prev;
		if ( abs( $prev ) < 0.005 ) {
			if ( abs( $cur ) < 0.005 ) {
				/* translators: %s: comparison label, e.g. "yesterday by this time" */
				return '<span class="rar-as-delta">' . esc_html( sprintf( __( 'Same as %s', 'rar-admin-studio' ), $label ) ) . '</span>';
			}
			/* translators: %s: comparison label */
			return '<span class="rar-as-delta rar-as-delta--up">' . esc_html( sprintf( __( 'Nothing booked %s', 'rar-admin-studio' ), $label ) ) . '</span>';
		}
		$pct   = $diff / abs( $prev ) * 100;
		$class = $diff > 0.004 ? 'up' : ( $diff < -0.004 ? 'down' : 'flat' );
		$sign  = $diff > 0 ? '+' : ( $diff < 0 ? '−' : '' );
		$money = ( $diff > 0 ? '+' : '' ) . RAR_AS_Helpers::money( $diff );
		return sprintf(
			'<span class="rar-as-delta rar-as-delta--%1$s"><b>%2$s</b> (%3$s%4$s%%) %5$s</span>',
			esc_attr( $class ),
			esc_html( $money ),
			esc_html( $sign ),
			esc_html( number_format_i18n( abs( $pct ), abs( $pct ) < 10 ? 1 : 0 ) ),
			esc_html( $label )
		);
	}

	private static function tiles( $m, $can_reports, $can_orders, $can_products ) {
		$s = $m['sum'];
		echo '<div class="rar-as-tiles">';
		if ( $can_reports ) {
			self::tile(
				__( 'Booked today', 'rar-admin-studio' ),
				RAR_AS_Helpers::money( $s['today'] ),
				/* translators: %d: number of orders */
				esc_html( sprintf( _n( '%d order', '%d orders', $s['today_n'], 'rar-admin-studio' ), $s['today_n'] ) ) . '<br>' . self::delta( $s['today'], $s['yday_now'], __( 'vs yesterday by this time', 'rar-admin-studio' ) ),
				'lead'
			);
			self::tile(
				__( 'This month', 'rar-admin-studio' ),
				RAR_AS_Helpers::money( $s['month'] ),
				/* translators: %d: number of orders */
				esc_html( sprintf( _n( '%d order so far', '%d orders so far', $s['month_n'], 'rar-admin-studio' ), $s['month_n'] ) )
				. ( $s['refunds'] < 0 ? '<br><span class="rar-as-delta">' . esc_html( sprintf( /* translators: %s: amount */ __( 'after %s refunds', 'rar-admin-studio' ), RAR_AS_Helpers::money( abs( $s['refunds'] ) ) ) ) . '</span>' : '' )
			);
			self::tile(
				__( 'Last 7 days', 'rar-admin-studio' ),
				RAR_AS_Helpers::money( $s['week'] ),
				self::delta( $s['week'], $s['prev_week'], __( 'vs the 7 days before', 'rar-admin-studio' ) )
			);
		}
		if ( $can_orders ) {
			$open   = self::open_statuses( $m );
			$total  = array_sum( $open );
			$detail = array();
			foreach ( array_slice( $open, 0, 2, true ) as $slug => $n ) {
				$detail[] = $n . ' ' . self::status_label( $slug );
			}
			self::tile(
				__( 'Orders to handle', 'rar-admin-studio' ),
				number_format_i18n( $total ),
				$detail ? esc_html( implode( ', ', $detail ) ) : esc_html__( 'Nothing waiting', 'rar-admin-studio' ),
				'',
				RAR_AS_Helpers::orders_url()
			);
		}
		if ( $can_products ) {
			$st = $m['stock'];
			self::tile(
				__( 'Stock alerts', 'rar-admin-studio' ),
				number_format_i18n( $st['out_n'] + $st['low_n'] ),
				/* translators: 1: out of stock count, 2: low stock count */
				esc_html( sprintf( __( '%1$d out of stock, %2$d running low', 'rar-admin-studio' ), $st['out_n'], $st['low_n'] ) ),
				( $st['out_n'] + $st['low_n'] ) > 0 ? 'warn' : '',
				admin_url( 'edit.php?post_type=product&stock_status=outofstock' )
			);
		}
		echo '</div>';
	}

	/**
	 * @param string $sub_html Already-escaped HTML.
	 */
	private static function tile( $label, $value, $sub_html, $mod = '', $href = '' ) {
		$tag  = $href ? 'a' : 'div';
		$attr = $href ? ' href="' . esc_url( $href ) . '"' : '';
		printf(
			'<%1$s class="rar-as-tile%2$s"%3$s><span class="rar-as-tile__label">%4$s</span><span class="rar-as-tile__value">%5$s</span><span class="rar-as-tile__sub">%6$s</span></%1$s>',
			$tag, // phpcs:ignore WordPress.Security.EscapeOutput
			$mod ? ' rar-as-tile--' . esc_attr( $mod ) : '',
			$attr, // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $label ),
			esc_html( $value ),
			$sub_html // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by callers.
		);
	}

	private static function open_statuses( $m ) {
		$closed = RAR_AS_Metrics::closed_statuses();
		$open   = array();
		foreach ( (array) $m['statuses'] as $slug => $n ) {
			if ( $n > 0 && ! in_array( $slug, $closed, true ) ) {
				$open[ $slug ] = (int) $n;
			}
		}
		arsort( $open );
		return $open;
	}

	private static function status_label( $slug ) {
		$all = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		return isset( $all[ 'wc-' . $slug ] ) ? $all[ 'wc-' . $slug ] : ucwords( str_replace( '-', ' ', $slug ) );
	}

	private static function card_open( $title, $mod = '', $link = null ) {
		echo '<article class="rar-as-card' . ( $mod ? ' rar-as-card--' . esc_attr( $mod ) : '' ) . '"><header class="rar-as-card__head"><h2>' . esc_html( $title ) . '</h2>';
		if ( $link ) {
			echo '<a href="' . esc_url( $link[1] ) . '">' . esc_html( $link[0] ) . '</a>';
		}
		echo '</header><div class="rar-as-card__body">';
	}

	private static function card_close() {
		echo '</div></article>';
	}

	private static function card_chart( $m ) {
		$days    = $m['days'];
		$max     = 0;
		$total   = 0;
		foreach ( $days as $d ) {
			$max    = max( $max, $d['value'] );
			$total += $d['value'];
		}
		$top     = self::nice_max( $max );
		$today_k = wp_date( 'Y-m-d' );
		$utc     = new DateTimeZone( 'UTC' );

		self::card_open( __( 'Booked sales, last 14 days', 'rar-admin-studio' ), 'chart' );
		echo '<p class="rar-as-card__lede">' . esc_html( RAR_AS_Helpers::money( $total ) ) . ' <span>' . esc_html__( 'in 14 days', 'rar-admin-studio' ) . '</span></p>';

		// HTML bars: labels keep their real size at every screen width.
		echo '<div class="rar-as-days" aria-hidden="true">';
		echo '<div class="rar-as-days__axis">';
		foreach ( array( 1, 0.5, 0 ) as $f ) {
			echo '<span style="bottom:' . esc_attr( $f * 100 ) . '%">' . esc_html( RAR_AS_Helpers::compact( $top * $f ) ) . '</span>';
		}
		echo '</div><div class="rar-as-days__plot"><i class="rar-as-days__grid" style="bottom:100%"></i><i class="rar-as-days__grid" style="bottom:calc(26px + (100% - 26px) / 2)"></i><i class="rar-as-days__grid" style="bottom:26px"></i><ol class="rar-as-days__cols">';
		foreach ( $days as $key => $d ) {
			$ts    = strtotime( $key . ' 12:00:00 UTC' );
			$val   = max( 0, $d['value'] );
			$pct   = $top > 0 ? round( $val / $top * 100, 2 ) : 0;
			$is_t  = $key === $today_k;
			$tip   = wp_date( 'D j M', $ts, $utc ) . ': ' . RAR_AS_Helpers::money( $d['value'] ) . ', ' . sprintf( /* translators: %d: orders */ _n( '%d order', '%d orders', $d['count'], 'rar-admin-studio' ), $d['count'] );
			$label = $is_t ? __( 'Today', 'rar-admin-studio' ) : wp_date( 'j', $ts, $utc );
			printf(
				'<li class="%1$s" title="%2$s"><span class="rar-as-days__bar%3$s" style="height:%4$s%%"></span><span class="rar-as-days__label">%5$s</span></li>',
				$is_t ? 'is-today' : '',
				esc_attr( $tip ),
				$val > 0 ? '' : ' is-zero',
				esc_attr( $val > 0 ? max( 1.2, $pct ) : 0 ),
				esc_html( $label )
			);
		}
		echo '</ol></div></div>';

		// Same numbers as a table for screen readers.
		echo '<table class="screen-reader-text"><caption>' . esc_html__( 'Booked sales per day for the last 14 days', 'rar-admin-studio' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Day', 'rar-admin-studio' ) . '</th><th scope="col">' . esc_html__( 'Booked sales', 'rar-admin-studio' ) . '</th><th scope="col">' . esc_html__( 'Orders', 'rar-admin-studio' ) . '</th></tr></thead><tbody>';
		foreach ( $days as $key => $d ) {
			echo '<tr><th scope="row">' . esc_html( wp_date( 'j F', strtotime( $key . ' 12:00:00 UTC' ), $utc ) ) . '</th><td>' . esc_html( RAR_AS_Helpers::money( $d['value'] ) ) . '</td><td>' . (int) $d['count'] . '</td></tr>';
		}
		echo '</tbody></table>';
		self::card_close();
	}

	private static function nice_max( $v ) {
		if ( $v <= 0 ) {
			return 1000;
		}
		$exp  = pow( 10, floor( log10( $v ) ) );
		$frac = $v / $exp;
		foreach ( array( 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10 ) as $step ) {
			if ( $frac <= $step ) {
				return $step * $exp;
			}
		}
		return 10 * $exp;
	}

	private static function card_pipeline( $m ) {
		$open = self::open_statuses( $m );
		self::card_open( __( 'Orders to handle', 'rar-admin-studio' ), 'pipeline', array( __( 'All orders', 'rar-admin-studio' ), RAR_AS_Helpers::orders_url() ) );
		if ( ! $open ) {
			echo '<p class="rar-as-empty">' . esc_html__( 'No open orders. New orders will appear here by status.', 'rar-admin-studio' ) . '</p>';
		} else {
			$max = max( $open );
			echo '<ul class="rar-as-bars">';
			foreach ( $open as $slug => $n ) {
				$pct = $max > 0 ? max( 4, round( $n / $max * 100 ) ) : 0;
				printf(
					'<li><a href="%1$s"><span class="rar-as-bars__name"><mark class="order-status status-%2$s"><span>%3$s</span></mark></span><span class="rar-as-bars__track"><span class="rar-as-bars__fill status-%2$s" style="width:%4$d%%"></span></span><span class="rar-as-bars__n">%5$s</span></a></li>',
					esc_url( RAR_AS_Helpers::orders_url( $slug ) ),
					esc_attr( sanitize_html_class( $slug ) ),
					esc_html( self::status_label( $slug ) ),
					(int) $pct,
					esc_html( number_format_i18n( $n ) )
				);
			}
			echo '</ul>';
		}
		self::card_close();
	}

	private static function card_recent( $m ) {
		self::card_open( __( 'Latest orders', 'rar-admin-studio' ), 'recent', array( __( 'View all', 'rar-admin-studio' ), RAR_AS_Helpers::orders_url() ) );
		if ( empty( $m['recent'] ) ) {
			echo '<p class="rar-as-empty">' . esc_html__( 'No orders yet. Use "New order" to add one by hand.', 'rar-admin-studio' ) . '</p>';
		} else {
			echo '<ul class="rar-as-list">';
			foreach ( $m['recent'] as $o ) {
				printf(
					'<li><a href="%1$s"><span class="rar-as-list__main"><b>#%2$s</b> %3$s</span><span class="rar-as-list__meta"><mark class="order-status status-%4$s"><span>%5$s</span></mark><span>%6$s</span></span><span class="rar-as-list__amount">%7$s</span></a></li>',
					esc_url( RAR_AS_Helpers::order_edit_url( $o['id'] ) ),
					esc_html( $o['number'] ),
					esc_html( $o['name'] ),
					esc_attr( sanitize_html_class( $o['status'] ) ),
					esc_html( self::status_label( $o['status'] ) ),
					esc_html( $o['ts'] ? sprintf( /* translators: %s: time span */ __( '%s ago', 'rar-admin-studio' ), human_time_diff( $o['ts'], time() ) ) : '' ),
					esc_html( RAR_AS_Helpers::money( $o['total'] ) )
				);
			}
			echo '</ul>';
		}
		self::card_close();
	}

	private static function card_stock( $m ) {
		$st = $m['stock'];
		self::card_open( __( 'Stock watch', 'rar-admin-studio' ), 'stock', array( __( 'Products', 'rar-admin-studio' ), admin_url( 'edit.php?post_type=product' ) ) );
		echo '<div class="rar-as-stock-sum">';
		printf(
			'<a class="rar-as-chip rar-as-chip--out" href="%s"><b>%s</b> %s</a>',
			esc_url( admin_url( 'edit.php?post_type=product&stock_status=outofstock' ) ),
			esc_html( number_format_i18n( $st['out_n'] ) ),
			esc_html__( 'out of stock', 'rar-admin-studio' )
		);
		printf(
			'<span class="rar-as-chip rar-as-chip--low"><b>%s</b> %s</span>',
			esc_html( number_format_i18n( $st['low_n'] ) ),
			esc_html__( 'running low', 'rar-admin-studio' )
		);
		echo '</div>';
		if ( $st['low'] ) {
			echo '<ul class="rar-as-list rar-as-list--stock">';
			foreach ( $st['low'] as $p ) {
				printf(
					'<li><a href="%1$s"><span class="rar-as-list__main">%2$s</span><span class="rar-as-qty">%3$s</span></a></li>',
					esc_url( get_edit_post_link( $p['id'], 'url' ) ? get_edit_post_link( $p['id'], 'url' ) : admin_url( 'edit.php?post_type=product' ) ),
					esc_html( $p['name'] ),
					/* translators: %s: quantity */
					esc_html( sprintf( __( '%s left', 'rar-admin-studio' ), wc_stock_amount( $p['qty'] ) ) )
				);
			}
			echo '</ul>';
		} elseif ( $st['out'] ) {
			echo '<ul class="rar-as-list rar-as-list--stock">';
			foreach ( array_slice( $st['out'], 0, 5 ) as $p ) {
				printf(
					'<li><a href="%1$s"><span class="rar-as-list__main">%2$s</span><span class="rar-as-qty rar-as-qty--out">%3$s</span></a></li>',
					esc_url( get_edit_post_link( $p['id'], 'url' ) ? get_edit_post_link( $p['id'], 'url' ) : admin_url( 'edit.php?post_type=product&stock_status=outofstock' ) ),
					esc_html( $p['name'] ),
					esc_html__( 'Out', 'rar-admin-studio' )
				);
			}
			echo '</ul>';
		} else {
			echo '<p class="rar-as-empty">' . esc_html__( 'Every tracked product is above its low-stock level.', 'rar-admin-studio' ) . '</p>';
		}
		/* translators: %d: low stock threshold */
		echo '<p class="rar-as-note">' . esc_html( sprintf( __( 'Running low means stock-managed items at or below their low-stock level (store default: %d).', 'rar-admin-studio' ), (int) $st['threshold'] ) ) . '</p>';
		self::card_close();
	}

	private static function card_top( $m ) {
		self::card_open( __( 'Best sellers, last 30 days', 'rar-admin-studio' ), 'top' );
		if ( empty( $m['top'] ) ) {
			echo '<p class="rar-as-empty">' . esc_html__( 'No booked sales in the last 30 days yet.', 'rar-admin-studio' ) . '</p>';
		} else {
			$max = max( array_column( $m['top'], 'qty' ) );
			echo '<ol class="rar-as-top">';
			foreach ( $m['top'] as $p ) {
				$url = $p['id'] ? get_edit_post_link( $p['id'], 'url' ) : '';
				$pct = $max > 0 ? max( 6, round( $p['qty'] / $max * 100 ) ) : 0;
				printf(
					'<li><span class="rar-as-top__name">%1$s</span><span class="rar-as-top__nums"><b>%2$s</b> %3$s, %4$s</span><span class="rar-as-top__bar"><span style="width:%5$d%%"></span></span></li>',
					$url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $p['name'] ) . '</a>' : esc_html( $p['name'] ),
					esc_html( wc_stock_amount( $p['qty'] ) ),
					esc_html__( 'sold', 'rar-admin-studio' ),
					esc_html( RAR_AS_Helpers::money( $p['total'] ) ),
					(int) $pct
				);
			}
			echo '</ol>';
			echo '<p class="rar-as-note">' . esc_html__( 'Quantity sold and line totals after discounts, excluding tax and shipping.', 'rar-admin-studio' ) . '</p>';
		}
		self::card_close();
	}

	private static function foot( $user, $show_basis ) {
		$prev = get_user_meta( $user->ID, 'rar_as_prev_login', true );
		echo '<footer class="rar-as-cc__foot">';
		if ( is_array( $prev ) && ! empty( $prev['t'] ) ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: date and time, 2: IP address */
					__( 'Your previous sign-in: %1$s from %2$s. Not you? Change your password in your profile.', 'rar-admin-studio' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $prev['t'] ),
					isset( $prev['ip'] ) ? $prev['ip'] : '?'
				)
			) . '</p>';
		}
		if ( $show_basis ) {
			echo '<details class="rar-as-basis"><summary>' . esc_html__( 'How these figures are worked out', 'rar-admin-studio' ) . '</summary><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: time zone name */
					__( 'Booked sales are order totals (including shipping and tax, as stored by WooCommerce) of orders created in the period, leaving out pending payment, failed, cancelled, refunded and draft orders. Partial refunds made in the period are deducted. Days follow the site time zone (%s). These are operational figures, not accounting revenue; WooCommerce Analytics and your accounts remain the reference.', 'rar-admin-studio' ),
					wp_timezone_string()
				)
			);
			echo '</p></details>';
		}
		echo '</footer>';
	}
}
