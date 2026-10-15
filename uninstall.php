<?php
/**
 * Removes everything RAR Admin Studio stored when the plugin is deleted from Plugins.
 *
 * @package RAR_Admin_Studio
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'rar_as_settings' );
delete_option( 'rar_as_db_version' );
delete_option( 'rar_as_menu_snapshot' );
delete_option( 'rar_as_metrics_ver' );

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rar_as_login" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange

delete_metadata( 'user', 0, 'rar_as_last_login', '', true );
delete_metadata( 'user', 0, 'rar_as_prev_login', '', true );

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_rar\\_as\\_m\\_%' OR option_name LIKE '\\_transient\\_timeout\\_rar\\_as\\_m\\_%'" );

wp_clear_scheduled_hook( 'rar_as_guard_cleanup' );
