<?php
/**
 * Standard PHP page-cache engine (template_redirect + output buffering).
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Serve and capture cached pages via WordPress hooks.
 */
class CacheRocket_Cache_Engine {

	/**
	 * Register hooks when page caching is enabled.
	 */
	public static function init() {
		if ( ! CacheRocket_Cache::is_enabled() ) {
			return;
		}

		// Never serve or buffer page cache in wp-admin / admin-ajax.
		if ( ! is_admin() ) {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_or_start_buffer' ), 0 );
		}

		$auto_purge = ! class_exists( 'CacheRocket_Options' ) || CacheRocket_Options::get( 'cache_purge_pages', true );
		if ( $auto_purge ) {
			add_action( 'save_post', array( __CLASS__, 'purge_all' ) );
			add_action( 'deleted_post', array( __CLASS__, 'purge_all' ) );
			add_action( 'trashed_post', array( __CLASS__, 'purge_all' ) );
			add_action( 'switch_theme', array( __CLASS__, 'purge_all' ) );
			add_action( 'wp_update_nav_menu', array( __CLASS__, 'purge_all' ) );
			add_action( 'edited_term', array( __CLASS__, 'purge_all' ) );
			add_action( 'delete_term', array( __CLASS__, 'purge_all' ) );
			add_action( 'comment_post', array( __CLASS__, 'purge_all' ) );
			add_action( 'wp_set_comment_status', array( __CLASS__, 'purge_all' ) );

			if ( CacheRocket_Cache::is_woocommerce_caching_enabled() ) {
				add_action( 'woocommerce_update_product', array( __CLASS__, 'purge_all' ) );
				add_action( 'woocommerce_new_product', array( __CLASS__, 'purge_all' ) );
				add_action( 'woocommerce_delete_product', array( __CLASS__, 'purge_all' ) );
			}
		}
	}

	/**
	 * Serve a hit or start buffering for a miss.
	 */
	public static function maybe_serve_or_start_buffer() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! CacheRocket_Cache::is_request_cacheable() ) {
			return;
		}

		if ( ! CacheRocket_Cache::is_query_cacheable() ) {
			return;
		}

		if ( CacheRocket_Cache::DELIVERY_STANDARD === CacheRocket_Cache::get_delivery_mode() ) {
			$html = CacheRocket_Cache::read();
			if ( false !== $html ) {
				CacheRocket_Cache::serve( $html );
			}
		}

		ob_start( array( __CLASS__, 'ob_callback' ) );
	}

	/**
	 * Output buffer callback: store eligible HTML.
	 *
	 * @param string $html Buffered HTML.
	 * @return string
	 */
	public static function ob_callback( $html ) {
		if ( CacheRocket_Cache::should_cache_response() ) {
			CacheRocket_Cache::write( $html );
		}

		if ( ! headers_sent() ) {
			header( 'X-CacheRocket-Cache: MISS' );
		}

		return $html;
	}

	/**
	 * Purge all CacheRocket page cache files.
	 */
	public static function purge_all() {
		CacheRocket_Cache::purge_all();
	}
}
