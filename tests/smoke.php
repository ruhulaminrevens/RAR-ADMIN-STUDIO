<?php
/**
 * RAR Admin Studio smoke tests. Run with:  wp eval-file wp-content/plugins/rar-admin-studio/tests/smoke.php
 * Uses the live database of the site it runs on: run it on a local or staging copy only.
 */
$GLOBALS["pass"] = 0; $GLOBALS["fail"] = 0;
function rar_t( $name, $ok, $extra = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; }
}
RAR_AS_Guard::clear_all();
update_option( 'rar_as_settings', array_merge( RAR_AS_Settings::defaults(), array( 'guard_max' => 5, 'guard_ip_max' => 20 ) ) );
RAR_AS_Settings::flush();

/* ---------- Guard: per network + username ---------- */
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
for ( $i = 1; $i <= 4; $i++ ) {
	$r = wp_authenticate( 'itruhul5', 'wrong-' . $i );
	rar_t( "wrong password $i is rejected with generic message", is_wp_error( $r ) && 'rar_as_invalid' === $r->get_error_code() && false !== strpos( $r->get_error_message(), 'incorrect' ), is_wp_error( $r ) ? $r->get_error_code() : 'user' );
}
$r = wp_authenticate( 'itruhul5', 'wrong-5' );
rar_t( '5th wrong password says sign-in is now paused', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'now paused' ), is_wp_error( $r ) ? $r->get_error_message() : '' );
$r = wp_authenticate( 'itruhul5', 'Admin-Pass-123' );
rar_t( 'correct password is refused while paused', is_wp_error( $r ) && 'rar_as_locked' === $r->get_error_code() );
$r = wp_authenticate( 'nonexistent-user', 'x' );
rar_t( 'unknown username gets the same generic message', is_wp_error( $r ) && 'rar_as_invalid' === $r->get_error_code() );
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
$r = wp_authenticate( 'itruhul5', 'Admin-Pass-123' );
rar_t( 'same user from another network can still sign in', $r instanceof WP_User );
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$r = wp_authenticate( 'nabiadoffice', 'Shop-Pass-123' );
rar_t( 'another user from the paused network can still sign in', $r instanceof WP_User );
$r = wp_authenticate( 'itruhul5', 'wrong-x' );
rar_t( 'attempts during a pause are not counted again', is_wp_error( $r ) && 'rar_as_locked' === $r->get_error_code() );
$rows = RAR_AS_Guard::recent();
$locked = array_filter( $rows, function ( $x ) { return 'u' === $x->kind && (int) $x->locked_until > time(); } );
rar_t( 'exactly one paused network+username row', 1 === count( $locked ), count( $locked ) );
RAR_AS_Guard::unlock( reset( $locked )->id_hash );
$r = wp_authenticate( 'itruhul5', 'Admin-Pass-123' );
rar_t( 'unpause lets the user sign in', $r instanceof WP_User );
rar_t( 'success clears the user counter', 0 === count( array_filter( RAR_AS_Guard::recent(), function ( $x ) { return 'u' === $x->kind && $x->label === 'itruhul5'; } ) ) );

/* ---------- Guard: whole-network limit ---------- */
RAR_AS_Guard::clear_all();
$_SERVER['REMOTE_ADDR'] = '192.0.2.55';
for ( $i = 0; $i < 20; $i++ ) { wp_authenticate( 'guess' . $i, 'x' ); }
$r = wp_authenticate( 'nabiadoffice', 'Shop-Pass-123' );
rar_t( 'network pause after 20 wrong passwords on any usernames', is_wp_error( $r ) && 'rar_as_locked' === $r->get_error_code() );
RAR_AS_Guard::clear_all();
$r = wp_authenticate( 'nabiadoffice', 'Shop-Pass-123' );
rar_t( 'clear all removes the network pause', $r instanceof WP_User );

/* ---------- Guard: window expiry ---------- */
global $wpdb;
$_SERVER['REMOTE_ADDR'] = '192.0.2.60';
for ( $i = 0; $i < 4; $i++ ) { wp_authenticate( 'itruhul5', 'x' ); }
$wpdb->query( "UPDATE {$wpdb->prefix}rar_as_login SET first_at = first_at - 3600" );
wp_authenticate( 'itruhul5', 'x' );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT fails, locked_until FROM {$wpdb->prefix}rar_as_login WHERE kind='u' AND ip=%s", '192.0.2.60' ) );
rar_t( 'counter restarts after the counting window', $row && 1 === (int) $row->fails && 0 === (int) $row->locked_until, json_encode( $row ) );
RAR_AS_Guard::clear_all();

/* ---------- Guard disabled ---------- */
$s = RAR_AS_Settings::all(); $s['guard_enabled'] = 0; $s['generic_errors'] = 1; update_option( 'rar_as_settings', $s ); RAR_AS_Settings::flush();
rar_t( 'settings change visible', 0 === (int) RAR_AS_Settings::get( 'guard_enabled' ) );
$s['guard_enabled'] = 1; update_option( 'rar_as_settings', $s ); RAR_AS_Settings::flush();

/* ---------- Cloudflare IP ---------- */
$s['ip_source'] = 'cloudflare'; update_option( 'rar_as_settings', $s ); RAR_AS_Settings::flush();
$_SERVER['REMOTE_ADDR'] = '172.70.1.2'; $_SERVER['HTTP_CF_CONNECTING_IP'] = '103.4.5.6';
rar_t( 'Cloudflare header trusted from a Cloudflare address', '103.4.5.6' === RAR_AS_Guard::ip() );
$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
rar_t( 'Cloudflare header ignored from other addresses', '8.8.8.8' === RAR_AS_Guard::ip() );
$_SERVER['REMOTE_ADDR'] = '2606:4700:10::6816:1'; $_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:db8::1';
rar_t( 'Cloudflare IPv6 range works', '2001:db8::1' === RAR_AS_Guard::ip() );
$_SERVER['REMOTE_ADDR'] = '172.70.1.2'; $_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
rar_t( 'invalid Cloudflare header falls back', '172.70.1.2' === RAR_AS_Guard::ip() );
unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
$s['ip_source'] = 'remote'; update_option( 'rar_as_settings', $s ); RAR_AS_Settings::flush();

/* ---------- Settings sanitising ---------- */
$clean = RAR_AS_Settings::sanitize( array(
	'brand_name' => '<script>alert(1)</script>Nabiad', 'color_primary' => 'red; background:url(x)', 'color_sidebar' => '#123abc',
	'login_headline' => '<b>Hi</b>', 'custom_links' => "Staff app | /staff/\nBad | javascript:alert(1)\nEvil | //evil.example\nOrders | admin.php?page=wc-orders\nX | ../../wp-config.php",
	'menu_hide' => array( 'administrator' => array( 'index.php' ), 'shop_manager' => array( 'edit.php', 'tools.php', '"><script>' ), 'nosuchrole' => array( 'x' ) ),
	'login_redirect' => array( 'shop_manager' => 'https://evil.example/', 'editor' => 'admin.php?page=wc-orders' ),
	'guard_max' => '1', 'guard_lock' => '999999', 'excluded_screens' => "toplevel_page_x\n<bad>\nproduct",
) );
rar_t( 'brand name tags stripped', false === strpos( $clean['brand_name'], '<' ) );
rar_t( 'bad colour falls back to default', '#0E7A4E' === $clean['color_primary'] );
rar_t( 'good colour kept', '#123ABC' === $clean['color_sidebar'] );
rar_t( 'custom links keep only safe targets', "Staff app | /staff/\nOrders | admin.php?page=wc-orders" === $clean['custom_links'], json_encode( $clean['custom_links'] ) );
rar_t( 'administrator menus can never be hidden', ! isset( $clean['menu_hide']['administrator'] ) );
rar_t( 'unknown roles dropped', ! isset( $clean['menu_hide']['nosuchrole'] ) );
rar_t( 'unsafe menu slug dropped', array( 'edit.php', 'tools.php' ) === $clean['menu_hide']['shop_manager'], json_encode( $clean['menu_hide'] ) );
rar_t( 'off-site landing page rejected', ! isset( $clean['login_redirect']['shop_manager'] ) );
rar_t( 'admin landing page kept', 'admin.php?page=wc-orders' === $clean['login_redirect']['editor'] );
rar_t( 'limits clamped', 3 === $clean['guard_max'] && 1440 === $clean['guard_lock'] );
rar_t( 'screen ids filtered', "toplevel_page_x\nproduct" === $clean['excluded_screens'] );
rar_t( 'sanitize is idempotent', $clean == RAR_AS_Settings::sanitize( $clean ), json_encode( array_diff_assoc( array_map( 'json_encode', $clean ), array_map( 'json_encode', RAR_AS_Settings::sanitize( $clean ) ) ) ) );

/* ---------- Helpers ---------- */
rar_t( 'BDT lakh grouping', '৳ 1,23,45,679' === str_replace( "\u{00A0}", ' ', RAR_AS_Helpers::money( 12345678.5 ) ), RAR_AS_Helpers::money( 12345678.5 ) );
rar_t( 'BDT small amount', '৳ 999' === str_replace( "\u{00A0}", ' ', RAR_AS_Helpers::money( 999 ) ) );
rar_t( 'negative with decimals', '−৳ 1,500.26' === str_replace( "\u{00A0}", ' ', RAR_AS_Helpers::money( -1500.256, true ) ) );
rar_t( 'readable primary keeps 4.5:1 for white text', RAR_AS_Helpers::contrast( RAR_AS_Helpers::readable_primary( '#7CF2B0' ), '#FFFFFF' ) >= 4.5 );
rar_t( 'menu request matching: plain page', true === ( function () { $_GET = array( 'page' => 'elementor' ); return RAR_AS_Menus::matches_request( 'elementor', 'admin.php' ); } )() );
rar_t( 'menu request matching: edit.php only for posts', false === ( function () { $_GET = array( 'post_type' => 'product' ); return RAR_AS_Menus::matches_request( 'edit.php', 'edit.php' ); } )() );
rar_t( 'menu request matching: post type list', true === ( function () { $_GET = array( 'post_type' => 'cms_block' ); return RAR_AS_Menus::matches_request( 'edit.php?post_type=cms_block', 'edit.php' ); } )() );
$_GET = array();

/* ---------- Metrics (reconciled against the WooCommerce order API) ---------- */
$m = RAR_AS_Metrics::get( true );
$tz = wp_timezone(); $today = new DateTimeImmutable( 'today', $tz ); $month = $today->modify( 'first day of this month' );
$excl = RAR_AS_Metrics::excluded_statuses();
$t = $mo = 0.0; $tn = 0;
foreach ( wc_get_orders( array( 'limit' => -1, 'type' => 'shop_order', 'status' => array_keys( wc_get_order_statuses() ) ) ) as $o ) {
	if ( in_array( $o->get_status(), $excl, true ) ) { continue; }
	$ts = $o->get_date_created()->getTimestamp(); $v = (float) $o->get_total();
	if ( $ts >= $today->getTimestamp() ) { $t += $v; $tn++; }
	if ( $ts >= $month->getTimestamp() ) { $mo += $v; }
	foreach ( $o->get_refunds() as $r ) {
		$rts = $r->get_date_created()->getTimestamp();
		if ( $rts >= $today->getTimestamp() ) { $t -= abs( (float) $r->get_amount() ); }
		if ( $rts >= $month->getTimestamp() ) { $mo -= abs( (float) $r->get_amount() ); }
	}
}
rar_t( 'today booked sales match the order API (hpos=' . (int) $m['hpos'] . ')', abs( $m['sum']['today'] - $t ) < 0.01, $m['sum']['today'] . ' vs ' . $t );
rar_t( 'today order count matches', $m['sum']['today_n'] === $tn );
rar_t( 'month booked sales match the order API', abs( $m['sum']['month'] - $mo ) < 0.01, $m['sum']['month'] . ' vs ' . $mo );
$counts = array();
foreach ( wc_get_orders( array( 'limit' => -1, 'type' => 'shop_order', 'status' => array_keys( wc_get_order_statuses() ), 'return' => 'ids' ) ) as $id ) { $s2 = wc_get_order( $id )->get_status(); $counts[ $s2 ] = ( isset( $counts[ $s2 ] ) ? $counts[ $s2 ] : 0 ) + 1; }
ksort( $counts ); $mc = $m['statuses']; ksort( $mc );
rar_t( 'status counts match', $counts == $mc, json_encode( $counts ) . ' vs ' . json_encode( $mc ) );
rar_t( '14 days in chart', 14 === count( $m['days'] ) );
$ver = (int) get_option( 'rar_as_metrics_ver' ); do_action( 'woocommerce_product_set_stock', wc_get_product( 38 ) );
rar_t( 'stock change clears the cache', (int) get_option( 'rar_as_metrics_ver' ) === $ver + 1 );

echo "\n" . $GLOBALS["pass"] . " passed, " . $GLOBALS["fail"] . " failed\n";
