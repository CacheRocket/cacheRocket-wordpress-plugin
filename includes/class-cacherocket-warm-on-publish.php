<?php
/**
 * Warm URLs remotely via CacheRocket after publish / update.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Priority warm on publish for public post types.
 */
class CacheRocket_Warm_On_Publish {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 20, 3 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product' ), 20, 1 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product' ), 20, 1 );
	}

	/**
	 * When a post becomes published, warm its permalink (+ home).
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			return;
		}

		$urls = self::urls_for_post( $post );
		if ( empty( $urls ) ) {
			return;
		}

		self::enqueue_warm( $urls );
	}

	/**
	 * WooCommerce product create/update.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function on_product( $product_id ) {
		if ( ! self::is_enabled() ) {
			return;
		}
		$post = get_post( (int) $product_id );
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return;
		}
		$urls = self::urls_for_post( $post );
		if ( empty( $urls ) ) {
			return;
		}
		self::enqueue_warm( $urls );
	}

	/**
	 * Whether warm-on-publish is enabled (default on when API keys exist).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$api_key    = get_option( 'cacherocket_api_key' );
		$api_secret = get_option( 'cacherocket_api_secret' );
		if ( ! $api_key || ! $api_secret ) {
			return false;
		}
		if ( class_exists( 'CacheRocket_Options' ) ) {
			return (bool) CacheRocket_Options::get( 'warm_on_publish', true );
		}
		return (bool) get_option( 'cacherocket_warm_on_publish', true );
	}

	/**
	 * Build URL list for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return string[]
	 */
	private static function urls_for_post( $post ) {
		$urls = array();
		$permalink = get_permalink( $post );
		if ( $permalink ) {
			$urls[] = $permalink;
		}
		$home = home_url( '/' );
		$include_home = ! class_exists( 'CacheRocket_Options' ) || CacheRocket_Options::get( 'cache_purge_home', true );
		if ( $home && $include_home ) {
			$urls[] = $home;
		}

		if ( 'product' === $post->post_type && function_exists( 'wc_get_page_id' ) ) {
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$shop = get_permalink( $shop_id );
				if ( $shop ) {
					$urls[] = $shop;
				}
			}
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Debounce + fire remote warm (non-blocking where possible).
	 *
	 * @param string[] $urls URLs.
	 */
	private static function enqueue_warm( $urls ) {
		$transient_key = 'cacherocket_warm_lock_' . md5( implode( '|', $urls ) );
		if ( get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, 1, 30 );

		// Prefer async if Action Scheduler / WP Cron available; otherwise fire now.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'cacherocket_do_warm_urls', array( $urls ), 'cacherocket' );
			return;
		}

		self::do_warm( $urls );
	}

	/**
	 * Perform the API call.
	 *
	 * @param string[] $urls URLs.
	 */
	public static function do_warm( $urls ) {
		if ( empty( $urls ) || ! is_array( $urls ) ) {
			return;
		}
		cacherocket_warm_urls( $urls );
	}
}

/**
 * Cron / Action Scheduler callback.
 *
 * @param string[] $urls URLs.
 */
function cacherocket_do_warm_urls_action( $urls ) {
	CacheRocket_Warm_On_Publish::do_warm( $urls );
}
add_action( 'cacherocket_do_warm_urls', 'cacherocket_do_warm_urls_action', 10, 1 );
