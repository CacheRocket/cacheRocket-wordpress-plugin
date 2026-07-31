<?php
/**
 * CacheRocket advanced-cache.php
 *
 * Early page-cache delivery (paid). Copied to WP_CONTENT_DIR when early mode is enabled.
 * CacheRocket advanced-cache
 *
 * Runs before most of WordPress loads — keep this file free of WP APIs.
 *
 * @package CacheRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

if ( ! function_exists( 'cacherocket_serve_advanced_cache' ) ) {
	/**
	 * Serve a cached page early when possible.
	 *
	 * Wrapped in a function so locals are not treated as plugin globals.
	 *
	 * @return void
	 */
	function cacherocket_serve_advanced_cache() {
		if ( ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- early drop-in bypass only.
			return;
		}

		// WP sanitization helpers are not loaded yet; stripslashes mirrors wp_unslash().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$cacherocket_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', stripslashes( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $cacherocket_method && 'HEAD' !== $cacherocket_method ) {
			return;
		}

		// Never serve cached HTML for WordPress system / admin endpoints.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$cacherocket_uri_check = isset( $_SERVER['REQUEST_URI'] ) ? stripslashes( (string) $_SERVER['REQUEST_URI'] ) : '';
		if ( $cacherocket_uri_check && preg_match( '#/(?:wp-admin|wp-login\.php|wp-cron\.php|xmlrpc\.php|wp-json)(/|\?|$)#i', $cacherocket_uri_check ) ) {
			return;
		}

		if ( ! empty( $_COOKIE ) && is_array( $_COOKIE ) ) {
			foreach ( array_keys( $_COOKIE ) as $cacherocket_cookie_name ) {
				$cacherocket_cookie_name = (string) $cacherocket_cookie_name;
				if ( 0 === strpos( $cacherocket_cookie_name, 'wordpress_logged_in_' ) ) {
					return;
				}
				if ( 0 === strpos( $cacherocket_cookie_name, 'comment_author_' ) ) {
					return;
				}
				if ( 0 === strpos( $cacherocket_cookie_name, 'wp_woocommerce_session_' ) ) {
					return;
				}
				if ( in_array( $cacherocket_cookie_name, array( 'woocommerce_items_in_cart', 'woocommerce_cart_hash' ), true ) ) {
					return;
				}
			}
		}

		if ( ! empty( $_GET['preview'] ) || ! empty( $_GET['customize_changeset_uuid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$cacherocket_cache_dir = '{{CACHEROCKET_CACHE_DIR}}';
		$cacherocket_ttl       = (int) '{{CACHEROCKET_TTL}}';
		if ( $cacherocket_ttl <= 0 ) {
			$cacherocket_ttl = 86400;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$cacherocket_host = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/[^a-zA-Z0-9.\-]/', '', stripslashes( (string) $_SERVER['HTTP_HOST'] ) ) : 'default';
		if ( '' === $cacherocket_host ) {
			$cacherocket_host = 'default';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$cacherocket_uri   = isset( $_SERVER['REQUEST_URI'] ) ? preg_replace( '/[\x00-\x1F\x7F]/', '', stripslashes( (string) $_SERVER['REQUEST_URI'] ) ) : '/';
		$cacherocket_parts = parse_url( $cacherocket_uri ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WP not fully loaded.
		$cacherocket_path  = isset( $cacherocket_parts['path'] ) ? $cacherocket_parts['path'] : '/';
		$cacherocket_query = array();
		if ( ! empty( $cacherocket_parts['query'] ) ) {
			parse_str( $cacherocket_parts['query'], $cacherocket_query );
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'mc_cid', 'mc_eid' ) as $cacherocket_ignore ) {
				unset( $cacherocket_query[ $cacherocket_ignore ] );
			}
			ksort( $cacherocket_query );
		}
		$cacherocket_normalized = $cacherocket_path;
		if ( ! empty( $cacherocket_query ) ) {
			$cacherocket_normalized .= '?' . http_build_query( $cacherocket_query );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$cacherocket_scheme    = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== stripslashes( (string) $_SERVER['HTTPS'] ) ) ? 'https' : 'http';
		$cacherocket_cache_key = md5( $cacherocket_scheme . '://' . strtolower( $cacherocket_host ) . $cacherocket_normalized );
		$cacherocket_file      = rtrim( $cacherocket_cache_dir, '/\\' ) . '/' . $cacherocket_host . '/' . $cacherocket_cache_key . '/index.html';

		// Prevent path traversal outside the configured cache directory.
		$cacherocket_real_cache = realpath( $cacherocket_cache_dir );
		$cacherocket_real_file  = realpath( $cacherocket_file );
		if ( false === $cacherocket_real_cache || false === $cacherocket_real_file || 0 !== strpos( $cacherocket_real_file, $cacherocket_real_cache ) ) {
			return;
		}

		if ( ! is_readable( $cacherocket_real_file ) ) {
			return;
		}

		$cacherocket_mtime = filemtime( $cacherocket_real_file );
		if ( false === $cacherocket_mtime || ( time() - $cacherocket_mtime ) > $cacherocket_ttl ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- early drop-in; WP APIs unavailable.
		$cacherocket_html = file_get_contents( $cacherocket_real_file );
		if ( false === $cacherocket_html || '' === $cacherocket_html ) {
			return;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-CacheRocket-Cache: HIT-EARLY' );
			header( 'Cache-Control: public, max-age=0, s-maxage=' . $cacherocket_ttl );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- full-page cache output.
		echo $cacherocket_html;
		exit;
	}
}

cacherocket_serve_advanced_cache();
