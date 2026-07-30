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
			'cache_webp'              => false,
			'cache_wc_empty_cart'     => false,

			// File optimization.
			'minify_css'              => false,
			'minify_js'               => false,
			'defer_js'                => false,
			'delay_js'                => false,
			'delay_js_exclusions'     => '',
			'delay_js_pack_analytics' => false,
			'delay_js_pack_ads'       => false,
			'delay_js_pack_chat'      => false,
			'delay_js_pack_maps'      => false,
			'remove_query_strings'    => false,
			'optimize_google_fonts'   => false,
			'self_host_fonts'         => false,
			'remove_emoji'            => false,
			'disable_embeds'          => false,
			'remove_jquery_migrate'   => false,
			'dns_prefetch'            => '',

			// Media.
			'lazyload'                => false,
			'lazyload_iframes'        => false,
			'lazyload_youtube'        => false,
			'lazyload_css_bg'         => false,
			'image_dimensions'        => false,
			'critical_images'         => false,
			'lazy_rendering'          => false,
			'lazy_rendering_selectors'=> "footer\n.site-footer\n#colophon\naside\n.widget-area\n.related-posts",

			// Preload / warming.
			'warm_on_publish'         => true,
			'preload_links'           => false,
			'preload_sitemap'         => false,
			'preload_sitemap_url'     => '',
			'preload_fonts'           => '',

			// Database schedule.
			'db_schedule'             => false,
			'db_schedule_frequency'   => 'weekly',
			'db_schedule_actions'     => "revisions\nauto_drafts\nspam_comments\nexpired_transients",

			// Advanced.
			'cdn'                     => false,
			'cdn_cnames'              => '',
			'cdn_reject_files'        => '',
			'browser_cache'           => false,
			'gzip'                    => false,
			'heartbeat_control'       => false,
			'heartbeat_frequency'     => 60,
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
			'cache_webp',
			'cache_wc_empty_cart',
			'minify_css',
			'minify_js',
			'defer_js',
			'delay_js',
			'delay_js_pack_analytics',
			'delay_js_pack_ads',
			'delay_js_pack_chat',
			'delay_js_pack_maps',
			'remove_query_strings',
			'optimize_google_fonts',
			'self_host_fonts',
			'remove_emoji',
			'disable_embeds',
			'remove_jquery_migrate',
			'lazyload',
			'lazyload_iframes',
			'lazyload_youtube',
			'lazyload_css_bg',
			'image_dimensions',
			'critical_images',
			'lazy_rendering',
			'warm_on_publish',
			'preload_links',
			'preload_sitemap',
			'db_schedule',
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
			$out['cache_delivery'] = ( CacheRocket_Cache::DELIVERY_EARLY === $delivery )
				? CacheRocket_Cache::DELIVERY_EARLY
				: CacheRocket_Cache::DELIVERY_STANDARD;
		}

		if ( array_key_exists( 'cache_ttl', $input ) ) {
			$ttl              = (int) $input['cache_ttl'];
			$out['cache_ttl'] = max( 300, min( 604800, $ttl ) );
		}

		if ( array_key_exists( 'heartbeat_frequency', $input ) ) {
			$freq = (int) $input['heartbeat_frequency'];
			$out['heartbeat_frequency'] = in_array( $freq, array( 15, 30, 60, 120 ), true ) ? $freq : 60;
		}

		if ( array_key_exists( 'db_schedule_frequency', $input ) ) {
			$freq = (string) $input['db_schedule_frequency'];
			$out['db_schedule_frequency'] = in_array( $freq, array( 'daily', 'weekly' ), true ) ? $freq : 'weekly';
		}

		$textareas = array(
			'cache_reject_uri',
			'cache_reject_cookies',
			'cache_reject_ua',
			'delay_js_exclusions',
			'cdn_cnames',
			'cdn_reject_files',
			'dns_prefetch',
			'preload_fonts',
			'lazy_rendering_selectors',
			'db_schedule_actions',
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
	 * Keywords that should never be delayed (manual + packs).
	 *
	 * @return string[]
	 */
	public static function delay_js_exclusion_list() {
		$list = self::lines( 'delay_js_exclusions' );

		$packs = array(
			'delay_js_pack_analytics' => array( 'googletagmanager', 'gtag', 'google-analytics', 'analytics.js', 'ga.js', 'gtm.js' ),
			'delay_js_pack_ads'       => array( 'googlesyndication', 'doubleclick', 'adsbygoogle', 'pagead2' ),
			'delay_js_pack_chat'      => array( 'intercom', 'drift', 'hubspot', 'crisp', 'tawk', 'zendesk', 'livechat' ),
			'delay_js_pack_maps'      => array( 'maps.googleapis', 'maps.google', 'google.com/maps' ),
		);

		foreach ( $packs as $option => $needles ) {
			if ( self::get( $option ) ) {
				$list = array_merge( $list, $needles );
			}
		}

		return array_values( array_unique( array_filter( array_map( 'trim', $list ) ) ) );
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
