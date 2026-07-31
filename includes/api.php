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
 * Selected workspace organization id (null = personal account).
 *
 * Option values:
 * - '' (empty): not configured yet
 * - 'personal': personal account
 * - '{cuid}': team id
 *
 * @return string|null Organization id, or null for personal / unset.
 */
function cacherocket_organization_id() {
	$raw = get_option( 'cacherocket_organization_id', '' );
	if ( ! is_string( $raw ) || '' === $raw || 'personal' === $raw ) {
		return null;
	}
	return $raw;
}

/**
 * Whether the user has explicitly chosen a workspace (personal or team).
 *
 * @return bool
 */
function cacherocket_organization_configured() {
	$raw = get_option( 'cacherocket_organization_id', '' );
	return is_string( $raw ) && '' !== $raw;
}

/**
 * Fetch teams for the connected CacheRocket account.
 *
 * @return array<int, array<string, mixed>>|WP_Error
 */
function cacherocket_organizations_fetch() {
	$result = cacherocket_api_post( 'getOrganizations', array( 'organizationId' => null ) );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$list = ! empty( $result['organizations'] ) && is_array( $result['organizations'] ) ? $result['organizations'] : array();
	return $list;
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

	$extra = is_array( $extra ) ? $extra : array();

	// Scope warmers / hostnames to the selected workspace (personal or team).
	// Skip for getOrganizations so listing teams works before a selection exists.
	$endpoint_name = preg_replace( '#^.*/#', '', (string) $endpoint );
	if ( 'getOrganizations' !== $endpoint_name && ! array_key_exists( 'organizationId', $extra ) ) {
		if ( cacherocket_organization_configured() ) {
			$extra['organizationId'] = cacherocket_organization_id();
		}
	}

	$body_data = array_merge(
		array(
			'publicKey' => $api_key,
			'secretKey' => $api_secret,
		),
		$extra
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
 * Create a cloud optimization job.
 *
 * @param array<string, mixed> $payload kind, sourceUrl, siteKey?, request?, callbackUrl?.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_create_optimization_job( $payload ) {
	$payload = is_array( $payload ) ? $payload : array();
	return cacherocket_api_post( 'createOptimizationJob', $payload );
}

/**
 * Fetch a cloud optimization job by id.
 *
 * @param string $job_id Job id.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_get_optimization_job( $job_id ) {
	return cacherocket_api_post(
		'getOptimizationJob',
		array(
			'jobId' => (string) $job_id,
		)
	);
}

/**
 * List recent cloud optimization jobs.
 *
 * @param array<string, mixed> $args Optional kind / limit.
 * @return array<int, array<string, mixed>>|WP_Error
 */
function cacherocket_list_optimization_jobs( $args = array() ) {
	$args = is_array( $args ) ? $args : array();
	return cacherocket_api_post( 'listOptimizationJobs', $args );
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
 * Ensures a site warmer exists so results appear under Warmers in the dashboard.
 * Large lists are split into plan-sized batches.
 *
 * @param string[] $urls Absolute URLs.
 * @return array<string, mixed>|WP_Error
 */
function cacherocket_warm_urls( $urls ) {
	if ( empty( $urls ) || ! is_array( $urls ) ) {
		return new WP_Error( 'empty_urls', __( 'No URLs to warm.', 'cacherocket' ) );
	}

	$urls = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $u ) {
						return is_string( $u ) ? trim( $u ) : '';
					},
					$urls
				)
			)
		)
	);
	if ( empty( $urls ) ) {
		return new WP_Error( 'empty_urls', __( 'No URLs to warm.', 'cacherocket' ) );
	}

	$crawler_id = cacherocket_ensure_site_warmer();
	if ( is_wp_error( $crawler_id ) ) {
		return $crawler_id;
	}

	$batch_size = class_exists( 'CacheRocket_Sitemap_Preload' )
		? CacheRocket_Sitemap_Preload::batch_limit()
		: 25;
	$batches    = array_chunk( $urls, max( 1, $batch_size ) );

	$aggregated = array(
		'warmed'    => 0,
		'failed'    => 0,
		'skipped'   => 0,
		'truncated' => 0,
		'limit'     => $batch_size,
		'batches'   => count( $batches ),
		'results'   => array(),
	);

	foreach ( $batches as $batch ) {
		$extra = array(
			'urls' => array_values( $batch ),
		);
		if ( $crawler_id ) {
			$extra['crawlerId'] = $crawler_id;
		}

		$result = cacherocket_api_post( 'warmUrls', $extra );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$aggregated['warmed']   += isset( $result['warmed'] ) ? (int) $result['warmed'] : 0;
		$aggregated['failed']   += isset( $result['failed'] ) ? (int) $result['failed'] : 0;
		$aggregated['skipped']  += isset( $result['skipped'] ) ? (int) $result['skipped'] : 0;
		$aggregated['truncated'] += isset( $result['truncated'] ) ? (int) $result['truncated'] : 0;
		if ( isset( $result['limit'] ) ) {
			$aggregated['limit'] = (int) $result['limit'];
		}
		if ( ! empty( $result['results'] ) && is_array( $result['results'] ) ) {
			$aggregated['results'] = array_merge( $aggregated['results'], $result['results'] );
		}
	}

	return $aggregated;
}

/**
 * Normalize a hostname for warmer matching (lowercase, strip www).
 *
 * @param string $host Host or URL host part.
 * @return string
 */
function cacherocket_normalize_hostname( $host ) {
	$host = strtolower( trim( (string) $host ) );
	$host = preg_replace( '#^https?://#', '', $host );
	$host = explode( '/', $host )[0];
	$host = explode( ':', $host )[0];
	$host = rtrim( $host, '.' );
	if ( 0 === strpos( $host, 'www.' ) ) {
		$host = substr( $host, 4 );
	}
	return $host;
}

/**
 * Whether a warmer belongs to this site's hostname.
 *
 * @param array<string, mixed> $crawler Warmer payload.
 * @param string               $host    Normalized hostname.
 * @return bool
 */
function cacherocket_warmer_matches_host( $crawler, $host ) {
	if ( ! is_array( $crawler ) || '' === $host ) {
		return false;
	}

	if ( ! empty( $crawler['hostName'] ) && cacherocket_normalize_hostname( (string) $crawler['hostName'] ) === $host ) {
		return true;
	}

	$cs = array();
	if ( ! empty( $crawler['CrawlSettings'] ) && is_array( $crawler['CrawlSettings'] ) ) {
		$cs = isset( $crawler['CrawlSettings']['entryUrls'] ) || isset( $crawler['CrawlSettings']['id'] )
			? $crawler['CrawlSettings']
			: ( isset( $crawler['CrawlSettings'][0] ) && is_array( $crawler['CrawlSettings'][0] ) ? $crawler['CrawlSettings'][0] : array() );
	} elseif ( ! empty( $crawler['crawlSettings'] ) && is_array( $crawler['crawlSettings'] ) ) {
		$cs = isset( $crawler['crawlSettings']['entryUrls'] ) || isset( $crawler['crawlSettings']['id'] )
			? $crawler['crawlSettings']
			: ( isset( $crawler['crawlSettings'][0] ) && is_array( $crawler['crawlSettings'][0] ) ? $crawler['crawlSettings'][0] : array() );
	}

	$entries = isset( $cs['entryUrls'] ) && is_array( $cs['entryUrls'] ) ? $cs['entryUrls'] : array();
	foreach ( $entries as $entry ) {
		$url = is_array( $entry ) && isset( $entry['url'] ) ? (string) $entry['url'] : (string) $entry;
		$entry_host = cacherocket_normalize_hostname( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $entry_host && $entry_host === $host ) {
			return true;
		}
	}

	return false;
}

/**
 * Ensure a CacheRocket warmer exists for this WordPress site.
 *
 * Reuses a matching warmer when possible; otherwise creates one (inactive by default)
 * so preload / warm-on-publish results show under Warmers in the dashboard.
 *
 * @return string|WP_Error Warmer id.
 */
function cacherocket_ensure_site_warmer() {
	$api_key    = get_option( 'cacherocket_api_key' );
	$api_secret = get_option( 'cacherocket_api_secret' );
	if ( ! $api_key || ! $api_secret ) {
		return new WP_Error( 'missing_api_key', __( 'API Key or Secret is missing.', 'cacherocket' ) );
	}

	$orgs = cacherocket_organizations_fetch();
	if ( ! is_wp_error( $orgs ) && count( $orgs ) > 0 && ! cacherocket_organization_configured() ) {
		return new WP_Error(
			'team_required',
			__( 'Select a team (or Personal account) on the CacheRocket Account page before warming.', 'cacherocket' )
		);
	}

	$home = home_url( '/' );
	$host = cacherocket_normalize_hostname( (string) wp_parse_url( $home, PHP_URL_HOST ) );
	if ( '' === $host ) {
		return new WP_Error( 'invalid_site', __( 'Could not determine site hostname for warmer.', 'cacherocket' ) );
	}

	$stored = (string) get_option( 'cacherocket_site_warmer_id', '' );
	if ( $stored ) {
		$got = cacherocket_crawler_get( $stored );
		if ( ! is_wp_error( $got ) && ! empty( $got['crawler']['id'] ) ) {
			$crawler = $got['crawler'];
			if ( cacherocket_warmer_matches_host( is_array( $crawler ) ? $crawler : array(), $host ) ) {
				return (string) $got['crawler']['id'];
			}
		}
		delete_option( 'cacherocket_site_warmer_id' );
	}

	$list = cacherocket_crawlers_fetch_data();
	if ( is_wp_error( $list ) ) {
		return $list;
	}

	$crawlers = ! empty( $list['crawlers'] ) && is_array( $list['crawlers'] ) ? $list['crawlers'] : array();
	foreach ( $crawlers as $crawler ) {
		if ( ! is_array( $crawler ) || empty( $crawler['id'] ) ) {
			continue;
		}
		if ( cacherocket_warmer_matches_host( $crawler, $host ) ) {
			$id = (string) $crawler['id'];
			update_option( 'cacherocket_site_warmer_id', $id, false );
			return $id;
		}
	}

	// Domain must be verified before createCrawler — heartbeat auto-verifies installs.
	if ( function_exists( 'cacherocket_send_plugin_heartbeat' ) ) {
		cacherocket_send_plugin_heartbeat( true );
	}

	$blogname = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$name     = $blogname
		? sprintf(
			/* translators: %s: site name */
			__( 'WordPress — %s', 'cacherocket' ),
			$blogname
		)
		: sprintf(
			/* translators: %s: hostname */
			__( 'WordPress — %s', 'cacherocket' ),
			$host
		);
	$name = substr( $name, 0, 120 );

	$created = cacherocket_crawler_create(
		array(
			'name'          => $name,
			'active'        => false,
			'region'        => 'default',
			'crawlSettings' => array(
				'entryUrls'          => array( $home ),
				'includePublicPosts' => true,
				'includeSitemaps'    => false,
				'useCanonical'       => true,
				'rewriteToHttps'     => true,
				'depth'              => 2,
				'requestTimeout'     => 15,
				'maxUrlCrawlsMinute' => 5,
				'autoStartInterval'  => 3600,
				'enqueueInterval'    => 3600,
			),
		)
	);

	if ( is_wp_error( $created ) ) {
		return $created;
	}

	$id = ! empty( $created['crawler']['id'] ) ? (string) $created['crawler']['id'] : '';
	if ( '' === $id ) {
		return new WP_Error( 'no_warmer_id', __( 'Warmer was created but no id was returned.', 'cacherocket' ) );
	}

	update_option( 'cacherocket_site_warmer_id', $id, false );
	return $id;
}
