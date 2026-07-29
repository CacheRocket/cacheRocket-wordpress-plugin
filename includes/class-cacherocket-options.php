<?php
/**
 * Centralized CacheRocket settings.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Defaults, getters, and sanitization for plugin options.
 */
class CacheRocket_Options {

	const OPTION_KEY = 'cacherocket_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			// Cache.
			'cache_enabled'           => true,
			'cache_delivery'          => CacheRocket_Cache::DELIVERY_STANDARD,
			'cache_woocommerce'       => false,
			'cache_ttl'               => CacheRocket_Cache::DEFAULT_TTL,
			'cache_mobile'            => false,
			'cache_logged_user'       => false,
			'cache_ssl'               => true,
			'cache_query_strings'     => false,
			'cache_reject_uri'        => "/cart/\n/checkout/\n/my-account/\n/wp-admin/\n/wp-login.php",
			'cache_reject_cookies'    => '',
			'cache_reject_ua'         => '',
			'cache_purge_pages'       => true,
			'cache_purge_home'        => true,

			// File optimization.
			'minify_css'              => false,
			'minify_js'               => false,
			'defer_js'                => false,
			'delay_js'                => false,
			'delay_js_exclusions'     => '',
			'remove_query_strings'    => false,
			'optimize_google_fonts'   => false,

			// Media.
			'lazyload'                => false,
			'lazyload_iframes'        => false,
			'lazyload_youtube'        => false,
			'image_dimensions'        => false,

			// Preload / warming.
			'warm_on_publish'         => true,
			'preload_links'           => false,
			'preload_sitemap'         => false,
			'preload_sitemap_url'     => '',

			// Advanced.
			'cdn'                     => false,
			'cdn_cnames'              => '',
			'cdn_reject_files'        => '',
			'browser_cache'           => false,
			'gzip'                    => false,
			'heartbeat_control'       => false,
			'heartbeat_frequency'     => 60,

			// Account keys stay as separate options for backward compatibility.
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional override default.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Update one or more settings.
	 *
	 * @param array<string, mixed> $partial Partial settings.
	 * @return bool
	 */
	public static function update( $partial ) {
		$current = self::all();
		$merged  = array_merge( $current, is_array( $partial ) ? $partial : array() );
		$clean   = self::sanitize( $merged );
		return update_option( self::OPTION_KEY, $clean, false );
	}

	/**
	 * Sanitize settings. Merges with current values so multi-page forms can submit partial sets.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$current  = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$base  = array_merge( $defaults, $current );
		$input = is_array( $input ) ? $input : array();
		$out   = $base;

		$bools = array(
			'cache_enabled',
			'cache_woocommerce',
			'cache_mobile',
			'cache_logged_user',
			'cache_ssl',
			'cache_query_strings',
			'cache_purge_pages',
			'cache_purge_home',
			'minify_css',
			'minify_js',
			'defer_js',
			'delay_js',
			'remove_query_strings',
			'optimize_google_fonts',
			'lazyload',
			'lazyload_iframes',
			'lazyload_youtube',
			'image_dimensions',
			'warm_on_publish',
			'preload_links',
			'preload_sitemap',
			'cdn',
			'browser_cache',
			'gzip',
			'heartbeat_control',
		);

		foreach ( $bools as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = ! empty( $input[ $key ] );
			}
		}

		if ( array_key_exists( 'cache_delivery', $input ) ) {
			$delivery = (string) $input['cache_delivery'];
			if ( CacheRocket_Cache::DELIVERY_EARLY === $delivery && CacheRocket_Plan::can_use_early_cache() ) {
				$out['cache_delivery'] = CacheRocket_Cache::DELIVERY_EARLY;
			} else {
				$out['cache_delivery'] = CacheRocket_Cache::DELIVERY_STANDARD;
			}
		}

		if ( ! CacheRocket_Plan::can_cache_plugin_pages() ) {
			$out['cache_woocommerce'] = false;
		}

		if ( array_key_exists( 'cache_ttl', $input ) ) {
			$ttl              = (int) $input['cache_ttl'];
			$out['cache_ttl'] = max( 300, min( 604800, $ttl ) );
		}

		if ( array_key_exists( 'heartbeat_frequency', $input ) ) {
			$freq = (int) $input['heartbeat_frequency'];
			$out['heartbeat_frequency'] = in_array( $freq, array( 15, 30, 60, 120 ), true ) ? $freq : 60;
		}

		$textareas = array(
			'cache_reject_uri',
			'cache_reject_cookies',
			'cache_reject_ua',
			'delay_js_exclusions',
			'cdn_cnames',
			'cdn_reject_files',
		);
		foreach ( $textareas as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = self::sanitize_lines( (string) $input[ $key ] );
			}
		}

		if ( array_key_exists( 'preload_sitemap_url', $input ) ) {
			$out['preload_sitemap_url'] = esc_url_raw( trim( (string) $input['preload_sitemap_url'] ) );
		}

		return $out;
	}

	/**
	 * Sanitize multiline text into newline-separated trimmed lines.
	 *
	 * @param string $raw Raw textarea.
	 * @return string
	 */
	public static function sanitize_lines( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		$clean = array();
		foreach ( $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$clean[] = $line;
			}
		}
		return implode( "\n", $clean );
	}

	/**
	 * Split stored multiline option into array.
	 *
	 * @param string $key Setting key.
	 * @return string[]
	 */
	public static function lines( $key ) {
		$raw = (string) self::get( $key, '' );
		if ( '' === $raw ) {
			return array();
		}
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		return is_array( $lines ) ? array_values( array_filter( array_map( 'trim', $lines ) ) ) : array();
	}

	/**
	 * Migrate legacy single options into the settings blob once.
	 */
	public static function maybe_migrate() {
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}

		$migrated = self::defaults();
		$map      = array(
			'cache_enabled'     => CacheRocket_Cache::OPTION_ENABLED,
			'cache_delivery'    => CacheRocket_Cache::OPTION_DELIVERY,
			'cache_woocommerce' => CacheRocket_Cache::OPTION_WOOCOMMERCE,
			'cache_ttl'         => CacheRocket_Cache::OPTION_TTL,
			'warm_on_publish'   => 'cacherocket_warm_on_publish',
		);

		foreach ( $map as $new_key => $old_key ) {
			$val = get_option( $old_key, null );
			if ( null !== $val ) {
				$migrated[ $new_key ] = $val;
			}
		}

		update_option( self::OPTION_KEY, self::sanitize( $migrated ), false );
	}
}
