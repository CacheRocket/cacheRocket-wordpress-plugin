<?php
/**
 * CDN URL rewriting.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Rewrite static asset hosts to CDN CNAMEs.
 */
class CacheRocket_CDN {

	/**
	 * Register filters.
	 */
	public static function init() {
		if ( ! CacheRocket_Options::get( 'cdn' ) ) {
			return;
		}
		if ( ! CacheRocket_Plan::can_use_cdn() ) {
			return;
		}
		// Never rewrite asset URLs in wp-admin / admin-ajax.
		if ( is_admin() ) {
			return;
		}

		$cnames = self::get_cnames();
		if ( empty( $cnames ) ) {
			return;
		}

		add_filter( 'script_loader_src', array( __CLASS__, 'rewrite_url' ), 20 );
		add_filter( 'style_loader_src', array( __CLASS__, 'rewrite_url' ), 20 );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'rewrite_url' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'rewrite_content' ), 20 );
	}

	/**
	 * Configured CDN hostnames.
	 *
	 * @return string[]
	 */
	public static function get_cnames() {
		$lines = CacheRocket_Options::lines( 'cdn_cnames' );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = preg_replace( '#^https?://#i', '', $line );
			$line = untrailingslashit( trim( $line ) );
			if ( $line ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Pick a CNAME (round-robin by path hash).
	 *
	 * @param string $path Path for hashing.
	 * @return string
	 */
	private static function pick_cname( $path ) {
		$cnames = self::get_cnames();
		if ( empty( $cnames ) ) {
			return '';
		}
		$index = abs( crc32( $path ) ) % count( $cnames );
		return $cnames[ $index ];
	}

	/**
	 * Whether a URL should skip CDN rewrite.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function should_reject( $url ) {
		$rejects = CacheRocket_Options::lines( 'cdn_reject_files' );
		foreach ( $rejects as $needle ) {
			if ( '' !== $needle && false !== strpos( $url, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rewrite a single URL to CDN.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function rewrite_url( $url ) {
		if ( ! is_string( $url ) || '' === $url || self::should_reject( $url ) ) {
			return $url;
		}

		$site  = wp_parse_url( home_url( '/' ) );
		$parts = wp_parse_url( $url );
		if ( empty( $site['host'] ) || empty( $parts['host'] ) ) {
			return $url;
		}
		if ( strtolower( $site['host'] ) !== strtolower( $parts['host'] ) ) {
			return $url;
		}

		$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
		$cname = self::pick_cname( $path );
		if ( ! $cname ) {
			return $url;
		}

		$scheme = is_ssl() ? 'https' : ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' );
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		return $scheme . '://' . $cname . $path . $query;
	}

	/**
	 * Rewrite URLs inside post content HTML.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function rewrite_content( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$home = preg_quote( home_url( '/' ), '#' );
		return preg_replace_callback(
			'#(?<=["\'\(])' . $home . '([^"\'\)]+)#i',
			static function ( $m ) {
				return CacheRocket_CDN::rewrite_url( home_url( '/' ) . $m[1] );
			},
			$content
		);
	}
}
