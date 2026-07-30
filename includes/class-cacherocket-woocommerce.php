<?php
/**
 * WooCommerce performance helpers.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Cache empty-cart refreshed fragments responses.
 */
class CacheRocket_WooCommerce {

	const TRANSIENT = 'cacherocket_wc_empty_fragments';

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( ! CacheRocket_Options::get( 'cache_wc_empty_cart' ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'maybe_serve_cached_fragments' ), 0 );
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'clear' ) );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'clear' ) );
	}

	/**
	 * Serve cached empty-cart fragments for wc-ajax=get_refreshed_fragments.
	 */
	public static function maybe_serve_cached_fragments() {
		if ( empty( $_GET['wc-ajax'] ) || 'get_refreshed_fragments' !== $_GET['wc-ajax'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Cart has items — do not serve empty cache.
		if ( ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return;
		}

		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['fragments'] ) ) {
			wp_send_json( $cached );
		}

		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'capture_fragments' ), 9999 );
	}

	/**
	 * Store empty-cart fragments when generated.
	 *
	 * @param array<string, string> $fragments Fragments.
	 * @return array<string, string>
	 */
	public static function capture_fragments( $fragments ) {
		if ( ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return $fragments;
		}

		$payload = array(
			'fragments' => $fragments,
			'cart_hash' => '',
		);
		set_transient( self::TRANSIENT, $payload, DAY_IN_SECONDS );
		return $fragments;
	}

	/**
	 * Clear empty-cart fragment cache.
	 */
	public static function clear() {
		delete_transient( self::TRANSIENT );
	}
}
