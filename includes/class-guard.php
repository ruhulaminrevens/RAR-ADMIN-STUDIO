<?php
/**
 * Sign-in protection: pauses sign-in after repeated wrong passwords.
 *
 * Two counters per failed attempt, both stored in one small table and updated
 * with a single atomic INSERT … ON DUPLICATE KEY UPDATE:
 *   u  = this network (IP) + this username   → limit "guard_max"
 *   i  = this network (IP), any username      → limit "guard_ip_max"
 * Because every key contains the IP, nobody can lock a real user out from another network.
 *
 * @package RAR_Admin_Studio
 */

defined( 'ABSPATH' ) || exit;

class RAR_AS_Guard {

	const DB_VERSION = '1';
	const CRON       = 'rar_as_guard_cleanup';

	/** Cloudflare edge ranges (https://www.cloudflare.com/ips/). */
	const CF_RANGES = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
		'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
		'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
		'2a06:98c0::/29', '2c0f:f248::/32',
	);

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rar_as_login';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
				id_hash char(64) NOT NULL,
				kind char(1) NOT NULL DEFAULT 'u',
				label varchar(191) NOT NULL DEFAULT '',
				ip varchar(45) NOT NULL DEFAULT '',
				fails smallint(5) unsigned NOT NULL DEFAULT 0,
				first_at int(10) unsigned NOT NULL DEFAULT 0,
				last_at int(10) unsigned NOT NULL DEFAULT 0,
				locked_until int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id_hash),
				KEY last_at (last_at)
			) {$charset};"
		);
		update_option( 'rar_as_db_version', self::DB_VERSION, true );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( 'rar_as_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		add_action( 'wp_login', array( __CLASS__, 'on_success' ), 10, 2 );
		if ( ! RAR_AS_Settings::get( 'guard_enabled' ) ) {
			return;
		}
		add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 99, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_failed' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ IP */

	public static function ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			$remote = '0.0.0.0';
		}
		if ( 'cloudflare' === RAR_AS_Settings::get( 'ip_source' ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && self::in_ranges( $remote, self::CF_RANGES ) ) {
			$cf = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
			if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
				return $cf;
			}
		}
		return $remote;
	}

	public static function in_ranges( $ip, $ranges ) {
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $bin ) {
			return false;
		}
		foreach ( $ranges as $cidr ) {
			list( $net, $bits ) = explode( '/', $cidr );
			$nb = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false === $nb || strlen( $nb ) !== strlen( $bin ) ) {
				continue;
			}
			$bits  = (int) $bits;
			$bytes = intdiv( $bits, 8 );
			if ( substr( $bin, 0, $bytes ) !== substr( $nb, 0, $bytes ) ) {
				continue;
			}
			$rem = $bits % 8;
			if ( 0 === $rem ) {
				return true;
			}
			$mask = chr( ( 0xFF << ( 8 - $rem ) ) & 0xFF );
			if ( ( $bin[ $bytes ] & $mask ) === ( $nb[ $bytes ] & $mask ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------- Keys */

	private static function norm_user( $username ) {
		$u = strtolower( trim( (string) $username ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $u, 0, 100 ) : substr( $u, 0, 100 );
	}

	private static function key_user( $ip, $username ) {
		return hash( 'sha256', 'u|' . $ip . '|' . self::norm_user( $username ) );
	}

	private static function key_ip( $ip ) {
		return hash( 'sha256', 'i|' . $ip );
	}

	/**
	 * Rows for the two counters of this attempt.
	 *
	 * @return array{u:?object,i:?object}
	 */
	private static function rows( $ip, $username ) {
		global $wpdb;
		$t  = self::table();
		$ku = self::key_user( $ip, $username );
		$ki = self::key_ip( $ip );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id_hash, fails, first_at, locked_until FROM {$t} WHERE id_hash IN (%s, %s)", $ku, $ki ) );
		$out  = array(
			'u' => null,
			'i' => null,
		);
		foreach ( (array) $rows as $r ) {
			$out[ $r->id_hash === $ku ? 'u' : 'i' ] = $r;
		}
		return $out;
	}

	/** Seconds until this IP/username may try again (0 = not paused). */
	public static function lock_remaining( $ip, $username ) {
		$now  = time();
		$rows = self::rows( $ip, $username );
		$left = 0;
		foreach ( $rows as $r ) {
			if ( $r && (int) $r->locked_until > $now ) {
				$left = max( $left, (int) $r->locked_until - $now );
			}
		}
		return $left;
	}

	/* ------------------------------------------------------------- Hooks */

	/**
	 * Runs after WordPress (and other plugins) checked the password.
	 *
	 * @param WP_User|WP_Error|null $user     Result so far.
	 * @param string                $username Submitted username/email.
	 * @param string                $password Submitted password.
	 */
	public static function authenticate( $user, $username, $password ) {
		if ( '' === trim( (string) $username ) ) {
			return $user;
		}
		$ip   = self::ip();
		$left = self::lock_remaining( $ip, $username );
		if ( $left > 0 ) {
			$mins = max( 1, (int) ceil( $left / 60 ) );
			return new WP_Error(
				'rar_as_locked',
				sprintf(
					/* translators: %d: minutes */
					_n( 'Sign-in is paused after too many wrong passwords. Try again in %d minute.', 'Sign-in is paused after too many wrong passwords. Try again in %d minutes.', $mins, 'rar-admin-studio' ),
					$mins
				)
			);
		}

		if ( is_wp_error( $user ) && RAR_AS_Settings::get( 'generic_errors' ) ) {
			$generic = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed' );
			if ( array_intersect( $user->get_error_codes(), $generic ) ) {
				$msg  = __( 'The username or password is incorrect.', 'rar-admin-studio' );
				$rows = self::rows( $ip, $username );
				$max  = (int) RAR_AS_Settings::get( 'guard_max' );
				$now  = time();
				$win  = (int) RAR_AS_Settings::get( 'guard_window' ) * 60;
				$used = ( $rows['u'] && (int) $rows['u']->first_at >= $now - $win ) ? (int) $rows['u']->fails : 0;
				$rest = $max - ( $used + 1 ); // This attempt is counted right after.
				if ( $rest > 0 && $rest <= 2 ) {
					$msg .= ' ' . sprintf(
						/* translators: 1: attempts left, 2: minutes */
						_n( '%1$d attempt left before sign-in pauses for %2$d minutes.', '%1$d attempts left before sign-in pauses for %2$d minutes.', $rest, 'rar-admin-studio' ),
						$rest,
						(int) RAR_AS_Settings::get( 'guard_lock' )
					);
				} elseif ( $rest <= 0 ) {
					$msg .= ' ' . sprintf(
						/* translators: %d: minutes */
						__( 'Sign-in is now paused for %d minutes.', 'rar-admin-studio' ),
						(int) RAR_AS_Settings::get( 'guard_lock' )
					);
				}
				$err = new WP_Error( 'rar_as_invalid', $msg );
				return $err;
			}
		}
		return $user;
	}

	/**
	 * Count a failed attempt.
	 *
	 * @param string        $username Username or e-mail that was tried.
	 * @param WP_Error|null $error    Error (WP 5.4+).
	 */
	public static function on_failed( $username, $error = null ) {
		if ( $error instanceof WP_Error ) {
			$skip = array( 'rar_as_locked', 'empty_username', 'empty_password' );
			if ( array_intersect( $error->get_error_codes(), $skip ) ) {
				return;
			}
		}
		if ( '' === trim( (string) $username ) ) {
			return;
		}
		$ip  = self::ip();
		$now = time();
		$win = $now - (int) RAR_AS_Settings::get( 'guard_window' ) * 60;
		$lck = $now + (int) RAR_AS_Settings::get( 'guard_lock' ) * 60;

		self::bump( self::key_user( $ip, $username ), 'u', sanitize_user( self::norm_user( $username ), false ), $ip, $now, $win, $lck, (int) RAR_AS_Settings::get( 'guard_max' ) );
		self::bump( self::key_ip( $ip ), 'i', '', $ip, $now, $win, $lck, (int) RAR_AS_Settings::get( 'guard_ip_max' ) );
	}

	private static function bump( $key, $kind, $label, $ip, $now, $win, $lock_until, $max ) {
		global $wpdb;
		$t = self::table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$t} (id_hash, kind, label, ip, fails, first_at, last_at, locked_until)
				 VALUES (%s, %s, %s, %s, 1, %d, %d, 0)
				 ON DUPLICATE KEY UPDATE
				   fails = IF(first_at < %d, 1, fails + 1),
				   first_at = IF(first_at < %d, %d, first_at),
				   last_at = %d",
				$key,
				$kind,
				$label,
				$ip,
				$now,
				$now,
				$win,
				$win,
				$now,
				$now
			)
		);
		$fails = (int) $wpdb->get_var( $wpdb->prepare( "SELECT fails FROM {$t} WHERE id_hash = %s", $key ) );
		if ( $fails >= $max ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET locked_until = %d, fails = 0, first_at = %d WHERE id_hash = %s", $lock_until, $now, $key ) );
			/**
			 * Fires when sign-in is paused for a network/username.
			 *
			 * @param string $kind 'u' (network + username) or 'i' (whole network).
			 * @param string $ip   IP address.
			 */
			do_action( 'rar_as_login_locked', $kind, $ip );
		}
		// phpcs:enable
	}

	/**
	 * Successful sign-in: clear this network's counter for the user and remember the sign-in.
	 *
	 * @param string  $login User login.
	 * @param WP_User $user  User.
	 */
	public static function on_success( $login, $user ) {
		global $wpdb;
		$ip = self::ip();
		if ( RAR_AS_Settings::get( 'guard_enabled' ) ) {
			$keys = array( self::key_user( $ip, $login ) );
			if ( $user instanceof WP_User && $user->user_email ) {
				$keys[] = self::key_user( $ip, $user->user_email );
			}
			$t = self::table();
			foreach ( $keys as $k ) {
				$wpdb->delete( $t, array( 'id_hash' => $k ), array( '%s' ) );
			}
		}
		if ( RAR_AS_Settings::get( 'record_login' ) && $user instanceof WP_User ) {
			$last = get_user_meta( $user->ID, 'rar_as_last_login', true );
			if ( is_array( $last ) ) {
				update_user_meta( $user->ID, 'rar_as_prev_login', $last );
			}
			update_user_meta(
				$user->ID,
				'rar_as_last_login',
				array(
					't'  => time(),
					'ip' => $ip,
				)
			);
		}
	}

	public static function cleanup() {
		global $wpdb;
		$t   = self::table();
		$now = time();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE last_at < %d AND locked_until < %d", $now - 2 * DAY_IN_SECONDS, $now ) );
	}

	/* ------------------------------------------------------------- Admin */

	public static function recent( $limit = 30 ) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id_hash, kind, label, ip, fails, first_at, last_at, locked_until FROM {$t} ORDER BY last_at DESC LIMIT %d", $limit ) );
	}

	public static function unlock( $key ) {
		global $wpdb;
		if ( preg_match( '/^[a-f0-9]{64}$/', $key ) ) {
			$wpdb->delete( self::table(), array( 'id_hash' => $key ), array( '%s' ) );
		}
	}

	public static function clear_all() {
		global $wpdb;
		$t = self::table();
		$wpdb->query( "DELETE FROM {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
