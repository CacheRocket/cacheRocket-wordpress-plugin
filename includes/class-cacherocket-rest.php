<?php
/**
 * Public REST ping so CacheRocket can verify the plugin is still installed.
 *
 * @package CacheRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Cache Rocket REST routes.
 */
class CacheRocket_Rest {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'cacherocket/v1',
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ping' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Lightweight install probe response.
	 *
	 * @return WP_REST_Response
	 */
	public static function ping() {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$domain = is_string( $host ) ? strtolower( $host ) : '';

		return new WP_REST_Response(
			array(
				'ok'            => true,
				'service'       => 'cacherocket',
				'plugin'        => 'cache-rocket',
				'version'       => defined( 'CACHEROCKET_VERSION' ) ? CACHEROCKET_VERSION : '0',
				'domain'        => $domain,
				'siteUrl'       => home_url( '/' ),
				'connected'     => (bool) ( get_option( 'cacherocket_api_key' ) && get_option( 'cacherocket_api_secret' ) ),
			),
			200
		);
	}
}
