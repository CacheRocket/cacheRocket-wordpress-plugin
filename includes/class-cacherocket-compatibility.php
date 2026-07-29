<?php
/**
 * Detect conflicting page-cache plugins and disable CacheRocket page caching.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Compatibility helpers for CacheRocket page caching.
 */
class CacheRocket_Compatibility {

	/**
	 * Known page-cache plugins: plugin basename => display name.
	 *
	 * @var array<string, string>
	 */
	private static $page_cache_plugins = array(
		'wp-rocket/wp-rocket.php'                         => 'WP Rocket',
		'w3-total-cache/w3-total-cache.php'               => 'W3 Total Cache',
		'wp-super-cache/wp-cache.php'                     => 'WP Super Cache',
		'litespeed-cache/litespeed-cache.php'             => 'LiteSpeed Cache',
		'wp-fastest-cache/wpFastestCache.php'             => 'WP Fastest Cache',
		'cache-enabler/cache-enabler.php'                 => 'Cache Enabler',
		'hummingbird-performance/wp-hummingbird.php'      => 'Hummingbird',
		'sg-cachepress/sg-cachepress.php'                 => 'SiteGround Optimizer',
		'breeze/breeze.php'                               => 'Breeze',
		'nitropack/main.php'                              => 'NitroPack',
		'flying-press/flying-press.php'                   => 'FlyingPress',
		'comet-cache/comet-cache.php'                     => 'Comet Cache',
		'comet-cache-pro/comet-cache-pro.php'             => 'Comet Cache Pro',
		'swift-performance-lite/performance.php'          => 'Swift Performance',
		'swift-performance/performance.php'               => 'Swift Performance',
		'hyper-cache/plugin.php'                          => 'Hyper Cache',
		'wp-optimize/wp-optimize.php'                     => 'WP-Optimize',
		'cachify/cachify.php'                             => 'Cachify',
		'simple-cache/simple-cache.php'                   => 'Simple Cache',
		'wp-cloudflare-page-cache/wp-cloudflare-super-page-cache.php' => 'Super Page Cache for Cloudflare',
	);

	/**
	 * Return active conflicting page-cache plugins.
	 *
	 * @return array<string, string> basename => name
	 */
	public static function get_conflicting_plugins() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = array();
		foreach ( self::$page_cache_plugins as $basename => $name ) {
			if ( is_plugin_active( $basename ) ) {
				$found[ $basename ] = $name;
			}
		}

		/**
		 * Filter detected conflicting page-cache plugins.
		 *
		 * @param array<string, string> $found Active conflicts.
		 */
		return apply_filters( 'cacherocket_conflicting_cache_plugins', $found );
	}

	/**
	 * Whether CacheRocket page caching should stay disabled.
	 *
	 * @return bool
	 */
	public static function is_caching_disabled() {
		$conflicts = self::get_conflicting_plugins();
		$disabled  = ! empty( $conflicts );

		/**
		 * Filter whether CacheRocket page caching is disabled due to compatibility.
		 *
		 * @param bool                  $disabled  Disabled state.
		 * @param array<string, string> $conflicts Detected plugins.
		 */
		return (bool) apply_filters( 'cacherocket_is_page_caching_disabled', $disabled, $conflicts );
	}

	/**
	 * Human-readable conflict summary for admin notices.
	 *
	 * @return string
	 */
	public static function get_conflict_message() {
		$conflicts = self::get_conflicting_plugins();
		if ( empty( $conflicts ) ) {
			return '';
		}

		$names = implode( ', ', array_values( $conflicts ) );
		return sprintf(
			/* translators: %s: comma-separated plugin names */
			__( 'CacheRocket page caching is disabled because another cache plugin is active: %s. Deactivate it to use CacheRocket page caching. Cache warming remains available.', 'cacherocket' ),
			$names
		);
	}
}
