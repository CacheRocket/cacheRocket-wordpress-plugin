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

	const META_IMAGE   = '_cacherocket_image_opt';
	const META_LQIP    = '_cacherocket_lqip';
	const OPTION_CCSS  = 'cacherocket_ccss_map';
	const OPTION_PSI   = 'cacherocket_pagespeed_last';
	const CRON_POLL    = 'cacherocket_poll_opt_jobs';
	const TRANSIENT_JOBS = 'cacherocket_pending_opt_jobs';

	/**
	 * Boot hooks.
	 */
	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'maybe_queue_attachment' ) );
		add_action( self::CRON_POLL, array( __CLASS__, 'poll_pending_jobs' ) );
		add_action( 'wp_ajax_cacherocket_run_pagespeed', array( __CLASS__, 'ajax_run_pagespeed' ) );
		add_action( 'wp_ajax_cacherocket_queue_ccss', array( __CLASS__, 'ajax_queue_ccss' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_poll_on_admin' ), 50 );

		if ( ! wp_next_scheduled( self::CRON_POLL ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::CRON_POLL );
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( CacheRocket_Options::get( 'cloud_critical_css' ) && CacheRocket_Plan::can_use_critical_css() ) {
			add_action( 'wp_head', array( __CLASS__, 'inject_critical_css' ), 1 );
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
	 * Light admin poll so PageSpeed / image results show without waiting for cron.
	 */
	public static function maybe_poll_on_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$pending = get_transient( self::TRANSIENT_JOBS );
		if ( ! is_array( $pending ) || empty( $pending ) ) {
			return;
		}
		$lock = get_transient( 'cacherocket_opt_poll_lock' );
		if ( $lock ) {
			return;
		}
		set_transient( 'cacherocket_opt_poll_lock', 1, 30 );
		self::poll_pending_jobs();
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
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return;
		}

		if ( CacheRocket_Options::get( 'cloud_image_opt' ) && CacheRocket_Plan::can_use_image_optimization() ) {
			self::queue_job(
				'imageOpt',
				$url,
				array(
					'attachmentId' => $attachment_id,
					'metaKey'      => self::META_IMAGE,
				)
			);
		}

		if ( CacheRocket_Options::get( 'cloud_lqip' ) && CacheRocket_Plan::can_use_lqip() ) {
			self::queue_job(
				'lqip',
				$url,
				array(
					'attachmentId' => $attachment_id,
					'metaKey'      => self::META_LQIP,
				)
			);
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

		return $result;
	}

	/**
	 * Poll pending jobs and apply completed results.
	 */
	public static function poll_pending_jobs() {
		$pending = get_transient( self::TRANSIENT_JOBS );
		if ( ! is_array( $pending ) || empty( $pending ) ) {
			return;
		}

		$remaining = array();
		foreach ( $pending as $job_id => $info ) {
			$job = cacherocket_get_optimization_job( (string) $job_id );
			if ( is_wp_error( $job ) ) {
				$remaining[ $job_id ] = $info;
				continue;
			}

			$status = isset( $job['status'] ) ? (string) $job['status'] : '';
			if ( 'completed' === $status ) {
				self::apply_job_result( $job, isset( $info['context'] ) && is_array( $info['context'] ) ? $info['context'] : array() );
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

		$url = home_url( add_query_arg( array() ) );
		$key = 'cacherocket_ccss_queued_' . md5( $url );
		if ( get_transient( $key ) ) {
			return;
		}

		$map = get_option( self::OPTION_CCSS, array() );
		$hash = md5( $url );
		if ( is_array( $map ) && ! empty( $map[ $hash ]['cssUrl'] ) ) {
			return;
		}

		self::queue_job(
			'criticalCss',
			$url,
			array( 'pageUrl' => $url ),
			array(
				'viewportWidth'  => 1280,
				'viewportHeight' => 800,
			)
		);
		set_transient( $key, 1, DAY_IN_SECONDS );
	}

	/**
	 * Inject stored critical CSS link for the current URL.
	 */
	public static function inject_critical_css() {
		$url  = home_url( add_query_arg( array() ) );
		$map  = get_option( self::OPTION_CCSS, array() );
		$hash = md5( $url );
		if ( ! is_array( $map ) || empty( $map[ $hash ]['cssUrl'] ) ) {
			return;
		}
		$css_url = esc_url( (string) $map[ $hash ]['cssUrl'] );
		echo '<link rel="stylesheet" id="cacherocket-critical-css" href="' . $css_url . '" media="all" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			return $attr;
		}

		$formats = $meta['formats'];
		$prefer  = array();
		if ( CacheRocket_Options::get( 'cloud_avif' ) && ! empty( $formats['avif']['url'] ) ) {
			$prefer[] = (string) $formats['avif']['url'];
		}
		if ( CacheRocket_Options::get( 'cloud_webp' ) && ! empty( $formats['webp']['url'] ) ) {
			$prefer[] = (string) $formats['webp']['url'];
		}
		if ( empty( $prefer ) && ! empty( $formats['jpeg']['url'] ) ) {
			$prefer[] = (string) $formats['jpeg']['url'];
		}
		if ( empty( $prefer ) ) {
			return $attr;
		}

		$attr['src'] = $prefer[0];
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
					return $tag;
				}
				$formats = $meta['formats'];
				$url     = '';
				if ( CacheRocket_Options::get( 'cloud_avif' ) && ! empty( $formats['avif']['url'] ) ) {
					$url = (string) $formats['avif']['url'];
				} elseif ( CacheRocket_Options::get( 'cloud_webp' ) && ! empty( $formats['webp']['url'] ) ) {
					$url = (string) $formats['webp']['url'];
				} elseif ( ! empty( $formats['jpeg']['url'] ) ) {
					$url = (string) $formats['jpeg']['url'];
				}
				if ( '' === $url ) {
					return $tag;
				}
				$safe = esc_url( $url );
				return preg_replace( '/\ssrc=(["\'])(.*?)\1/i', ' src=$1' . $safe . '$1', $tag, 1 );
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
