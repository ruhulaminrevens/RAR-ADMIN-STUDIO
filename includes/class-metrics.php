<?php
/**
 * Command Center figures. Read-only SQL, cached for five minutes and cleared when orders or stock change.
 *
 * Basis (shown on the dashboard as well):
 *   Booked sales = order totals (incl. shipping and tax, as WooCommerce stores them) of orders created in the
 *   period, excluding pending payment, failed, cancelled, refunded, draft and trashed orders, minus partial
 *   refunds created in the period on included orders. Dates use the site time zone.
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Metrics {

	const TTL = 300;

	public static function init() {
		$flush = array( __CLASS__, 'flush' );
		add_action( 'woocommerce_new_order', $flush );
		add_action( 'woocommerce_order_status_changed', $flush );
		add_action( 'woocommerce_order_refunded', $flush );
		add_action( 'woocommerce_refund_deleted', $flush );
		add_action( 'woocommerce_delete_order', $flush );
		add_action( 'woocommerce_trash_order', $flush );
		add_action( 'woocommerce_product_set_stock', $flush );
		add_action( 'woocommerce_variation_set_stock', $flush );
		add_action( 'woocommerce_product_set_stock_status', $flush );
	}

	public static function flush() {
		update_option( 'rar_as_metrics_ver', (int) get_option( 'rar_as_metrics_ver', 0 ) + 1, true );
	}

	public static function excluded_statuses() {
		/**
		 * Order statuses (without "wc-") that never count as booked sales.
		 *
		 * @param string[] $statuses Status slugs.
		 */
		return (array) apply_filters( 'rar_as_excluded_statuses', array( 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft', 'trash', 'draft', 'auto-draft' ) );
	}

	/** Statuses that are finished (not shown in "Orders to handle"). */
	public static function closed_statuses() {
		return (array) apply_filters( 'rar_as_closed_statuses', array( 'completed', 'cancelled', 'refunded', 'failed', 'checkout-draft', 'trash', 'draft', 'auto-draft' ) );
	}

	public static function hpos() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * All figures, cached.
	 *
	 * @param bool $fresh Ignore the cache.
	 * @return array|null Null when WooCommerce is not active.
	 */
	public static function get( $fresh = false ) {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}
		$key = 'rar_as_m_' . md5( wp_date( 'Y-m-d' ) . '|' . (int) self::hpos() . '|' . (int) get_option( 'rar_as_metrics_ver', 0 ) . '|' . RAR_AS_VERSION );
		if ( ! $fresh ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$data = self::build();
		set_transient( $key, $data, self::TTL );
		return $data;
	}

	private static function build() {
		$tz        = wp_timezone();
		$now       = new DateTimeImmutable( 'now', $tz );
		$today     = $now->setTime( 0, 0 );
		$month     = $today->modify( 'first day of this month' );
		$from      = min( $month, $today->modify( '-29 days' ) );
		$from_gmt  = $from->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$excluded  = self::excluded_statuses();

		$orders  = self::orders_since( $from_gmt );
		$refunds = self::refunds_since( $from_gmt );

		$days = array();
		for ( $i = 13; $i >= 0; $i-- ) {
			$days[ $today->modify( "-{$i} days" )->format( 'Y-m-d' ) ] = array(
				'value' => 0.0,
				'count' => 0,
			);
		}

		$k_today      = $today->format( 'Y-m-d' );
		$k_yesterday  = $today->modify( '-1 day' )->format( 'Y-m-d' );
		$y_cut        = $now->modify( '-1 day' )->getTimestamp();
		$w0           = $today->modify( '-6 days' )->getTimestamp();
		$w1           = $today->modify( '-13 days' )->getTimestamp();
		$m0           = $month->getTimestamp();
		$d30          = $today->modify( '-29 days' )->getTimestamp();

		$sum = array(
			'today'     => 0.0,
			'today_n'   => 0,
			'yday_now'  => 0.0,
			'yday_n'    => 0,
			'week'      => 0.0,
			'week_n'    => 0,
			'prev_week' => 0.0,
			'month'     => 0.0,
			'month_n'   => 0,
			'refunds'   => 0.0,
		);
		$included_30 = array();

		foreach ( $orders as $o ) {
			$status = self::strip( $o['status'] );
			if ( in_array( $status, $excluded, true ) ) {
				continue;
			}
			$ts    = strtotime( $o['d'] . ' UTC' );
			$value = (float) $o['total'];
			$day   = wp_date( 'Y-m-d', $ts );
			if ( isset( $days[ $day ] ) ) {
				$days[ $day ]['value'] += $value;
				++$days[ $day ]['count'];
			}
			if ( $day === $k_today ) {
				$sum['today'] += $value;
				++$sum['today_n'];
			}
			if ( $day === $k_yesterday && $ts <= $y_cut ) {
				$sum['yday_now'] += $value;
				++$sum['yday_n'];
			}
			if ( $ts >= $w0 ) {
				$sum['week'] += $value;
				++$sum['week_n'];
			} elseif ( $ts >= $w1 ) {
				$sum['prev_week'] += $value;
			}
			if ( $ts >= $m0 ) {
				$sum['month'] += $value;
				++$sum['month_n'];
			}
			if ( $ts >= $d30 ) {
				$included_30[] = (int) $o['id'];
			}
		}

		foreach ( $refunds as $r ) {
			if ( in_array( self::strip( $r['parent_status'] ), $excluded, true ) ) {
				continue; // The order itself is already excluded.
			}
			$ts    = strtotime( $r['d'] . ' UTC' );
			$value = -abs( (float) $r['amount'] );
			$day   = wp_date( 'Y-m-d', $ts );
			if ( isset( $days[ $day ] ) ) {
				$days[ $day ]['value'] += $value;
			}
			if ( $day === $k_today ) {
				$sum['today'] += $value;
			}
			if ( $day === $k_yesterday && $ts <= $y_cut ) {
				$sum['yday_now'] += $value;
			}
			if ( $ts >= $w0 ) {
				$sum['week'] += $value;
			} elseif ( $ts >= $w1 ) {
				$sum['prev_week'] += $value;
			}
			if ( $ts >= $m0 ) {
				$sum['month'] += $value;
				$sum['refunds'] += $value;
			}
		}

		return array(
			'generated' => time(),
			'sum'       => $sum,
			'days'      => $days,
			'statuses'  => self::status_counts(),
			'top'       => self::top_products( $included_30 ),
			'stock'     => self::stock(),
			'recent'    => self::recent_orders(),
			'hpos'      => self::hpos(),
		);
	}

	private static function strip( $status ) {
		return 0 === strpos( (string) $status, 'wc-' ) ? substr( $status, 3 ) : (string) $status;
	}

	private static function orders_since( $from_gmt ) {
		global $wpdb;
		if ( self::hpos() ) {
			$t = $wpdb->prefix . 'wc_orders';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare( "SELECT id, status, total_amount AS total, date_created_gmt AS d FROM {$t} WHERE type = 'shop_order' AND date_created_gmt >= %s", $from_gmt );
		} else {
			$sql = $wpdb->prepare(
				"SELECT p.ID AS id, p.post_status AS status, pm.meta_value AS total, p.post_date_gmt AS d
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_order_total'
				 WHERE p.post_type = 'shop_order' AND p.post_date_gmt >= %s",
				$from_gmt
			);
		}
		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function refunds_since( $from_gmt ) {
		global $wpdb;
		if ( self::hpos() ) {
			$t   = $wpdb->prefix . 'wc_orders';
			$sql = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT r.total_amount AS amount, r.date_created_gmt AS d, o.status AS parent_status
				 FROM {$t} r INNER JOIN {$t} o ON o.id = r.parent_order_id
				 WHERE r.type = 'shop_order_refund' AND r.date_created_gmt >= %s",
				$from_gmt
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT pm.meta_value AS amount, r.post_date_gmt AS d, o.post_status AS parent_status
				 FROM {$wpdb->posts} r
				 INNER JOIN {$wpdb->posts} o ON o.ID = r.post_parent
				 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = r.ID AND pm.meta_key = '_refund_amount'
				 WHERE r.post_type = 'shop_order_refund' AND r.post_date_gmt >= %s",
				$from_gmt
			);
		}
		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Count of every order by status (all time). */
	private static function status_counts() {
		global $wpdb;
		if ( self::hpos() ) {
			$t    = $wpdb->prefix . 'wc_orders';
			$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$t} WHERE type = 'shop_order' GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results( "SELECT post_status AS status, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type = 'shop_order' GROUP BY post_status", ARRAY_A );
		}
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ self::strip( $r['status'] ) ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Best sellers by quantity among included orders of the last 30 days.
	 *
	 * @param int[] $order_ids Included order IDs.
	 */
	private static function top_products( $order_ids ) {
		global $wpdb;
		if ( ! $order_ids ) {
			return array();
		}
		$agg = array();
		foreach ( array_chunk( $order_ids, 500 ) as $chunk ) {
			$in   = implode( ',', array_map( 'intval', $chunk ) );
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT oi.order_item_name AS name, pid.meta_value AS pid, qty.meta_value AS qty, lt.meta_value AS total
				 FROM {$wpdb->prefix}woocommerce_order_items oi
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_qty'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta lt ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
				 WHERE oi.order_item_type = 'line_item' AND oi.order_id IN ({$in})",
				ARRAY_A
			);
			foreach ( (array) $rows as $r ) {
				$id = (int) $r['pid'];
				$k  = $id ? 'p' . $id : 'n' . md5( $r['name'] );
				if ( ! isset( $agg[ $k ] ) ) {
					$agg[ $k ] = array(
						'id'    => $id,
						'name'  => (string) $r['name'],
						'qty'   => 0.0,
						'total' => 0.0,
					);
				}
				$agg[ $k ]['qty']   += (float) $r['qty'];
				$agg[ $k ]['total'] += (float) $r['total'];
			}
		}
		uasort(
			$agg,
			static function ( $a, $b ) {
				return $b['qty'] <=> $a['qty'] ?: $b['total'] <=> $a['total'];
			}
		);
		return array_slice( array_values( $agg ), 0, 5 );
	}

	/** Out-of-stock count and the lowest managed stock items. */
	private static function stock() {
		global $wpdb;
		$global_low = max( 0, (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ) );
		$t          = $wpdb->prefix . 'wc_product_meta_lookup';

		$suppress = $wpdb->suppress_errors( true );
		$rows     = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT p.ID AS id, p.post_title AS name, p.post_type AS type, p.post_parent AS parent, l.stock_status AS status, l.stock_quantity AS qty, ls.meta_value AS low
			 FROM {$t} l
			 INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
			 LEFT JOIN {$wpdb->postmeta} ls ON ls.post_id = p.ID AND ls.meta_key = '_low_stock_amount'
			 WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')",
			ARRAY_A
		);
		$failed = '' !== $wpdb->last_error;
		$wpdb->suppress_errors( $suppress );

		if ( $failed ) {
			// Lookup table missing: read the same fields from post meta.
			$rows = $wpdb->get_results(
				"SELECT p.ID AS id, p.post_title AS name, p.post_type AS type, p.post_parent AS parent, ss.meta_value AS status,
				        IF(ms.meta_value = 'yes', st.meta_value, NULL) AS qty, ls.meta_value AS low
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} ss ON ss.post_id = p.ID AND ss.meta_key = '_stock_status'
				 LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_stock'
				 LEFT JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = '_manage_stock'
				 LEFT JOIN {$wpdb->postmeta} ls ON ls.post_id = p.ID AND ls.meta_key = '_low_stock_amount'
				 WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')",
				ARRAY_A
			);
		}

		$out_n = 0;
		$low   = array();
		$out   = array();
		foreach ( (array) $rows as $r ) {
			if ( 'product' === $r['type'] && 'outofstock' === $r['status'] ) {
				++$out_n;
				$out[] = array(
					'id'   => (int) $r['id'],
					'name' => $r['name'],
				);
			}
			if ( null === $r['qty'] || '' === $r['qty'] ) {
				continue;
			}
			$qty       = (float) $r['qty'];
			$threshold = ( null !== $r['low'] && '' !== $r['low'] ) ? (float) $r['low'] : $global_low;
			if ( $qty > 0 && $qty <= $threshold ) {
				$low[] = array(
					'id'     => (int) ( 'product_variation' === $r['type'] ? $r['parent'] : $r['id'] ),
					'name'   => $r['name'],
					'qty'    => $qty,
					'thresh' => $threshold,
				);
			}
		}
		usort(
			$low,
			static function ( $a, $b ) {
				return $a['qty'] <=> $b['qty'];
			}
		);
		return array(
			'out_n'     => $out_n,
			'out'       => array_slice( $out, 0, 6 ),
			'low_n'     => count( $low ),
			'low'       => array_slice( $low, 0, 6 ),
			'threshold' => $global_low,
		);
	}

	private static function recent_orders() {
		$orders = wc_get_orders(
			array(
				'limit'   => 6,
				'type'    => 'shop_order',
				'orderby' => 'date',
				'order'   => 'DESC',
				'status'  => array_keys( wc_get_order_statuses() ),
			)
		);
		$out = array();
		foreach ( $orders as $o ) {
			$name = trim( $o->get_formatted_billing_full_name() );
			if ( '' === $name ) {
				$name = $o->get_billing_company() ? $o->get_billing_company() : __( 'Guest', 'rar-admin-studio' );
			}
			$created = $o->get_date_created();
			$out[]   = array(
				'id'     => $o->get_id(),
				'number' => $o->get_order_number(),
				'status' => $o->get_status(),
				'total'  => (float) $o->get_total(),
				'name'   => $name,
				'ts'     => $created ? $created->getTimestamp() : 0,
			);
		}
		return $out;
	}
}
