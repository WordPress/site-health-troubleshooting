<?php
/**
 * Perform plugin installation routines.
 *
 * @package Health Check
 */

// Make sure the uninstall file can't be accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

global $wpdb;

// Remove options introduced by the plugin.
\delete_option( 'health-check-disable-plugin-hash' );
\delete_option( 'health-check-default-theme' );
\delete_option( 'health-check-current-theme' );
\delete_option( 'health-check-dashboard-notices' );

/*
 * Remove any user meta entries we made, done with a custom query as core
 * does not provide an option to clear them for all users.
 */
$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This is a one-time operation, with no need for caching, and no more appropriate functionality available.
	$wpdb->usermeta,
	array(
		'meta_key' => 'health-check', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is a one-time operation, and should not have any noteworthy performance impact.
	)
); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is a one-time operation, and should not have any noteworthy performance impact.

// Remove the Must-Use plugin, if it has been added.
if ( file_exists( \trailingslashit( WPMU_PLUGIN_DIR ) . 'troubleshooting-mode.php' ) ) {
	\wp_delete_file( \trailingslashit( WPMU_PLUGIN_DIR ) . 'troubleshooting-mode.php' );
}
