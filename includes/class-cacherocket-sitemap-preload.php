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

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );

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
	 * @return array{urls:int,result:mixed,limit:int}|WP_Error
	 */
	public static function run() {
		if ( ! CacheRocket_Options::get( 'preload_sitemap' ) ) {
			return new WP_Error( 'disabled', __( 'Sitemap preload is disabled.', 'cacherocket' ) );
		}

		$sitemap = self::get_sitemap_url();
		if ( ! $sitemap ) {
			return new WP_Error( 'no_sitemap', __( 'No sitemap URL configured.', 'cacherocket' ) );
		}

		$limit = self::collect_limit();
		$urls  = self::collect_urls( $sitemap, 0, $limit );
		if ( empty( $urls ) ) {
			return new WP_Error( 'empty', __( 'No URLs found in sitemap.', 'cacherocket' ) );
		}

		$urls   = array_slice( $urls, 0, $limit );
		$result = cacherocket_warm_urls( $urls );

		return array(
			'urls'   => count( $urls ),
			'limit'  => $limit,
			'result' => $result,
		);
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
