<?php
/**
 * CacheRocket API helpers.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'CACHEROCKET_API_BASE' ) ) {
	define( 'CACHEROCKET_API_BASE', 'https://api.cacherocket.com/web/v1/wordpress' );
}

/**
 * WordPress API base URL (no trailing slash).
 *
 * @return string
 */
function cacherocket_api_base() {
	$base = defined( 'CACHEROCKET_API_BASE' ) ? CACHEROCKET_API_BASE : 'https://api.cacherocket.com/web/v1/wordpress';
	/**
	 * Filter the CacheRocket WordPress API base URL.
	 *
	 * @param string $base Base URL without trailing slash.
	 */
	return untrailingslashit( (string) apply_filters( 'cacherocket_api_base', $base ) );
}

/**
 * Absolute URL for a WordPress API endpoint.
 *
 * @param string $endpoint Endpoint path segment (e.g. getPlan).
 * @return string
 */
function cacherocket_api_url( $endpoint ) {
	$endpoint = ltrim( (string) $endpoint, '/' );
	return cacherocket_api_base() . '/' . $endpoint;
}

/**
 * Unwrap standard CacheRocket API envelope.
 *
 * @param array<string, mixed> $payload Decoded JSON.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_unwrap_api_data( $payload ) {
	if ( ! is_array( $payload ) ) {
		return new WP_Error( 'invalid_response', __( 'Invalid response from the CacheRocket API.', 'cacherocket' ) );
	}

	if ( isset( $payload['success'] ) && false === $payload['success'] ) {
		$message = isset( $payload['message'] ) ? $payload['message'] : __( 'CacheRocket API request failed.', 'cacherocket' );
		return new WP_Error( 'api_error', sanitize_text_field( $message ) );
	}

	if ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
		return $payload['data'];
	}

	return $payload;
}

/**
 * POST JSON to a CacheRocket WordPress API endpoint.
 *
 * @param string               $endpoint Absolute URL or endpoint name under the API base.
 * @param array<string, mixed> $extra    Extra body fields merged with API keys.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_api_post( $endpoint, $extra = array() ) {
	$api_key    = get_option( 'cacherocket_api_key' );
	$api_secret = get_option( 'cacherocket_api_secret' );

	if ( ! $api_key || ! $api_secret ) {
		return new WP_Error( 'missing_api_key', __( 'API Key or Secret is missing.', 'cacherocket' ) );
	}

	if ( 0 !== strpos( $endpoint, 'http://' ) && 0 !== strpos( $endpoint, 'https://' ) ) {
		$endpoint = cacherocket_api_url( $endpoint );
	}

	$body_data = array_merge(
		array(
			'publicKey' => $api_key,
			'secretKey' => $api_secret,
		),
		is_array( $extra ) ? $extra : array()
	);

	$body = wp_json_encode( $body_data );

	$version = defined( 'CACHEROCKET_VERSION' ) ? CACHEROCKET_VERSION : '0';

	$response = wp_remote_post(
		$endpoint,
		array(
			'headers'  => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => 'CacheRocket-WordPress/' . $version,
				'Accept'       => 'application/json',
			),
			'body'     => $body,
			'timeout'  => 45,
			'blocking' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$raw  = wp_remote_retrieve_body( $response );
	$data = json_decode( $raw, true );

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'invalid_json', __( 'Invalid JSON response from the API.', 'cacherocket' ) );
	}

	if ( $code < 200 || $code >= 300 ) {
		$unwrapped = cacherocket_unwrap_api_data( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $unwrapped ) ) {
			return $unwrapped;
		}
		$message = isset( $data['message'] ) ? sanitize_text_field( (string) $data['message'] ) : __( 'CacheRocket API request failed.', 'cacherocket' );
		$error   = new WP_Error( 'http_error', $message );
		if ( ! empty( $data['unverifiedHostnames'] ) && is_array( $data['unverifiedHostnames'] ) ) {
			$error->add_data( array( 'unverifiedHostnames' => $data['unverifiedHostnames'] ) );
		}
		return $error;
	}

	return cacherocket_unwrap_api_data( $data );
}

/**
 * Fetch cache warmers for the connected account.
 *
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_crawlers_fetch_data() {
	return cacherocket_api_post( 'getCrawlers' );
}

/**
 * Fetch a single warmer by id.
 *
 * @param string $crawler_id Warmer id.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_crawler_get( $crawler_id ) {
	return cacherocket_api_post(
		'getCrawler',
		array(
			'crawlerId' => (string) $crawler_id,
		)
	);
}

/**
 * Create a warmer.
 *
 * @param array<string, mixed> $payload Create payload (without API keys).
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_crawler_create( $payload ) {
	return cacherocket_api_post( 'createCrawler', $payload );
}

/**
 * Update a warmer (including enable/disable via active/stopRequest).
 *
 * @param array<string, mixed> $payload Update payload.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_crawler_update( $payload ) {
	return cacherocket_api_post( 'updateCrawler', $payload );
}

/**
 * Delete a warmer.
 *
 * @param string $crawler_id Warmer id.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_crawler_delete( $crawler_id ) {
	return cacherocket_api_post(
		'deleteCrawler',
		array(
			'crawlerId' => (string) $crawler_id,
		)
	);
}

/**
 * Request warmer start.
 *
 * @param string $crawler_id Warmer id.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_crawler_start( $crawler_id ) {
	return cacherocket_api_post(
		'startCrawler',
		array(
			'crawlerId' => (string) $crawler_id,
		)
	);
}

/**
 * Request warmer stop.
 *
 * @param string $crawler_id Warmer id.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_crawler_stop( $crawler_id ) {
	return cacherocket_api_post(
		'stopCrawler',
		array(
			'crawlerId' => (string) $crawler_id,
		)
	);
}

/**
 * Fetch subscription plan / feature flags for the connected account.
 *
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_fetch_plan() {
	return cacherocket_api_post( 'getPlan' );
}

/**
 * Build site metadata for connected-install heartbeats.
 *
 * @return array<string, string>
 */
function cacherocket_plugin_heartbeat_payload() {
	$site_url = home_url( '/' );
	$host     = wp_parse_url( $site_url, PHP_URL_HOST );
	$domain   = is_string( $host ) ? strtolower( $host ) : '';

	global $wp_version;

	return array(
		'siteUrl'       => $site_url,
		'domain'        => $domain,
		'pluginVersion' => defined( 'CACHEROCKET_VERSION' ) ? CACHEROCKET_VERSION : '0',
		'wpVersion'     => isset( $wp_version ) ? (string) $wp_version : '',
		'phpVersion'    => PHP_VERSION,
	);
}

/**
 * Report this site as a connected install to CacheRocket.com.
 *
 * Only runs when API keys are present (connecting keys is the opt-in).
 * Stores on the server: API key id, site URL/domain, plugin/WP/PHP versions, lastSeen.
 *
 * @param bool $force Bypass local throttle.
 * @return array<string, mixed>|WP_Error|true True when skipped by throttle.
 */
function cacherocket_send_plugin_heartbeat( $force = false ) {
	$api_key    = get_option( 'cacherocket_api_key' );
	$api_secret = get_option( 'cacherocket_api_secret' );
	if ( ! $api_key || ! $api_secret ) {
		return new WP_Error( 'missing_api_key', __( 'API Key or Secret is missing.', 'cacherocket' ) );
	}

	if ( ! $force && get_transient( 'cacherocket_heartbeat_sent' ) ) {
		return true;
	}

	$payload = cacherocket_plugin_heartbeat_payload();
	if ( '' === $payload['domain'] ) {
		return new WP_Error( 'invalid_site', __( 'Could not determine site domain for heartbeat.', 'cacherocket' ) );
	}

	$result = cacherocket_api_post( 'pluginHeartbeat', $payload );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	set_transient( 'cacherocket_heartbeat_sent', 1, 12 * HOUR_IN_SECONDS );
	update_option(
		'cacherocket_last_heartbeat',
		array(
			'sentAt'  => gmdate( 'c' ),
			'siteUrl' => $payload['siteUrl'],
			'domain'  => $payload['domain'],
		),
		false
	);

	return $result;
}

/**
 * Priority-warm a list of URLs via CacheRocket.
 *
 * @param string[] $urls Absolute URLs.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_warm_urls( $urls ) {
	if ( empty( $urls ) || ! is_array( $urls ) ) {
		return new WP_Error( 'empty_urls', __( 'No URLs to warm.', 'cacherocket' ) );
	}
	return cacherocket_api_post(
		'warmUrls',
		array(
			'urls' => array_values( $urls ),
		)
	);
}
