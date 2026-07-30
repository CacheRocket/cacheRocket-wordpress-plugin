<?php
/**
 * Uninstall CacheRocket.
 *
 * Fired when the plugin is deleted from WordPress admin.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cacherocket_options = array(
	'cacherocket_api_key',
	'cacherocket_api_secret',
	'cacherocket_cache_enabled',
	'cacherocket_cache_delivery',
	'cacherocket_cache_woocommerce',
	'cacherocket_cache_ttl',
	'cacherocket_warm_on_publish',
	'cacherocket_settings',
	'cacherocket_settings_backup',
	'cacherocket_last_plan',
	'cacherocket_plan_sync_error',
	'cacherocket_lcp_map',
	'cacherocket_last_heartbeat',
	'cacherocket_site_warmer_id',
);

foreach ( $cacherocket_options as $cacherocket_option ) {
	delete_option( $cacherocket_option );
}

delete_transient( 'cacherocket_plan_data' );
delete_transient( 'cacherocket_wc_empty_fragments' );
delete_transient( 'cacherocket_heartbeat_sent' );

/**
 * Recursively remove a directory.
 *
 * @param string $dir Absolute directory path.
 */
function cacherocket_uninstall_rmdir( $dir ) {
	$dir = untrailingslashit( $dir );
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			cacherocket_uninstall_rmdir( $path );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall context; WP_Filesystem may be unavailable.
			rmdir( $path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- uninstall context.
			unlink( $path );
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall context.
	rmdir( $dir );
}

$cacherocket_cache_dir = trailingslashit( WP_CONTENT_DIR ) . 'cache/cacherocket';
if ( is_dir( $cacherocket_cache_dir ) ) {
	cacherocket_uninstall_rmdir( $cacherocket_cache_dir );
}

$cacherocket_dropin = trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
if ( is_readable( $cacherocket_dropin ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- uninstall context.
	$cacherocket_contents = file_get_contents( $cacherocket_dropin );
	if ( is_string( $cacherocket_contents ) && false !== strpos( $cacherocket_contents, 'CacheRocket advanced-cache' ) ) {
		wp_delete_file( $cacherocket_dropin );
	}
}

// Remove .htaccess markers if possible.
if ( file_exists( ABSPATH . 'wp-admin/includes/misc.php' ) ) {
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( function_exists( 'insert_with_markers' ) && function_exists( 'get_home_path' ) ) {
		$cacherocket_htaccess = get_home_path() . '.htaccess';
		if ( file_exists( $cacherocket_htaccess ) ) {
			insert_with_markers( $cacherocket_htaccess, 'CacheRocket', array() );
		}
	}
}
