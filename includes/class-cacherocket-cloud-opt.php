<?php
/**
 * Cloud optimization (image / CCSS / LQIP / PageSpeed) via CacheRocket API.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Queue remote optimization jobs and apply results on the front end.
 */
class CacheRocket_Cloud_Opt {

	const META_IMAGE           = '_cacherocket_image_opt';
	const META_LQIP            = '_cacherocket_lqip';
	const META_IGNORE          = '_cacherocket_opt_ignore';
	const OPTION_CCSS          = 'cacherocket_ccss_map';
	const OPTION_PSI           = 'cacherocket_pagespeed_last';
	const OPTION_BACKFILL      = 'cacherocket_opt_backfill_cursor';
	const OPTION_BACKFILL_DONE = 'cacherocket_opt_backfill_done';
	const OPTION_LOCK_GEN      = 'cacherocket_opt_lock_gen';
	const CRON_POLL            = 'cacherocket_poll_opt_jobs';
	const CRON_BACKFILL        = 'cacherocket_backfill_opt_jobs';
	const TRANSIENT_JOBS       = 'cacherocket_pending_opt_jobs';
	const TRANSIENT_IMAGE_CDN  = 'cacherocket_image_cdn_cfg';
	const IMAGE_CDN_TTL        = 6 * HOUR_IN_SECONDS;

	/**
	 * Attachment ids seen on the current front-end render with no optimized variant yet.
	 *
	 * @var int[]
	 */
	private static $render_queue = array();

	/**
	 * Boot hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'add_attachment', array( __CLASS__, 'maybe_queue_attachment' ) );
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete_attachment' ) );
		add_action( self::CRON_POLL, array( __CLASS__, 'poll_pending_jobs' ) );
		add_action( self::CRON_BACKFILL, array( __CLASS__, 'run_backfill' ) );
		add_action( 'wp_ajax_cacherocket_run_pagespeed', array( __CLASS__, 'ajax_run_pagespeed' ) );
		add_action( 'wp_ajax_cacherocket_queue_ccss', array( __CLASS__, 'ajax_queue_ccss' ) );
		add_action( 'wp_ajax_cacherocket_bulk_optimize', array( __CLASS__, 'ajax_bulk_optimize' ) );
		add_action( 'wp_ajax_cacherocket_bulk_status', array( __CLASS__, 'ajax_bulk_status' ) );
		add_action( 'wp_ajax_cacherocket_optimize_attachment', array( __CLASS__, 'ajax_optimize_attachment' ) );
		add_action( 'wp_ajax_cacherocket_restore_attachment', array( __CLASS__, 'ajax_restore_attachment' ) );
		add_action( 'wp_ajax_cacherocket_optimize_directory', array( __CLASS__, 'ajax_optimize_directory' ) );
		add_action( 'wp_ajax_cacherocket_list_jobs', array( __CLASS__, 'ajax_list_jobs' ) );
		add_action( 'wp_ajax_cacherocket_ignore_attachment', array( __CLASS__, 'ajax_ignore_attachment' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_poll_on_admin' ), 50 );

		// Media Library "CacheRocket" column (status / savings / actions).
		add_filter( 'manage_media_columns', array( __CLASS__, 'media_column_header' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'media_column_content' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'attachment_fields_to_edit' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'attachment_fields_to_save' ), 10, 2 );

		self::ensure_poll_schedule();
		self::maybe_schedule_initial_backfill();

		// Front-end delivery only — never rewrite media/content in wp-admin or admin-ajax
		// (breaks file managers, media library, and other admin XHR tools).
		if ( is_admin() ) {
			return;
		}

		// While jobs are pending, poll on front-end traffic too (throttled) so CCSS
		// does not wait for an admin visit or the next cron tick.
		add_action( 'shutdown', array( __CLASS__, 'maybe_poll_on_frontend' ), 5 );

		// Stop serving managed CDN URLs when monthly Bunny egress is exhausted.
		$cdn_bandwidth_ok = CacheRocket_Plan::has_cdn_bandwidth_remaining();

		if ( CacheRocket_Options::get( 'cloud_critical_css' ) && CacheRocket_Plan::can_use_critical_css() ) {
			if ( $cdn_bandwidth_ok ) {
				add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_critical_css' ), 1 );
			}
			add_action( 'template_redirect', array( __CLASS__, 'maybe_queue_page_ccss' ), 5 );
		}

		// On-demand delivery soft-fails to origin on quota, so it does not need the
		// managed-CDN bandwidth gate; pre-baked delivery does.
		$image_delivery_ok = self::on_demand_enabled() || $cdn_bandwidth_ok;
		if ( CacheRocket_Options::get( 'cloud_image_opt' ) && CacheRocket_Plan::can_use_image_optimization() && $image_delivery_ok ) {
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'attachment_image_attrs' ), 20, 2 );
			add_filter( 'the_content', array( __CLASS__, 'rewrite_content_images' ), 25 );
		}

		if ( CacheRocket_Options::get( 'cloud_lqip' ) && CacheRocket_Plan::can_use_lqip() && $cdn_bandwidth_ok ) {
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'attachment_lqip_attrs' ), 25, 2 );
		}
	}

	/**
	 * Register a short WP-Cron interval for optimization job polling.
	 *
	 * @param array<string, array<string, mixed>> $schedules Cron schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function cron_schedules( $schedules ) {
		$schedules = is_array( $schedules ) ? $schedules : array();
		$schedules['cacherocket_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (CacheRocket)', 'cache-rocket' ),
		);
		return $schedules;
	}

	/**
	 * Ensure recurring poll runs every 5 minutes (migrate off hourly).
	 */
	private static function ensure_poll_schedule() {
		$event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( self::CRON_POLL ) : false;
		if ( $event && is_object( $event ) && 'cacherocket_five_minutes' === $event->schedule ) {
			return;
		}
		wp_clear_scheduled_hook( self::CRON_POLL );
		wp_schedule_event( time() + 60, 'cacherocket_five_minutes', self::CRON_POLL );
	}

	/**
	 * Schedule near-term single polls after a job is queued.
	 *
	 * WP-Cron only fires on traffic, so we also nudge spawn_cron().
	 * Unique args are required so WP does not collapse events within 10 minutes.
	 */
	private static function schedule_fast_polls() {
		foreach ( array( 15, 45, 90, 180, 300 ) as $delay ) {
			$timestamp = time() + (int) $delay;
			if ( ! wp_next_scheduled( self::CRON_POLL, array( $delay ) ) ) {
				wp_schedule_single_event( $timestamp, self::CRON_POLL, array( $delay ) );
			}
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron( time() );
		}
	}

	/**
	 * Shared throttle for opportunistic polls (admin / front end).
	 *
	 * @param int $lock_seconds Minimum seconds between polls.
	 */
	private static function maybe_poll_pending( $lock_seconds = 15 ) {
		$pending = get_transient( self::TRANSIENT_JOBS );
		if ( ! is_array( $pending ) || empty( $pending ) ) {
			return;
		}
		$lock = get_transient( 'cacherocket_opt_poll_lock' );
		if ( $lock ) {
			return;
		}
		set_transient( 'cacherocket_opt_poll_lock', 1, max( 5, (int) $lock_seconds ) );
		self::poll_pending_jobs();
	}

	/**
	 * Light admin poll so PageSpeed / image results show without waiting for cron.
	 */
	public static function maybe_poll_on_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::maybe_poll_pending( 15 );
	}

	/**
	 * Front-end poll while optimization jobs are in flight.
	 */
	public static function maybe_poll_on_frontend() {
		if ( is_admin() || wp_doing_ajax() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return;
		}
		self::maybe_poll_pending( 20 );
	}

	/**
	 * Site key for multi-tenant CDN paths.
	 *
	 * @return string
	 */
	public static function site_key() {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : 'site';
	}

	/**
	 * Cached on-demand image CDN config (siteToken + base) from the API.
	 *
	 * @param bool $force Force refresh.
	 * @return array<string, mixed>
	 */
	public static function image_cdn_config( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_IMAGE_CDN );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$disabled = array( 'enabled' => false );

		if ( ! get_option( 'cacherocket_api_key' ) || ! get_option( 'cacherocket_api_secret' ) ) {
			set_transient( self::TRANSIENT_IMAGE_CDN, $disabled, MINUTE_IN_SECONDS * 15 );
			return $disabled;
		}

		$result = cacherocket_get_image_cdn();
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			set_transient( self::TRANSIENT_IMAGE_CDN, $disabled, MINUTE_IN_SECONDS * 15 );
			return $disabled;
		}

		$config = array(
			'enabled'      => ! empty( $result['enabled'] ) && ! empty( $result['siteToken'] ),
			'siteToken'    => isset( $result['siteToken'] ) ? (string) $result['siteToken'] : '',
			'imageBaseUrl' => isset( $result['imageBaseUrl'] ) ? untrailingslashit( (string) $result['imageBaseUrl'] ) : 'https://img.cacherocket.com',
			'imagePath'    => isset( $result['imagePath'] ) ? '/' . ltrim( (string) $result['imagePath'], '/' ) : '/i',
			'allowWebp'    => ! empty( $result['allowWebp'] ),
			'allowAvif'    => ! empty( $result['allowAvif'] ),
		);
		set_transient( self::TRANSIENT_IMAGE_CDN, $config, self::IMAGE_CDN_TTL );
		return $config;
	}

	/**
	 * Drop the cached image CDN config (after plan sync / connect / settings change).
	 */
	public static function clear_image_cdn_cache() {
		delete_transient( self::TRANSIENT_IMAGE_CDN );
	}

	/**
	 * Whether responsive on-demand CDN delivery is active for the front end.
	 *
	 * @return bool
	 */
	public static function on_demand_enabled() {
		if ( ! CacheRocket_Options::get( 'cloud_image_cdn' ) ) {
			return false;
		}
		if ( ! CacheRocket_Plan::can_use_on_demand_image_cdn() ) {
			return false;
		}
		$config = self::image_cdn_config();
		return ! empty( $config['enabled'] ) && ! empty( $config['siteToken'] );
	}

	/**
	 * Build an on-demand edge URL for a source image.
	 *
	 * @param string $origin_url Absolute origin image URL.
	 * @param int    $width      Target width in pixels.
	 * @param int    $quality    JPEG/WebP/AVIF quality (40-95).
	 * @param string $format     auto|webp|avif|jpeg.
	 * @return string Edge URL, or '' when unavailable.
	 */
	public static function build_edge_url( $origin_url, $width, $quality = 0, $format = 'auto' ) {
		$origin_url = trim( (string) $origin_url );
		if ( '' === $origin_url || 0 !== strpos( $origin_url, 'http' ) ) {
			return '';
		}
		$config = self::image_cdn_config();
		if ( empty( $config['enabled'] ) || empty( $config['siteToken'] ) ) {
			return '';
		}

		$width   = max( 1, min( 4096, (int) $width ) );
		$quality = (int) $quality;
		if ( $quality <= 0 ) {
			$quality = (int) CacheRocket_Options::get( 'cloud_image_quality', 75 );
		}
		$quality = max( 40, min( 95, $quality ) );

		$format   = in_array( $format, array( 'auto', 'webp', 'avif', 'jpeg' ), true ) ? $format : 'auto';
		$transform = sprintf( 'w_%d,q_%d,f_%s', $width, $quality, $format );

		return sprintf(
			'%s%s/%s/%s/%s',
			$config['imageBaseUrl'],
			$config['imagePath'],
			rawurlencode( $config['siteToken'] ),
			$transform,
			rawurlencode( $origin_url )
		);
	}

	/**
	 * Candidate widths for responsive srcset, derived from registered image sizes.
	 *
	 * @return int[] Sorted unique widths capped at the configured max width.
	 */
	public static function edge_widths() {
		$widths = array( 320, 480, 640, 768, 1024, 1280, 1536, 1920 );

		foreach ( array( 'medium', 'medium_large', 'large' ) as $size ) {
			$w = (int) get_option( $size . '_size_w' );
			if ( $w > 0 ) {
				$widths[] = $w;
			}
		}

		$max = (int) CacheRocket_Options::get( 'cloud_image_max_width', 2560 );
		if ( $max <= 0 ) {
			$max = 2560;
		}
		$widths[] = $max;

		$widths = array_values( array_unique( array_filter(
			$widths,
			static function ( $w ) use ( $max ) {
				return $w > 0 && $w <= $max;
			}
		) ) );
		sort( $widths );
		return $widths;
	}

	/**
	 * Build a srcset string of edge URLs for the given origin.
	 *
	 * @param string $origin_url Absolute origin image URL.
	 * @param int    $quality    Quality.
	 * @return string srcset value, or '' when unavailable.
	 */
	public static function edge_srcset( $origin_url, $quality = 0 ) {
		$parts = array();
		foreach ( self::edge_widths() as $w ) {
			$url = self::build_edge_url( $origin_url, $w, $quality, 'auto' );
			if ( '' !== $url ) {
				$parts[] = esc_url( $url ) . ' ' . $w . 'w';
			}
		}
		return implode( ', ', $parts );
	}

	/**
	 * Whether an attachment is manually excluded from cloud optimization / CDN rewrite.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_attachment_ignored( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return false;
		}
		return (bool) get_post_meta( $attachment_id, self::META_IGNORE, true );
	}

	/**
	 * Whether a URL is excluded from CDN rewriting by user rules or per-attachment ignore.
	 *
	 * @param string   $url            URL to test.
	 * @param int|null $attachment_id  Optional attachment ID when already known.
	 * @return bool
	 */
	public static function is_excluded_url( $url, $attachment_id = null ) {
		if ( null !== $attachment_id && self::is_attachment_ignored( (int) $attachment_id ) ) {
			return true;
		}

		$patterns = CacheRocket_Options::lines( 'cloud_image_exclusions' );
		$url      = (string) $url;
		if ( ! empty( $patterns ) ) {
			foreach ( $patterns as $needle ) {
				if ( '' !== $needle && false !== strpos( $url, $needle ) ) {
					return true;
				}
			}
		}

		// Bare uploads in content: resolve to attachment and honor per-image ignore.
		if ( null === $attachment_id && '' !== $url && function_exists( 'attachment_url_to_postid' ) ) {
			$resolved = (int) attachment_url_to_postid( $url );
			if ( $resolved > 0 && self::is_attachment_ignored( $resolved ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Image job request options from settings (quality, max width, formats, backup).
	 *
	 * @return array<string, mixed>
	 */
	public static function image_request_options() {
		$formats = array();
		if ( CacheRocket_Options::get( 'cloud_avif' ) && CacheRocket_Plan::can_use_avif() ) {
			$formats[] = 'avif';
		}
		if ( CacheRocket_Options::get( 'cloud_webp' ) ) {
			$formats[] = 'webp';
		}
		if ( empty( $formats ) ) {
			$formats[] = 'jpeg';
		}

		$request = array(
			'quality' => (int) CacheRocket_Options::get( 'cloud_image_quality', 75 ),
			'formats' => $formats,
		);

		$max_width = (int) CacheRocket_Options::get( 'cloud_image_max_width', 2560 );
		if ( $max_width > 0 ) {
			$request['maxWidth'] = $max_width;
		}

		if ( CacheRocket_Options::get( 'cloud_image_backup' ) && CacheRocket_Plan::can_use_image_backup() ) {
			$request['backupOriginal'] = true;
		}

		return $request;
	}

	/**
	 * Queue image + LQIP jobs when a new attachment is uploaded.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function maybe_queue_attachment( $attachment_id ) {
		self::ensure_queued( $attachment_id );
	}

	/**
	 * When media is deleted, clear local mappings and remove matching OVH objects.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function on_delete_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return;
		}

		$source_url = self::attachment_source_url( $attachment_id );
		$had_image  = (bool) get_post_meta( $attachment_id, self::META_IMAGE, true );
		$had_lqip   = (bool) get_post_meta( $attachment_id, self::META_LQIP, true );

		delete_post_meta( $attachment_id, self::META_IMAGE );
		delete_post_meta( $attachment_id, self::META_LQIP );

		if ( ( ! $had_image && ! $had_lqip ) || '' === $source_url ) {
			return;
		}

		if ( ! get_option( 'cacherocket_api_key' ) || ! get_option( 'cacherocket_api_secret' ) ) {
			return;
		}

		$kinds = array();
		if ( $had_image ) {
			$kinds[] = 'imageOpt';
		}
		if ( $had_lqip ) {
			$kinds[] = 'lqip';
		}

		cacherocket_purge_optimization_assets(
			array(
				'siteKey'   => self::site_key(),
				'kinds'     => $kinds,
				'sourceUrl' => $source_url,
			)
		);
	}

	/**
	 * Source URL for a job, without CDN/cloud rewrites applied.
	 *
	 * The front end filters wp_get_attachment_url through CacheRocket_CDN, and a custom
	 * CNAME is not necessarily fetchable by the optimization worker. Jobs must always be
	 * given the canonical origin URL.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function attachment_source_url( $attachment_id ) {
		$cdn_filter = array( 'CacheRocket_CDN', 'rewrite_url' );
		$had_filter = has_filter( 'wp_get_attachment_url', $cdn_filter );

		if ( false !== $had_filter ) {
			remove_filter( 'wp_get_attachment_url', $cdn_filter, (int) $had_filter );
		}

		$url = wp_get_attachment_url( (int) $attachment_id );

		if ( false !== $had_filter ) {
			add_filter( 'wp_get_attachment_url', $cdn_filter, (int) $had_filter );
		}

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Per-attachment queue throttle key.
	 *
	 * The generation stamp lets a disable/enable cycle invalidate every outstanding lock
	 * at once, so re-enabling can re-queue immediately instead of waiting them out.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function lock_key( $attachment_id ) {
		return 'cacherocket_opt_q_' . (int) get_option( self::OPTION_LOCK_GEN, 0 ) . '_' . (int) $attachment_id;
	}

	/**
	 * Per-URL Critical CSS queue throttle key.
	 *
	 * @param string $url Page URL.
	 * @return string
	 */
	private static function ccss_lock_key( $url ) {
		return 'cacherocket_ccss_q_' . (int) get_option( self::OPTION_LOCK_GEN, 0 ) . '_' . md5( (string) $url );
	}

	/**
	 * Invalidate all outstanding image / Critical CSS queue throttles.
	 */
	private static function bump_lock_generation() {
		update_option( self::OPTION_LOCK_GEN, (int) get_option( self::OPTION_LOCK_GEN, 0 ) + 1, true );
	}

	/**
	 * Queue whatever cloud variants an attachment is still missing.
	 *
	 * Used for new uploads, for library backfill, and on demand when the front end renders
	 * an image that has no optimized variant yet (images uploaded before the feature was
	 * enabled, or whose mapping was cleared by a disable/enable cycle).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool Whether at least one job was queued.
	 */
	public static function ensure_queued( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		if ( self::is_attachment_ignored( $attachment_id ) ) {
			return false;
		}

		$lock = self::lock_key( $attachment_id );
		if ( get_transient( $lock ) ) {
			return false;
		}

		$want_image = CacheRocket_Options::get( 'cloud_image_opt' ) && CacheRocket_Plan::can_use_image_optimization();
		$want_lqip  = CacheRocket_Options::get( 'cloud_lqip' ) && CacheRocket_Plan::can_use_lqip();
		if ( ! $want_image && ! $want_lqip ) {
			return false;
		}

		if ( $want_image ) {
			$meta = get_post_meta( $attachment_id, self::META_IMAGE, true );
			if ( is_array( $meta ) && ! empty( $meta['formats'] ) ) {
				$want_image = false;
			}
		}

		if ( $want_lqip ) {
			$meta = get_post_meta( $attachment_id, self::META_LQIP, true );
			if ( is_array( $meta ) && ! empty( $meta['dataUri'] ) ) {
				$want_lqip = false;
			}
		}

		if ( ! $want_image && ! $want_lqip ) {
			// Nothing missing — do not re-check this attachment on every render.
			set_transient( $lock, 1, DAY_IN_SECONDS );
			return false;
		}

		$url = self::attachment_source_url( $attachment_id );
		if ( '' === $url ) {
			set_transient( $lock, 1, HOUR_IN_SECONDS );
			return false;
		}

		$queued = false;

		if ( $want_image ) {
			$result = self::queue_job(
				'imageOpt',
				$url,
				array(
					'attachmentId' => $attachment_id,
					'metaKey'      => self::META_IMAGE,
				),
				self::image_request_options()
			);
			$queued = $queued || ! is_wp_error( $result );
		}

		if ( $want_lqip ) {
			$result = self::queue_job(
				'lqip',
				$url,
				array(
					'attachmentId' => $attachment_id,
					'metaKey'      => self::META_LQIP,
				)
			);
			$queued = $queued || ! is_wp_error( $result );
		}

		// Back off for an hour when the API rejected the job (quota, credentials, outage).
		set_transient( $lock, 1, $queued ? DAY_IN_SECONDS : HOUR_IN_SECONDS );

		return $queued;
	}

	/**
	 * Note an attachment rendered without an optimized variant, to be queued after output.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private static function mark_needs_optimization( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || isset( self::$render_queue[ $attachment_id ] ) ) {
			return;
		}

		if ( empty( self::$render_queue ) ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_render_queue' ), 100 );
		}

		self::$render_queue[ $attachment_id ] = true;
	}

	/**
	 * Queue missing variants for images seen on this request, after the response is sent.
	 */
	public static function flush_render_queue() {
		$ids = array_keys( self::$render_queue );
		self::$render_queue = array();
		if ( empty( $ids ) ) {
			return;
		}

		// Cap per request so a large gallery cannot stall shutdown on API calls.
		$ids = array_slice( $ids, 0, 5 );
		foreach ( $ids as $attachment_id ) {
			self::ensure_queued( $attachment_id );
		}
	}

	/**
	 * Schedule the one-time library pass for sites that enabled cloud media before
	 * backfill existed (their library has no optimized variants and nothing re-queues it).
	 */
	private static function maybe_schedule_initial_backfill() {
		if ( get_option( self::OPTION_BACKFILL_DONE ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_BACKFILL ) ) {
			return;
		}

		$want_image = CacheRocket_Options::get( 'cloud_image_opt' ) && CacheRocket_Plan::can_use_image_optimization();
		$want_lqip  = CacheRocket_Options::get( 'cloud_lqip' ) && CacheRocket_Plan::can_use_lqip();
		if ( ! $want_image && ! $want_lqip ) {
			return;
		}

		wp_schedule_single_event( time() + 60, self::CRON_BACKFILL );
	}

	/**
	 * Queue existing library images in batches after image optimization is switched on.
	 */
	public static function run_backfill() {
		$want_image = CacheRocket_Options::get( 'cloud_image_opt' ) && CacheRocket_Plan::can_use_image_optimization();
		$want_lqip  = CacheRocket_Options::get( 'cloud_lqip' ) && CacheRocket_Plan::can_use_lqip();
		if ( ! $want_image && ! $want_lqip ) {
			delete_option( self::OPTION_BACKFILL );
			update_option( self::OPTION_BACKFILL_DONE, 1, false );
			return;
		}

		$offset = (int) get_option( self::OPTION_BACKFILL, 0 );

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 20,
				'offset'         => max( 0, $offset ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			delete_option( self::OPTION_BACKFILL );
			update_option( self::OPTION_BACKFILL_DONE, 1, false );
			return;
		}

		foreach ( $ids as $attachment_id ) {
			self::ensure_queued( (int) $attachment_id );
		}

		update_option( self::OPTION_BACKFILL, $offset + count( $ids ), false );

		if ( ! wp_next_scheduled( self::CRON_BACKFILL ) ) {
			wp_schedule_single_event( time() + 120, self::CRON_BACKFILL );
		}
	}

	/**
	 * Start a library backfill when a cloud media feature is switched on.
	 *
	 * @param array<string, mixed> $old_settings Previous settings.
	 * @param array<string, mixed> $new_settings New settings.
	 */
	public static function maybe_backfill_on_enable( $old_settings, $new_settings ) {
		$old_settings = is_array( $old_settings ) ? $old_settings : array();
		$new_settings = is_array( $new_settings ) ? $new_settings : array();

		$enabled = false;
		foreach ( array( 'cloud_image_opt', 'cloud_lqip' ) as $option_key ) {
			if ( empty( $old_settings[ $option_key ] ) && ! empty( $new_settings[ $option_key ] ) ) {
				$enabled = true;
			}
		}

		if ( ! $enabled ) {
			return;
		}

		delete_option( self::OPTION_BACKFILL );
		delete_option( self::OPTION_BACKFILL_DONE );
		self::bump_lock_generation();
		if ( ! wp_next_scheduled( self::CRON_BACKFILL ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_BACKFILL );
		}
	}

	/**
	 * Create a remote job and track it for polling.
	 *
	 * @param string               $kind    Job kind.
	 * @param string               $url     Source URL.
	 * @param array<string, mixed> $context Local context stored with the pending job.
	 * @param array<string, mixed> $request Extra request options.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function queue_job( $kind, $url, $context = array(), $request = array() ) {
		$result = cacherocket_create_optimization_job(
			array(
				'kind'      => $kind,
				'sourceUrl' => $url,
				'siteKey'   => self::site_key(),
				'request'   => $request,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$job_id = isset( $result['id'] ) ? (string) $result['id'] : '';
		if ( '' === $job_id ) {
			return new WP_Error( 'no_job_id', __( 'Optimization job created without an id.', 'cache-rocket' ) );
		}

		$pending = get_transient( self::TRANSIENT_JOBS );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}
		$pending[ $job_id ] = array(
			'kind'    => $kind,
			'context' => $context,
			'queued'  => time(),
		);
		set_transient( self::TRANSIENT_JOBS, $pending, WEEK_IN_SECONDS );

		// Apply immediately when the API already returned a finished job.
		$status = isset( $result['status'] ) ? (string) $result['status'] : '';
		if ( 'completed' === $status ) {
			self::apply_job_result( $result, $context );
			unset( $pending[ $job_id ] );
			if ( empty( $pending ) ) {
				delete_transient( self::TRANSIENT_JOBS );
			} else {
				set_transient( self::TRANSIENT_JOBS, $pending, WEEK_IN_SECONDS );
			}
			if ( in_array( $kind, array( 'imageOpt', 'lqip', 'criticalCss' ), true ) ) {
				CacheRocket_Cache::purge_all();
			}
			return $result;
		}

		self::schedule_fast_polls();

		return $result;
	}

	/**
	 * Poll pending jobs and apply completed results.
	 *
	 * @param mixed ...$unused Optional WP-Cron args (used only to uniquify schedules).
	 */
	public static function poll_pending_jobs( ...$unused ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $unused );
		$pending = get_transient( self::TRANSIENT_JOBS );
		if ( ! is_array( $pending ) || empty( $pending ) ) {
			return;
		}

		$remaining   = array();
		$needs_purge = false;
		foreach ( $pending as $job_id => $info ) {
			$job = cacherocket_get_optimization_job( (string) $job_id );
			if ( is_wp_error( $job ) ) {
				$remaining[ $job_id ] = $info;
				continue;
			}

			$status = isset( $job['status'] ) ? (string) $job['status'] : '';
			if ( 'completed' === $status ) {
				self::apply_job_result( $job, isset( $info['context'] ) && is_array( $info['context'] ) ? $info['context'] : array() );
				$kind = isset( $info['kind'] ) ? (string) $info['kind'] : '';
				if ( in_array( $kind, array( 'imageOpt', 'lqip', 'criticalCss' ), true ) ) {
					$needs_purge = true;
				}
				continue;
			}
			if ( 'failed' === $status ) {
				continue;
			}

			// Drop jobs older than 7 days.
			$queued = isset( $info['queued'] ) ? (int) $info['queued'] : 0;
			if ( $queued && ( time() - $queued ) > WEEK_IN_SECONDS ) {
				continue;
			}
			$remaining[ $job_id ] = $info;
		}

		if ( empty( $remaining ) ) {
			delete_transient( self::TRANSIENT_JOBS );
		} else {
			set_transient( self::TRANSIENT_JOBS, $remaining, WEEK_IN_SECONDS );
		}

		// Cached HTML must regenerate to pick up CDN CSS / optimized image URLs.
		if ( $needs_purge ) {
			CacheRocket_Cache::purge_all();
		}
	}

	/**
	 * Persist completed job output locally.
	 *
	 * @param array<string, mixed> $job     Serialized job.
	 * @param array<string, mixed> $context Local context.
	 */
	public static function apply_job_result( $job, $context ) {
		$kind   = isset( $job['kind'] ) ? (string) $job['kind'] : '';
		$result = isset( $job['result'] ) && is_array( $job['result'] ) ? $job['result'] : array();

		if ( 'imageOpt' === $kind && ! empty( $context['attachmentId'] ) ) {
			update_post_meta( (int) $context['attachmentId'], self::META_IMAGE, $result );
			return;
		}

		if ( 'lqip' === $kind && ! empty( $context['attachmentId'] ) ) {
			update_post_meta( (int) $context['attachmentId'], self::META_LQIP, $result );
			return;
		}

		if ( 'criticalCss' === $kind && ! empty( $context['pageUrl'] ) ) {
			$map = get_option( self::OPTION_CCSS, array() );
			if ( ! is_array( $map ) ) {
				$map = array();
			}
			$key           = md5( (string) $context['pageUrl'] );
			$map[ $key ] = array(
				'url'     => (string) $context['pageUrl'],
				'cssUrl'  => isset( $result['cssUrl'] ) ? (string) $result['cssUrl'] : '',
				'updated' => gmdate( 'c' ),
			);
			// Keep map bounded.
			if ( count( $map ) > 200 ) {
				$map = array_slice( $map, -200, null, true );
			}
			update_option( self::OPTION_CCSS, $map, false );
			return;
		}

		if ( 'pageSpeed' === $kind ) {
			update_option(
				self::OPTION_PSI,
				array(
					'sourceUrl' => isset( $job['sourceUrl'] ) ? (string) $job['sourceUrl'] : '',
					'result'    => $result,
					'updated'   => gmdate( 'c' ),
				),
				false
			);
		}
	}

	/**
	 * Queue CCSS for the current front-end URL at most once per day.
	 */
	public static function maybe_queue_page_ccss() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_preview() ) {
			return;
		}
		if ( ! is_singular() && ! is_front_page() ) {
			return;
		}

		$url  = home_url( add_query_arg( array() ) );
		$key  = self::ccss_lock_key( $url );
		if ( get_transient( $key ) ) {
			return;
		}

		$map  = get_option( self::OPTION_CCSS, array() );
		$hash = md5( $url );
		if ( is_array( $map ) && ! empty( $map[ $hash ]['cssUrl'] ) ) {
			return;
		}

		$result = self::queue_job(
			'criticalCss',
			$url,
			array( 'pageUrl' => $url ),
			array(
				'viewportWidth'  => 1280,
				'viewportHeight' => 800,
			)
		);
		// Success: avoid re-queueing the same URL all day. Failure: short backoff so a
		// purge / outage / quota blip can retry instead of waiting 24 hours.
		set_transient( $key, 1, is_wp_error( $result ) ? HOUR_IN_SECONDS : DAY_IN_SECONDS );
	}

	/**
	 * Enqueue stored critical CSS for the current URL.
	 */
	public static function enqueue_critical_css() {
		$url  = home_url( add_query_arg( array() ) );
		$map  = get_option( self::OPTION_CCSS, array() );
		$hash = md5( $url );
		if ( ! is_array( $map ) || empty( $map[ $hash ]['cssUrl'] ) ) {
			return;
		}
		$css_url = (string) $map[ $hash ]['cssUrl'];
		if ( '' === $css_url ) {
			return;
		}
		wp_enqueue_style(
			'cacherocket-critical-css',
			$css_url,
			array(),
			isset( $map[ $hash ]['updated'] ) ? (string) $map[ $hash ]['updated'] : CACHEROCKET_VERSION
		);
	}

	/**
	 * Delete cloud optimization assets for this site (local mappings + OVH/CDN).
	 *
	 * Remote purge runs first so a failed API call does not leave OVH objects
	 * without local mappings. Local data is still cleared when credentials are
	 * missing (nothing remote to sync) or when the remote purge succeeds.
	 *
	 * @param string[]|null $kinds        Optimization kinds, or null for all.
	 * @param bool          $show_notices Whether to add admin settings notices.
	 * @param bool          $requeue      When true and features stay enabled, restart image backfill / CCSS.
	 * @return array<string, mixed>|WP_Error|true API result, WP_Error, or true when skipped (no credentials).
	 */
	public static function purge_site_assets( $kinds = null, $show_notices = true, $requeue = false ) {
		if ( null === $kinds ) {
			$kinds = array( 'imageOpt', 'lqip', 'criticalCss' );
		}
		$kinds = array_values(
			array_filter(
				(array) $kinds,
				static function ( $kind ) {
					return in_array( $kind, array( 'imageOpt', 'lqip', 'criticalCss' ), true );
				}
			)
		);
		if ( empty( $kinds ) ) {
			return true;
		}

		$result       = true;
		$clear_local  = true;
		$has_creds    = (bool) get_option( 'cacherocket_api_key' ) && (bool) get_option( 'cacherocket_api_secret' );

		if ( $has_creds ) {
			$result = cacherocket_purge_optimization_assets(
				array(
					'siteKey' => self::site_key(),
					'kinds'   => $kinds,
				)
			);

			if ( is_wp_error( $result ) ) {
				// Keep local mappings so a retry / age GC can still reconcile.
				$clear_local = false;
				if ( $show_notices ) {
					add_settings_error(
						'cacherocket_messages',
						'cloud_purge_error',
						sprintf(
							/* translators: %s: error message */
							__( 'CacheRocket CDN assets could not be deleted: %s', 'cache-rocket' ),
							$result->get_error_message()
						),
						'error'
					);
				}
			} elseif ( $show_notices ) {
				$deleted = isset( $result['deletedObjects'] ) ? (int) $result['deletedObjects'] : 0;
				add_settings_error(
					'cacherocket_messages',
					'cloud_purge_ok',
					sprintf(
						/* translators: %d: number of deleted objects */
						_n(
							'Removed %d file from CacheRocket CDN storage.',
							'Removed %d files from CacheRocket CDN storage.',
							$deleted, 'cache-rocket'
						),
						$deleted
					),
					'success'
				);
			}
		}

		if ( $clear_local ) {
			self::clear_local_cloud_data( $kinds );
		}

		// Re-queue only after remote purge so a freshly created object is not deleted.
		if ( $requeue && $clear_local ) {
			$want_image = in_array( 'imageOpt', $kinds, true ) && CacheRocket_Options::get( 'cloud_image_opt' ) && CacheRocket_Plan::can_use_image_optimization();
			$want_lqip  = in_array( 'lqip', $kinds, true ) && CacheRocket_Options::get( 'cloud_lqip' ) && CacheRocket_Plan::can_use_lqip();
			if ( $want_image || $want_lqip ) {
				delete_option( self::OPTION_BACKFILL );
				delete_option( self::OPTION_BACKFILL_DONE );
				if ( ! wp_next_scheduled( self::CRON_BACKFILL ) ) {
					wp_schedule_single_event( time() + 30, self::CRON_BACKFILL );
				}
			}

			// Images have a library backfill; Critical CSS is page-driven. Kick the homepage
			// immediately so a "Clear cache" does not wait for the next front-end visit, and
			// other singular URLs re-queue on traffic once the generation stamp is bumped.
			if ( in_array( 'criticalCss', $kinds, true ) && CacheRocket_Options::get( 'cloud_critical_css' ) && CacheRocket_Plan::can_use_critical_css() ) {
				$url = home_url( '/' );
				self::queue_job(
					'criticalCss',
					$url,
					array( 'pageUrl' => $url ),
					array(
						'viewportWidth'  => 1280,
						'viewportHeight' => 800,
					)
				);
			}
		}

		return $result;
	}

	/**
	 * When cloud CDN features are turned off: clear local mappings and delete remote OVH assets.
	 *
	 * @param array<string, mixed> $old_settings Previous settings.
	 * @param array<string, mixed> $new_settings New settings.
	 */
	public static function maybe_purge_on_disable( $old_settings, $new_settings ) {
		$old_settings = is_array( $old_settings ) ? $old_settings : array();
		$new_settings = is_array( $new_settings ) ? $new_settings : array();

		$map = array(
			'cloud_image_opt'    => 'imageOpt',
			'cloud_lqip'         => 'lqip',
			'cloud_critical_css' => 'criticalCss',
		);

		$kinds = array();
		foreach ( $map as $option_key => $kind ) {
			$was = ! empty( $old_settings[ $option_key ] );
			$now = ! empty( $new_settings[ $option_key ] );
			if ( $was && ! $now ) {
				$kinds[] = $kind;
			}
		}

		if ( empty( $kinds ) ) {
			return;
		}

		self::purge_site_assets( $kinds, true, false );
	}

	/**
	 * Clear local WP mappings that point at CDN URLs for given kinds.
	 *
	 * @param string[] $kinds Optimization kinds.
	 */
	public static function clear_local_cloud_data( $kinds ) {
		$kinds = is_array( $kinds ) ? $kinds : array();

		$invalidate_locks = false;

		if ( in_array( 'imageOpt', $kinds, true ) || in_array( 'lqip', $kinds, true ) ) {
			$meta_keys = array();
			if ( in_array( 'imageOpt', $kinds, true ) ) {
				$meta_keys[] = self::META_IMAGE;
			}
			if ( in_array( 'lqip', $kinds, true ) ) {
				$meta_keys[] = self::META_LQIP;
			}
			foreach ( $meta_keys as $meta_key ) {
				delete_post_meta_by_key( $meta_key );
			}
			$invalidate_locks = true;
		}

		if ( in_array( 'criticalCss', $kinds, true ) ) {
			delete_option( self::OPTION_CCSS );
			// Drop day-long per-URL queue throttles so pages can regenerate CCSS after purge.
			$invalidate_locks = true;
		}

		if ( $invalidate_locks ) {
			self::bump_lock_generation();
		}

		$pending = get_transient( self::TRANSIENT_JOBS );
		if ( is_array( $pending ) && ! empty( $pending ) ) {
			$remaining = array();
			foreach ( $pending as $job_id => $info ) {
				$kind = isset( $info['kind'] ) ? (string) $info['kind'] : '';
				if ( ! in_array( $kind, $kinds, true ) ) {
					$remaining[ $job_id ] = $info;
				}
			}
			if ( empty( $remaining ) ) {
				delete_transient( self::TRANSIENT_JOBS );
			} else {
				set_transient( self::TRANSIENT_JOBS, $remaining, WEEK_IN_SECONDS );
			}
		}
	}

	/**
	 * Pick the preferred optimized CDN URL from imageOpt formats.
	 *
	 * @param array<string, mixed> $formats Job result formats map.
	 * @return array<int, string> Preferred URLs (primary first, optional fallback second).
	 */
	public static function preferred_optimized_urls( $formats ) {
		if ( ! is_array( $formats ) ) {
			return array();
		}

		$prefer = array();
		if ( CacheRocket_Options::get( 'cloud_avif' ) && ! empty( $formats['avif']['url'] ) ) {
			$prefer[] = (string) $formats['avif']['url'];
		}
		if ( CacheRocket_Options::get( 'cloud_webp' ) && ! empty( $formats['webp']['url'] ) ) {
			$prefer[] = (string) $formats['webp']['url'];
		}
		if ( empty( $prefer ) && ! empty( $formats['jpeg']['url'] ) ) {
			$prefer[] = (string) $formats['jpeg']['url'];
		}

		return $prefer;
	}

	/**
	 * Rewrite an <img> tag to use a single optimized CDN URL.
	 *
	 * Drops srcset/sizes so modern browsers do not prefer original upload candidates.
	 *
	 * @param string $tag HTML img tag.
	 * @param string $url Optimized CDN URL.
	 * @return string
	 */
	public static function apply_optimized_url_to_img_tag( $tag, $url ) {
		$safe = esc_url( $url );
		if ( '' === $safe ) {
			return $tag;
		}

		if ( preg_match( '/\ssrc=(["\'])(.*?)\1/i', $tag ) ) {
			$tag = preg_replace( '/\ssrc=(["\'])(.*?)\1/i', ' src=$1' . $safe . '$1', $tag, 1 );
		} else {
			$tag = preg_replace( '/<img\b/i', '<img src="' . $safe . '"', $tag, 1 );
		}

		// Original responsive candidates would win over src in supporting browsers.
		$tag = preg_replace( '/\ssrcset=(["\'])(.*?)\1/i', '', $tag, 1 );
		$tag = preg_replace( '/\ssizes=(["\'])(.*?)\1/i', '', $tag, 1 );

		return is_string( $tag ) ? $tag : '';
	}

	/**
	 * Prefer optimized CDN URLs on attachment images.
	 *
	 * @param array<string, string> $attr       Attributes.
	 * @param WP_Post               $attachment Attachment.
	 * @return array<string, string>
	 */
	public static function attachment_image_attrs( $attr, $attachment ) {
		if ( ! is_array( $attr ) || ! $attachment instanceof WP_Post ) {
			return $attr;
		}
		if ( self::is_attachment_ignored( $attachment->ID ) ) {
			return $attr;
		}

		// Preferred path: responsive on-demand CDN with a real srcset.
		if ( self::on_demand_enabled() ) {
			$origin = self::attachment_source_url( $attachment->ID );
			if ( '' !== $origin && ! self::is_excluded_url( $origin ) ) {
				$width = 0;
				$meta  = wp_get_attachment_metadata( $attachment->ID );
				if ( is_array( $meta ) && ! empty( $meta['width'] ) ) {
					$width = (int) $meta['width'];
				}
				if ( $width <= 0 ) {
					$width = (int) CacheRocket_Options::get( 'cloud_image_max_width', 2560 );
				}

				$src    = self::build_edge_url( $origin, $width, 0, 'auto' );
				$srcset = self::edge_srcset( $origin );
				if ( '' !== $src ) {
					$attr['src'] = $src;
					if ( '' !== $srcset ) {
						$attr['srcset'] = $srcset;
						if ( empty( $attr['sizes'] ) ) {
							$attr['sizes'] = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
						}
					} else {
						unset( $attr['srcset'], $attr['sizes'] );
					}
					// New uploads still queue a batch job for backup / metering.
					self::mark_needs_optimization( $attachment->ID );
					return $attr;
				}
			}
		}

		// Fallback: single pre-baked optimized variant.
		$meta = get_post_meta( $attachment->ID, self::META_IMAGE, true );
		if ( ! is_array( $meta ) || empty( $meta['formats'] ) || ! is_array( $meta['formats'] ) ) {
			self::mark_needs_optimization( $attachment->ID );
			return $attr;
		}

		$prefer = self::preferred_optimized_urls( $meta['formats'] );
		if ( empty( $prefer ) ) {
			return $attr;
		}

		$attr['src'] = $prefer[0];
		// Single CDN variant — remove responsive originals so src is used.
		unset( $attr['srcset'], $attr['sizes'] );
		if ( count( $prefer ) > 1 ) {
			$attr['data-cacherocket-src-alt'] = $prefer[1];
		}
		return $attr;
	}

	/**
	 * Apply LQIP as placeholder background / data attribute.
	 *
	 * @param array<string, string> $attr       Attributes.
	 * @param WP_Post               $attachment Attachment.
	 * @return array<string, string>
	 */
	public static function attachment_lqip_attrs( $attr, $attachment ) {
		if ( ! is_array( $attr ) || ! $attachment instanceof WP_Post ) {
			return $attr;
		}
		if ( self::is_attachment_ignored( $attachment->ID ) ) {
			return $attr;
		}
		$meta = get_post_meta( $attachment->ID, self::META_LQIP, true );
		if ( ! is_array( $meta ) ) {
			return $attr;
		}
		if ( ! empty( $meta['dataUri'] ) ) {
			$attr['data-cacherocket-lqip'] = (string) $meta['dataUri'];
			$style = isset( $attr['style'] ) ? (string) $attr['style'] : '';
			$attr['style'] = trim( $style . ';background-image:url(' . esc_attr( (string) $meta['dataUri'] ) . ');background-size:cover;' );
		}
		return $attr;
	}

	/**
	 * Rewrite content <img> tags that reference attachment URLs with opt variants.
	 *
	 * @param string $content HTML.
	 * @return string
	 */
	public static function rewrite_content_images( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		if ( self::on_demand_enabled() ) {
			$content = self::rewrite_content_images_edge( $content );
			if ( CacheRocket_Options::get( 'lazyload_css_bg' ) || CacheRocket_Options::get( 'cloud_image_cdn' ) ) {
				$content = self::rewrite_inline_background_images( $content );
			}
			return $content;
		}

		if ( false === strpos( $content, '<img' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<img\b[^>]+>/i',
			static function ( $m ) {
				$tag = $m[0];
				if ( ! preg_match( '/\bwp-image-(\d+)\b/', $tag, $idm ) ) {
					return $tag;
				}
				$attachment_id = (int) $idm[1];
				if ( self::is_attachment_ignored( $attachment_id ) ) {
					return $tag;
				}
				$meta          = get_post_meta( $attachment_id, self::META_IMAGE, true );
				if ( ! is_array( $meta ) || empty( $meta['formats'] ) || ! is_array( $meta['formats'] ) ) {
					self::mark_needs_optimization( $attachment_id );
					return $tag;
				}
				$prefer = self::preferred_optimized_urls( $meta['formats'] );
				if ( empty( $prefer ) ) {
					return $tag;
				}
				return self::apply_optimized_url_to_img_tag( $tag, $prefer[0] );
			},
			$content
		);
	}

	/**
	 * Rewrite every uploads-hosted <img> to the on-demand CDN with a fresh srcset.
	 * Works for builder markup and bare uploads (no wp-image-{id} class required).
	 *
	 * @param string $content HTML.
	 * @return string
	 */
	private static function rewrite_content_images_edge( $content ) {
		if ( false === strpos( $content, '<img' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( $m ) {
				$tag = $m[0];
				if ( ! preg_match( '/\ssrc=(["\'])(.*?)\1/i', $tag, $srcm ) ) {
					return $tag;
				}
				$src = trim( $srcm[2] );
				if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
					return $tag;
				}

				$origin = self::to_absolute_uploads_url( $src );
				if ( '' === $origin || self::is_excluded_url( $origin ) ) {
					return $tag;
				}

				// Target width from an explicit width attribute, else the configured max.
				$width = 0;
				if ( preg_match( '/\swidth=(["\']?)(\d+)\1/i', $tag, $wm ) ) {
					$width = (int) $wm[2];
				}
				if ( $width <= 0 ) {
					$width = (int) CacheRocket_Options::get( 'cloud_image_max_width', 2560 );
				}

				$edge_src = self::build_edge_url( $origin, $width, 0, 'auto' );
				if ( '' === $edge_src ) {
					return $tag;
				}
				$srcset = self::edge_srcset( $origin );

				return self::apply_edge_to_img_tag( $tag, $edge_src, $srcset, $width );
			},
			$content
		);
	}

	/**
	 * Rewrite inline style="background-image:url(...)" to on-demand CDN URLs.
	 *
	 * @param string $content HTML.
	 * @return string
	 */
	private static function rewrite_inline_background_images( $content ) {
		if ( false === stripos( $content, 'background' ) || false === strpos( $content, 'url(' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			static function ( $m ) {
				$url    = trim( $m[2] );
				$origin = self::to_absolute_uploads_url( $url );
				if ( '' === $origin || self::is_excluded_url( $origin ) ) {
					return $m[0];
				}
				$max  = (int) CacheRocket_Options::get( 'cloud_image_max_width', 2560 );
				$edge = self::build_edge_url( $origin, $max > 0 ? $max : 2560, 0, 'auto' );
				if ( '' === $edge ) {
					return $m[0];
				}
				return 'url(' . $m[1] . esc_url( $edge ) . $m[1] . ')';
			},
			$content
		);
	}

	/**
	 * Resolve a possibly-relative src to an absolute URL if it lives in the uploads dir.
	 * Returns '' when the URL is not an on-site uploads image we should rewrite.
	 *
	 * @param string $src Raw src value.
	 * @return string Absolute uploads URL, or ''.
	 */
	public static function to_absolute_uploads_url( $src ) {
		$src = trim( (string) $src );
		if ( '' === $src ) {
			return '';
		}

		// Only rewrite common raster/vector image extensions.
		if ( ! preg_match( '/\.(jpe?g|png|gif|webp|avif|bmp|tiff?)(\?.*)?$/i', $src ) ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
		$baseurl = preg_replace( '#^https?:#', '', $baseurl );

		if ( 0 === strpos( $src, '//' ) ) {
			$abs = ( is_ssl() ? 'https:' : 'http:' ) . $src;
		} elseif ( 0 === strpos( $src, 'http' ) ) {
			$abs = $src;
		} elseif ( 0 === strpos( $src, '/' ) ) {
			$abs = home_url( $src );
		} else {
			return '';
		}

		$scheme_less = preg_replace( '#^https?:#', '', $abs );
		if ( '' !== $baseurl && false === strpos( $scheme_less, $baseurl ) ) {
			// Not in the uploads directory — skip (avoids rewriting theme sprites, etc.).
			return '';
		}

		return $abs;
	}

	/**
	 * Apply an on-demand edge src + srcset to an <img> tag, preserving sizes.
	 *
	 * @param string $tag    HTML img tag.
	 * @param string $src    Edge src URL.
	 * @param string $srcset Edge srcset (may be empty).
	 * @param int    $width  Intrinsic width used for a default sizes attribute.
	 * @return string
	 */
	public static function apply_edge_to_img_tag( $tag, $src, $srcset, $width = 0 ) {
		$safe_src = esc_url( $src );
		if ( '' === $safe_src ) {
			return $tag;
		}

		if ( preg_match( '/\ssrc=(["\'])(.*?)\1/i', $tag ) ) {
			$tag = preg_replace( '/\ssrc=(["\'])(.*?)\1/i', ' src=$1' . $safe_src . '$1', $tag, 1 );
		} else {
			$tag = preg_replace( '/<img\b/i', '<img src="' . $safe_src . '"', $tag, 1 );
		}

		if ( '' !== $srcset ) {
			if ( preg_match( '/\ssrcset=(["\'])(.*?)\1/i', $tag ) ) {
				$tag = preg_replace( '/\ssrcset=(["\'])(.*?)\1/i', ' srcset=$1' . $srcset . '$1', $tag, 1 );
			} else {
				$tag = preg_replace( '/<img\b/i', '<img srcset="' . $srcset . '"', $tag, 1 );
			}
			// Provide sizes when the theme did not (keeps responsive selection sane).
			if ( ! preg_match( '/\ssizes=(["\'])(.*?)\1/i', $tag ) && $width > 0 ) {
				$sizes = '(max-width: ' . (int) $width . 'px) 100vw, ' . (int) $width . 'px';
				$tag   = preg_replace( '/<img\b/i', '<img sizes="' . esc_attr( $sizes ) . '"', $tag, 1 );
			}
		} else {
			$tag = preg_replace( '/\ssrcset=(["\'])(.*?)\1/i', '', $tag, 1 );
			$tag = preg_replace( '/\ssizes=(["\'])(.*?)\1/i', '', $tag, 1 );
		}

		return is_string( $tag ) ? $tag : '';
	}

	/**
	 * AJAX: queue PageSpeed for the site home URL.
	 */
	public static function ajax_run_pagespeed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		if ( ! CacheRocket_Plan::can_use_page_speed_scores() ) {
			wp_send_json_error( array( 'message' => __( 'PageSpeed scores are not included in your plan.', 'cache-rocket' ) ), 403 );
		}

		$strategy = isset( $_POST['strategy'] ) && 'desktop' === $_POST['strategy'] ? 'desktop' : 'mobile';
		$result   = self::queue_job(
			'pageSpeed',
			home_url( '/' ),
			array( 'pageSpeed' => true ),
			array( 'strategy' => $strategy )
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: queue critical CSS for a URL.
	 */
	public static function ajax_queue_ccss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		if ( ! CacheRocket_Plan::can_use_critical_css() ) {
			wp_send_json_error( array( 'message' => __( 'Critical CSS is not included in your plan.', 'cache-rocket' ) ), 403 );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['url'] ) ) : home_url( '/' );
		if ( ! $url ) {
			$url = home_url( '/' );
		}

		$result = self::queue_job( 'criticalCss', $url, array( 'pageUrl' => $url ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Count library images and how many already have an optimized variant.
	 *
	 * @return array{total:int, optimized:int, pending:int}
	 */
	public static function library_stats() {
		$total = (int) wp_count_attachments()->{'image/jpeg'} +
			(int) wp_count_attachments()->{'image/png'} +
			(int) wp_count_attachments()->{'image/gif'} +
			(int) wp_count_attachments()->{'image/webp'};

		// Fall back to an accurate query when mime tallies are unavailable.
		$all = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$total     = is_array( $all ) ? count( $all ) : $total;
		$optimized = 0;
		if ( is_array( $all ) ) {
			foreach ( $all as $id ) {
				$meta = get_post_meta( (int) $id, self::META_IMAGE, true );
				if ( is_array( $meta ) && ! empty( $meta['formats'] ) ) {
					$optimized++;
				}
			}
		}
		$pending = get_transient( self::TRANSIENT_JOBS );
		return array(
			'total'     => $total,
			'optimized' => $optimized,
			'pending'   => is_array( $pending ) ? count( $pending ) : 0,
		);
	}

	/**
	 * AJAX: queue a batch of unoptimized library images.
	 */
	public static function ajax_bulk_optimize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		if ( ! CacheRocket_Plan::can_use_image_optimization() ) {
			wp_send_json_error( array( 'message' => __( 'Image optimization is not included in your plan.', 'cache-rocket' ) ), 403 );
		}
		if ( ! CacheRocket_Plan::has_image_storage_remaining() ) {
			wp_send_json_error( array( 'message' => __( 'Image storage limit reached for your plan.', 'cache-rocket' ) ), 402 );
		}

		$batch = isset( $_POST['batch'] ) ? max( 1, min( 25, (int) $_POST['batch'] ) ) : 10;

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $batch,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => self::META_IMAGE,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_IGNORE,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$queued = 0;
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				if ( self::ensure_queued( (int) $id ) ) {
					$queued++;
				}
			}
		}

		wp_send_json_success(
			array(
				'queued' => $queued,
				'stats'  => self::library_stats(),
			)
		);
	}

	/**
	 * AJAX: report bulk optimization progress.
	 */
	public static function ajax_bulk_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );
		self::maybe_poll_pending( 5 );
		wp_send_json_success(
			array(
				'stats'   => self::library_stats(),
				'storage' => CacheRocket_Plan::image_storage_usage(),
			)
		);
	}

	/**
	 * AJAX: optimize a single attachment now.
	 */
	public static function ajax_optimize_attachment() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		if ( ! CacheRocket_Plan::can_use_image_optimization() ) {
			wp_send_json_error( array( 'message' => __( 'Image optimization is not included in your plan.', 'cache-rocket' ) ), 403 );
		}

		$attachment_id = isset( $_POST['attachmentId'] ) ? (int) $_POST['attachmentId'] : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'cache-rocket' ) ), 400 );
		}
		if ( self::is_attachment_ignored( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This image is excluded from optimization.', 'cache-rocket' ) ), 400 );
		}

		// Clear the throttle so a manual request re-queues immediately.
		delete_transient( self::lock_key( $attachment_id ) );
		$queued = self::ensure_queued( $attachment_id );
		if ( ! $queued ) {
			wp_send_json_success( array( 'message' => __( 'Already optimized or nothing to do.', 'cache-rocket' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Queued for optimization.', 'cache-rocket' ) ) );
	}

	/**
	 * AJAX: restore an attachment to its original (drop optimized variants).
	 */
	public static function ajax_restore_attachment() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		$attachment_id = isset( $_POST['attachmentId'] ) ? (int) $_POST['attachmentId'] : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'cache-rocket' ) ), 400 );
		}

		self::restore_attachment( $attachment_id );

		wp_send_json_success( array( 'message' => __( 'Restored original. The site now serves the unoptimized image.', 'cache-rocket' ) ) );
	}

	/**
	 * AJAX: exclude or re-include an attachment from cloud optimization / CDN rewrite.
	 */
	public static function ajax_ignore_attachment() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		$attachment_id = isset( $_POST['attachmentId'] ) ? (int) $_POST['attachmentId'] : 0;
		$ignore        = ! isset( $_POST['ignore'] ) || ! empty( $_POST['ignore'] );
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'cache-rocket' ) ), 400 );
		}

		if ( $ignore ) {
			update_post_meta( $attachment_id, self::META_IGNORE, 1 );
			// Drop any delivered variants so front-end immediately serves origin.
			self::restore_attachment( $attachment_id );
			wp_send_json_success(
				array(
					'ignored' => true,
					'message' => __( 'Excluded from optimization. This image stays on your origin.', 'cache-rocket' ),
				)
			);
		}

		delete_post_meta( $attachment_id, self::META_IGNORE );
		delete_transient( self::lock_key( $attachment_id ) );
		wp_send_json_success(
			array(
				'ignored' => false,
				'message' => __( 'Included again. Use Optimize to queue it.', 'cache-rocket' ),
			)
		);
	}

	/**
	 * Restore an attachment to its original: drop delivered variants remotely + local mappings.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool Whether the attachment was valid and restored.
	 */
	public static function restore_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return false;
		}

		$source_url = self::attachment_source_url( $attachment_id );
		if ( '' !== $source_url && get_option( 'cacherocket_api_key' ) && get_option( 'cacherocket_api_secret' ) ) {
			cacherocket_restore_optimization(
				array(
					'siteKey'   => self::site_key(),
					'sourceUrl' => $source_url,
				)
			);
		}

		delete_post_meta( $attachment_id, self::META_IMAGE );
		delete_post_meta( $attachment_id, self::META_LQIP );
		delete_transient( self::lock_key( $attachment_id ) );

		return true;
	}

	/**
	 * AJAX: queue extra directories (theme/plugin images) for optimization.
	 */
	public static function ajax_optimize_directory() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		if ( ! CacheRocket_Plan::can_use_directory_optimize() ) {
			wp_send_json_error( array( 'message' => __( 'Directory optimization requires the Grow plan.', 'cache-rocket' ) ), 403 );
		}

		$urls   = self::collect_directory_image_urls();
		$queued = 0;
		$errors = 0;
		foreach ( array_slice( $urls, 0, 25 ) as $url ) {
			if ( self::is_excluded_url( $url ) ) {
				continue;
			}
			$result = self::queue_job( 'imageOpt', $url, array( 'directory' => true ), self::image_request_options() );
			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$queued++;
			}
		}

		wp_send_json_success(
			array(
				'queued' => $queued,
				'errors' => $errors,
				'found'  => count( $urls ),
			)
		);
	}

	/**
	 * AJAX: list recent cloud optimization jobs for the job history UI.
	 */
	public static function ajax_list_jobs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		$jobs = cacherocket_list_optimization_jobs( array( 'limit' => 20 ) );
		if ( is_wp_error( $jobs ) ) {
			wp_send_json_error( array( 'message' => $jobs->get_error_message() ) );
		}

		$list = array();
		$rows = is_array( $jobs ) && isset( $jobs['jobs'] ) && is_array( $jobs['jobs'] ) ? $jobs['jobs'] : ( is_array( $jobs ) ? $jobs : array() );
		foreach ( $rows as $job ) {
			if ( ! is_array( $job ) ) {
				continue;
			}
			$list[] = array(
				'kind'      => isset( $job['kind'] ) ? (string) $job['kind'] : '',
				'status'    => isset( $job['status'] ) ? (string) $job['status'] : '',
				'sourceUrl' => isset( $job['sourceUrl'] ) ? (string) $job['sourceUrl'] : '',
				'updatedAt' => isset( $job['updatedAt'] ) ? (string) $job['updatedAt'] : ( isset( $job['createdAt'] ) ? (string) $job['createdAt'] : '' ),
			);
		}

		wp_send_json_success( array( 'jobs' => $list ) );
	}

	/**
	 * Collect candidate image URLs from configured directories (under WP root).
	 *
	 * @return string[] Absolute URLs.
	 */
	public static function collect_directory_image_urls() {
		$paths = CacheRocket_Options::lines( 'cloud_directory_paths' );
		if ( empty( $paths ) ) {
			return array();
		}

		$root     = untrailingslashit( ABSPATH );
		$site_url = untrailingslashit( site_url() );
		$urls     = array();

		foreach ( $paths as $rel ) {
			// Confine to the WordPress install root (no traversal).
			$rel  = ltrim( str_replace( '..', '', (string) $rel ), '/' );
			$abs  = $root . '/' . $rel;
			$real = realpath( $abs );
			if ( false === $real || 0 !== strpos( $real, $root ) || ! is_dir( $real ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() ) {
					continue;
				}
				$ext = strtolower( pathinfo( $file->getPathname(), PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
					continue;
				}
				$file_rel = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
				$urls[]   = $site_url . '/' . str_replace( '\\', '/', $file_rel );
				if ( count( $urls ) >= 200 ) {
					break 2;
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Add the CacheRocket column to the Media Library list view.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function media_column_header( $columns ) {
		if ( is_array( $columns ) ) {
			$columns['cacherocket_opt'] = __( 'CacheRocket', 'cache-rocket' );
		}
		return $columns;
	}

	/**
	 * Render the CacheRocket column content for an attachment.
	 *
	 * @param string $column_name Column key.
	 * @param int    $attachment_id Attachment ID.
	 */
	public static function media_column_content( $column_name, $attachment_id ) {
		if ( 'cacherocket_opt' !== $column_name ) {
			return;
		}
		$attachment_id = (int) $attachment_id;
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			echo '<span class="cr-media-status cr-media-status--na">' . esc_html__( '—', 'cache-rocket' ) . '</span>';
			return;
		}

		$ignored   = self::is_attachment_ignored( $attachment_id );
		$meta      = get_post_meta( $attachment_id, self::META_IMAGE, true );
		$optimized = is_array( $meta ) && ! empty( $meta['formats'] ) && is_array( $meta['formats'] );

		$saved_label = '';
		if ( $optimized && ! empty( $meta['originalBytes'] ) && ! empty( $meta['bytesOut'] ) ) {
			$orig = (float) $meta['originalBytes'];
			$out  = (float) $meta['bytesOut'];
			if ( $orig > 0 && $out > 0 && $out < $orig ) {
				$pct         = round( ( 1 - ( $out / $orig ) ) * 100 );
				$saved_label = sprintf( /* translators: %d: percent saved */ __( '%d%% smaller', 'cache-rocket' ), $pct );
			}
		}

		$nonce = wp_create_nonce( 'cacherocket_cloud_opt' );
		echo '<div class="cr-media-cell" data-attachment="' . esc_attr( (string) $attachment_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
		if ( $ignored ) {
			echo '<span class="cr-media-status cr-media-status--ignored">' . esc_html__( 'Excluded', 'cache-rocket' ) . '</span>';
			echo ' <button type="button" class="button-link cr-media-include">' . esc_html__( 'Include', 'cache-rocket' ) . '</button>';
		} elseif ( $optimized ) {
			echo '<span class="cr-media-status cr-media-status--ok">' . esc_html__( 'Optimized', 'cache-rocket' ) . '</span>';
			if ( $saved_label ) {
				echo ' <span class="cr-media-saved">' . esc_html( $saved_label ) . '</span>';
			}
			echo ' <button type="button" class="button-link cr-media-restore">' . esc_html__( 'Restore', 'cache-rocket' ) . '</button>';
			echo ' <button type="button" class="button-link cr-media-exclude">' . esc_html__( 'Exclude', 'cache-rocket' ) . '</button>';
		} else {
			echo '<span class="cr-media-status cr-media-status--pending">' . esc_html__( 'Not optimized', 'cache-rocket' ) . '</span>';
			echo ' <button type="button" class="button-link cr-media-optimize">' . esc_html__( 'Optimize', 'cache-rocket' ) . '</button>';
			echo ' <button type="button" class="button-link cr-media-exclude">' . esc_html__( 'Exclude', 'cache-rocket' ) . '</button>';
		}
		echo '<span class="cr-media-msg" aria-live="polite"></span>';
		echo '</div>';
	}

	/**
	 * Add an "Exclude from CacheRocket optimization" field in Attachment Details (modal + edit screen).
	 *
	 * @param array<string, array<string, mixed>> $form_fields Fields.
	 * @param WP_Post                             $post        Attachment.
	 * @return array<string, array<string, mixed>>
	 */
	public static function attachment_fields_to_edit( $form_fields, $post ) {
		if ( ! $post instanceof WP_Post || ! wp_attachment_is_image( $post->ID ) ) {
			return $form_fields;
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return $form_fields;
		}

		$ignored = self::is_attachment_ignored( $post->ID );
		$form_fields['cacherocket_opt_ignore'] = array(
			'label' => __( 'CacheRocket', 'cache-rocket' ),
			'input' => 'html',
			'html'  => sprintf(
				'<label><input type="checkbox" name="attachments[%1$d][cacherocket_opt_ignore]" value="1" %2$s /> %3$s</label><p class="help">%4$s</p>',
				(int) $post->ID,
				checked( $ignored, true, false ),
				esc_html__( 'Exclude from optimization & CDN rewrite', 'cache-rocket' ),
				esc_html__( 'Keeps this image on your origin. Does not change the File URL in the media library.', 'cache-rocket' )
			),
			'show_in_edit'   => true,
			'show_in_modal'  => true,
		);

		return $form_fields;
	}

	/**
	 * Persist the per-attachment exclude checkbox from Attachment Details.
	 *
	 * @param array<string, mixed> $post       Attachment fields.
	 * @param array<string, mixed> $attachment Submitted attachment data.
	 * @return array<string, mixed>
	 */
	public static function attachment_fields_to_save( $post, $attachment ) {
		$id = isset( $post['ID'] ) ? (int) $post['ID'] : 0;
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return $post;
		}

		$ignore = ! empty( $attachment['cacherocket_opt_ignore'] );
		if ( $ignore ) {
			if ( ! self::is_attachment_ignored( $id ) ) {
				update_post_meta( $id, self::META_IGNORE, 1 );
				self::restore_attachment( $id );
			}
		} else {
			delete_post_meta( $id, self::META_IGNORE );
		}

		return $post;
	}
}
