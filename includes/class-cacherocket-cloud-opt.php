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
	const OPTION_CCSS          = 'cacherocket_ccss_map';
	const OPTION_PSI           = 'cacherocket_pagespeed_last';
	const OPTION_BACKFILL      = 'cacherocket_opt_backfill_cursor';
	const OPTION_BACKFILL_DONE = 'cacherocket_opt_backfill_done';
	const OPTION_LOCK_GEN      = 'cacherocket_opt_lock_gen';
	const CRON_POLL            = 'cacherocket_poll_opt_jobs';
	const CRON_BACKFILL        = 'cacherocket_backfill_opt_jobs';
	const TRANSIENT_JOBS       = 'cacherocket_pending_opt_jobs';

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
		add_action( self::CRON_POLL, array( __CLASS__, 'poll_pending_jobs' ) );
		add_action( self::CRON_BACKFILL, array( __CLASS__, 'run_backfill' ) );
		add_action( 'wp_ajax_cacherocket_run_pagespeed', array( __CLASS__, 'ajax_run_pagespeed' ) );
		add_action( 'wp_ajax_cacherocket_queue_ccss', array( __CLASS__, 'ajax_queue_ccss' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_poll_on_admin' ), 50 );

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

		if ( CacheRocket_Options::get( 'cloud_critical_css' ) && CacheRocket_Plan::can_use_critical_css() ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_critical_css' ), 1 );
			add_action( 'template_redirect', array( __CLASS__, 'maybe_queue_page_ccss' ), 5 );
		}

		if ( CacheRocket_Options::get( 'cloud_image_opt' ) && CacheRocket_Plan::can_use_image_optimization() ) {
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'attachment_image_attrs' ), 20, 2 );
			add_filter( 'the_content', array( __CLASS__, 'rewrite_content_images' ), 25 );
		}

		if ( CacheRocket_Options::get( 'cloud_lqip' ) && CacheRocket_Plan::can_use_lqip() ) {
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
			'display'  => __( 'Every 5 minutes (CacheRocket)', 'cacherocket' ),
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
	 * Queue image + LQIP jobs when a new attachment is uploaded.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function maybe_queue_attachment( $attachment_id ) {
		self::ensure_queued( $attachment_id );
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
				)
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
			return new WP_Error( 'no_job_id', __( 'Optimization job created without an id.', 'cacherocket' ) );
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

		self::clear_local_cloud_data( $kinds );

		$result = true;
		if ( get_option( 'cacherocket_api_key' ) && get_option( 'cacherocket_api_secret' ) ) {
			$result = cacherocket_purge_optimization_assets(
				array(
					'siteKey' => self::site_key(),
					'kinds'   => $kinds,
				)
			);

			if ( $show_notices ) {
				if ( is_wp_error( $result ) ) {
					add_settings_error(
						'cacherocket_messages',
						'cloud_purge_error',
						sprintf(
							/* translators: %s: error message */
							__( 'CacheRocket CDN assets could not be deleted: %s', 'cacherocket' ),
							$result->get_error_message()
						),
						'error'
					);
				} else {
					$deleted = isset( $result['deletedObjects'] ) ? (int) $result['deletedObjects'] : 0;
					add_settings_error(
						'cacherocket_messages',
						'cloud_purge_ok',
						sprintf(
							/* translators: %d: number of deleted objects */
							_n(
								'Removed %d file from CacheRocket CDN storage.',
								'Removed %d files from CacheRocket CDN storage.',
								$deleted,
								'cacherocket'
							),
							$deleted
						),
						'success'
					);
				}
			}
		}

		// Re-queue only after remote purge so a freshly created object is not deleted.
		if ( $requeue ) {
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
		if ( ! is_string( $content ) || false === strpos( $content, '<img' ) ) {
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
	 * AJAX: queue PageSpeed for the site home URL.
	 */
	public static function ajax_run_pagespeed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'cacherocket_cloud_opt', 'nonce' );

		if ( ! CacheRocket_Plan::can_use_page_speed_scores() ) {
			wp_send_json_error( array( 'message' => __( 'PageSpeed scores are not included in your plan.', 'cacherocket' ) ), 403 );
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
			wp_send_json_error( array( 'message' => __( 'Critical CSS is not included in your plan.', 'cacherocket' ) ), 403 );
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
}
