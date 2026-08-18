<?php
/**
 * Sitemap URL collection and remote warming.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Parse XML sitemaps and send URLs to CacheRocket warmUrls.
 */
class CacheRocket_Sitemap_Preload {

	const CRON_HOOK = 'cacherocket_sitemap_preload';

	/** Option holding a snapshot of the most recent warm job. */
	const JOB_OPTION = 'cacherocket_sitemap_warm_job';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'wp_ajax_cacherocket_warm_job_status', array( __CLASS__, 'ajax_job_status' ) );

		if ( CacheRocket_Options::get( 'preload_sitemap' ) ) {
			self::maybe_schedule();
		} else {
			self::unschedule();
		}
	}

	/**
	 * Schedule daily sitemap warm if missing.
	 */
	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clear cron.
	 */
	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
			$ts = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Max URLs to collect from the sitemap for one preload run (from plan entitlements).
	 *
	 * Mirrors server priorityWarmCollectLimit: max(25, min(rate*40, daily, 2000)).
	 *
	 * @return int
	 */
	public static function collect_limit() {
		$ents    = class_exists( 'CacheRocket_Warmers' ) ? CacheRocket_Warmers::entitlements() : array();
		$per_min = isset( $ents['maxUrlCrawlsMinute'] ) ? max( 1, (int) $ents['maxUrlCrawlsMinute'] ) : 5;
		$per_day = isset( $ents['maxUrlCrawlsDay'] ) ? max( 25, (int) $ents['maxUrlCrawlsDay'] ) : 500;
		if ( isset( $ents['maxSitemapWarmUrls'] ) && (int) $ents['maxSitemapWarmUrls'] > 0 ) {
			$limit = (int) $ents['maxSitemapWarmUrls'];
		} else {
			$limit = max( 25, min( $per_min * 40, $per_day, 2000 ) );
		}
		/**
		 * Filter sitemap warm collect limit.
		 *
		 * @param int $limit Max URLs.
		 */
		return (int) apply_filters( 'cacherocket_sitemap_warm_limit', $limit );
	}

	/**
	 * Max URLs per warmUrls API request (from plan entitlements).
	 *
	 * @return int
	 */
	public static function batch_limit() {
		$ents    = class_exists( 'CacheRocket_Warmers' ) ? CacheRocket_Warmers::entitlements() : array();
		$per_min = isset( $ents['maxUrlCrawlsMinute'] ) ? max( 1, (int) $ents['maxUrlCrawlsMinute'] ) : 5;
		if ( isset( $ents['maxPriorityWarmBatch'] ) && (int) $ents['maxPriorityWarmBatch'] > 0 ) {
			$limit = (int) $ents['maxPriorityWarmBatch'];
		} else {
			$limit = max( 25, min( $per_min * 5, 100 ) );
		}
		return max( 1, $limit );
	}

	/**
	 * Resolve sitemap URL (setting or common SEO plugin defaults).
	 *
	 * @return string
	 */
	public static function get_sitemap_url() {
		$url = (string) CacheRocket_Options::get( 'preload_sitemap_url', '' );
		if ( $url ) {
			return $url;
		}

		$candidates = array(
			home_url( '/wp-sitemap.xml' ),
			home_url( '/sitemap_index.xml' ),
			home_url( '/sitemap.xml' ),
		);

		/**
		 * Filter auto-detected sitemap candidates.
		 *
		 * @param string[] $candidates URLs.
		 */
		$candidates = apply_filters( 'cacherocket_sitemap_candidates', $candidates );

		return isset( $candidates[0] ) ? (string) $candidates[0] : '';
	}

	/**
	 * Cron / manual entry point.
	 *
	 * Queues the warm on CacheRocket and returns as soon as the job is accepted.
	 * Warming a sitemap takes minutes on the server, so waiting for the result
	 * here would outlast the API request timeout.
	 *
	 * @param string $source Where the run came from (sitemap|manual).
	 * @return array{urls:int,limit:int,job:array<string,mixed>}|array{urls:int,limit:int,result:mixed}|WP_Error
	 */
	public static function run( $source = 'sitemap' ) {
		if ( ! CacheRocket_Options::get( 'preload_sitemap' ) ) {
			return new WP_Error( 'disabled', __( 'Sitemap preload is disabled.', 'cache-rocket' ) );
		}

		$sitemap = self::get_sitemap_url();
		if ( ! $sitemap ) {
			return new WP_Error( 'no_sitemap', __( 'No sitemap URL configured.', 'cache-rocket' ) );
		}

		$limit = self::collect_limit();
		$urls  = self::collect_urls( $sitemap, 0, $limit );
		if ( empty( $urls ) ) {
			return new WP_Error( 'empty', __( 'No URLs found in sitemap.', 'cache-rocket' ) );
		}

		$urls = array_slice( $urls, 0, $limit );

		// Older API deployments only have the synchronous endpoint.
		if ( ! cacherocket_supports_warm_jobs() ) {
			return array(
				'urls'   => count( $urls ),
				'limit'  => $limit,
				'result' => cacherocket_warm_urls( $urls ),
			);
		}

		$job = cacherocket_create_warm_job( $urls, $source );
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		return array(
			'urls'  => count( $urls ),
			'limit' => $limit,
			'job'   => self::store_job( $job ),
		);
	}

	/**
	 * Persist a snapshot of a warm job payload returned by the API.
	 *
	 * @param array<string, mixed> $job Job payload.
	 * @return array<string, mixed> Stored snapshot.
	 */
	public static function store_job( $job ) {
		$job = is_array( $job ) ? $job : array();

		$snapshot = array(
			'id'            => isset( $job['id'] ) ? (string) $job['id'] : '',
			'status'        => isset( $job['status'] ) ? (string) $job['status'] : 'queued',
			'done'          => ! empty( $job['done'] ),
			'source'        => isset( $job['source'] ) ? (string) $job['source'] : 'sitemap',
			'totalUrls'     => isset( $job['totalUrls'] ) ? (int) $job['totalUrls'] : 0,
			'processedUrls' => isset( $job['processedUrls'] ) ? (int) $job['processedUrls'] : 0,
			'warmed'        => isset( $job['warmed'] ) ? (int) $job['warmed'] : 0,
			'failed'        => isset( $job['failed'] ) ? (int) $job['failed'] : 0,
			'skipped'       => isset( $job['skipped'] ) ? (int) $job['skipped'] : 0,
			'truncated'     => isset( $job['truncated'] ) ? (int) $job['truncated'] : 0,
			'quotaExceeded' => ! empty( $job['quotaExceeded'] ),
			'quotaMessage'  => isset( $job['quotaMessage'] ) ? sanitize_text_field( (string) $job['quotaMessage'] ) : '',
			'errorMessage'  => isset( $job['errorMessage'] ) ? sanitize_text_field( (string) $job['errorMessage'] ) : '',
			'checkedAt'     => gmdate( 'c' ),
		);

		$previous = get_option( self::JOB_OPTION, array() );
		if ( is_array( $previous ) && isset( $previous['id'] ) && $previous['id'] === $snapshot['id'] && ! empty( $previous['startedAt'] ) ) {
			$snapshot['startedAt'] = (string) $previous['startedAt'];
		} else {
			$snapshot['startedAt'] = gmdate( 'c' );
		}

		update_option( self::JOB_OPTION, $snapshot, false );

		return $snapshot;
	}

	/**
	 * Most recent warm job snapshot, or null when none has run.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function last_job() {
		$job = get_option( self::JOB_OPTION, array() );
		if ( ! is_array( $job ) || empty( $job['id'] ) ) {
			return null;
		}
		return $job;
	}

	/**
	 * Re-poll the stored warm job and refresh the snapshot.
	 *
	 * @return array<string, mixed>|WP_Error|null Null when there is no job to poll.
	 */
	public static function refresh_job() {
		$job = self::last_job();
		if ( ! $job ) {
			return null;
		}
		if ( ! empty( $job['done'] ) ) {
			return $job;
		}

		$fresh = cacherocket_get_warm_job( $job['id'] );
		if ( is_wp_error( $fresh ) ) {
			return $fresh;
		}

		return self::store_job( $fresh );
	}

	/**
	 * Forget the stored warm job.
	 */
	public static function clear_job() {
		delete_option( self::JOB_OPTION );
	}

	/**
	 * AJAX: poll the stored warm job so the Preload page can show live progress.
	 */
	public static function ajax_job_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_warm_job', 'nonce' );

		$job = self::refresh_job();
		if ( null === $job ) {
			wp_send_json_success( array( 'job' => null ) );
		}
		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => $job->get_error_message() ) );
		}

		wp_send_json_success( array( 'job' => $job ) );
	}

	/**
	 * Recursively collect page URLs from a sitemap or sitemap index.
	 *
	 * @param string $url   Sitemap URL.
	 * @param int    $depth Recursion depth.
	 * @param int    $limit Max URLs to collect (0 = use plan limit).
	 * @return string[]
	 */
	public static function collect_urls( $url, $depth = 0, $limit = 0 ) {
		if ( $depth > 2 ) {
			return array();
		}

		$max = $limit > 0 ? (int) $limit : self::collect_limit();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'CacheRocket/' . ( defined( 'CACHEROCKET_VERSION' ) ? CACHEROCKET_VERSION : '1.0' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || '' === $body ) {
			return array();
		}

		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_string( $body );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( false === $xml ) {
			return array();
		}

		$urls  = array();
		$ns    = $xml->getDocNamespaces( true );
		$xhtml = isset( $ns[''] ) ? $xml->children( $ns[''] ) : $xml->children();

		// Sitemap index.
		if ( isset( $xhtml->sitemap ) ) {
			foreach ( $xhtml->sitemap as $entry ) {
				$loc = isset( $entry->loc ) ? trim( (string) $entry->loc ) : '';
				if ( $loc ) {
					$urls = array_merge( $urls, self::collect_urls( $loc, $depth + 1, $max ) );
				}
				if ( count( $urls ) >= $max ) {
					break;
				}
			}
			return array_slice( array_values( array_unique( $urls ) ), 0, $max );
		}

		// URL set.
		if ( isset( $xhtml->url ) ) {
			foreach ( $xhtml->url as $entry ) {
				$loc = isset( $entry->loc ) ? trim( (string) $entry->loc ) : '';
				if ( $loc ) {
					$urls[] = esc_url_raw( $loc );
				}
				if ( count( $urls ) >= $max ) {
					break;
				}
			}
		}

		return array_slice( array_values( array_filter( array_unique( $urls ) ) ), 0, $max );
	}
}
