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

/**
 * Purge OVH/CDN optimization assets before API credentials are removed.
 *
 * Best-effort: failures must not block local cleanup (age GC is the fallback).
 */
function cacherocket_uninstall_purge_remote() {
	$api_key    = get_option( 'cacherocket_api_key' );
	$api_secret = get_option( 'cacherocket_api_secret' );
	if ( ! $api_key || ! $api_secret ) {
		return;
	}

	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$site_key = is_string( $host ) ? strtolower( $host ) : 'site';

	$base = defined( 'CACHEROCKET_API_BASE' ) ? CACHEROCKET_API_BASE : 'https://api.cacherocket.com/web/v1/wordpress';
	$url  = untrailingslashit( (string) $base ) . '/purgeOptimizationAssets';

	$body = array(
		'publicKey' => (string) $api_key,
		'secretKey' => (string) $api_secret,
		'siteKey'   => $site_key,
		'kinds'     => array( 'imageOpt', 'lqip', 'criticalCss' ),
	);

	$org = get_option( 'cacherocket_organization_id', '' );
	if ( is_string( $org ) && '' !== $org && 'personal' !== $org ) {
		$body['organizationId'] = $org;
	}

	wp_remote_post(
		$url,
		array(
			'headers'  => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => 'CacheRocket-WordPress-Uninstall',
				'Accept'       => 'application/json',
			),
			'body'     => wp_json_encode( $body ),
			'timeout'  => 45,
			'blocking' => true,
		)
	);
}

cacherocket_uninstall_purge_remote();

// Drop cloud image / LQIP mappings left on attachments.
delete_post_meta_by_key( '_cacherocket_image_opt' );
delete_post_meta_by_key( '_cacherocket_lqip' );

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
	'cacherocket_organization_id',
	'cacherocket_ccss_map',
	'cacherocket_pagespeed_last',
	'cacherocket_opt_backfill_cursor',
	'cacherocket_opt_backfill_done',
	'cacherocket_opt_lock_gen',
);

foreach ( $cacherocket_options as $cacherocket_option ) {
	delete_option( $cacherocket_option );
}

delete_transient( 'cacherocket_plan_data' );
delete_transient( 'cacherocket_wc_empty_fragments' );
delete_transient( 'cacherocket_heartbeat_sent' );
delete_transient( 'cacherocket_pending_opt_jobs' );

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
