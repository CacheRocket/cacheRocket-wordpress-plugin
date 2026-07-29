<?php
/**
 * Lazy-load images and iframes.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Media lazy-loading for CacheRocket.
 */
class CacheRocket_Lazyload {

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$images  = (bool) CacheRocket_Options::get( 'lazyload' );
		$iframes = (bool) CacheRocket_Options::get( 'lazyload_iframes' );
		$youtube = (bool) CacheRocket_Options::get( 'lazyload_youtube' );
		$dims    = (bool) CacheRocket_Options::get( 'image_dimensions' );

		if ( ! $images && ! $iframes && ! $youtube && ! $dims ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 2 );
	}

	/**
	 * Start HTML buffer.
	 */
	public static function start_buffer() {
		if ( is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( __CLASS__, 'process_html' ) );
	}

	/**
	 * Transform images / iframes in HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		if ( CacheRocket_Options::get( 'lazyload' ) ) {
			$html = preg_replace_callback(
				'/<img\b([^>]*?)>/is',
				array( __CLASS__, 'lazy_img' ),
				$html
			);
		}

		if ( CacheRocket_Options::get( 'lazyload_iframes' ) || CacheRocket_Options::get( 'lazyload_youtube' ) ) {
			$html = preg_replace_callback(
				'/<iframe\b([^>]*?)>/is',
				array( __CLASS__, 'lazy_iframe' ),
				$html
			);
		}

		return $html;
	}

	/**
	 * Lazy-load a single img tag.
	 *
	 * @param array<int, string> $m Match.
	 * @return string
	 */
	public static function lazy_img( $m ) {
		$attrs = $m[1];

		if ( false !== stripos( $attrs, 'loading=' ) || false !== stripos( $attrs, 'data-no-lazy' ) || false !== stripos( $attrs, 'data-cacherocket-nolazy' ) ) {
			return $m[0];
		}

		if ( false !== stripos( $attrs, 'fetchpriority="high"' ) || false !== stripos( $attrs, "fetchpriority='high'" ) ) {
			return $m[0];
		}

		$attrs .= ' loading="lazy" decoding="async"';

		if ( CacheRocket_Options::get( 'image_dimensions' ) && false === stripos( $attrs, ' width=' ) && preg_match( '/src=(["\'])([^"\']+)\1/i', $attrs, $src ) ) {
			$size = self::local_image_size( $src[2] );
			if ( $size ) {
				$attrs .= ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"';
			}
		}

		return '<img' . $attrs . '>';
	}

	/**
	 * Lazy-load iframe / YouTube.
	 *
	 * @param array<int, string> $m Match.
	 * @return string
	 */
	public static function lazy_iframe( $m ) {
		$attrs = $m[1];

		if ( false !== stripos( $attrs, 'loading=' ) || false !== stripos( $attrs, 'data-no-lazy' ) ) {
			return $m[0];
		}

		$is_youtube = (bool) preg_match( '/youtube\.com|youtu\.be|youtube-nocookie\.com/i', $attrs );

		if ( $is_youtube && ! CacheRocket_Options::get( 'lazyload_youtube' ) && ! CacheRocket_Options::get( 'lazyload_iframes' ) ) {
			return $m[0];
		}

		if ( ! $is_youtube && ! CacheRocket_Options::get( 'lazyload_iframes' ) ) {
			return $m[0];
		}

		$attrs .= ' loading="lazy"';
		return '<iframe' . $attrs . '>';
	}

	/**
	 * Resolve width/height for a local attachment URL.
	 *
	 * @param string $url Image URL.
	 * @return array{0:int,1:int}|null
	 */
	private static function local_image_size( $url ) {
		$upload = wp_upload_dir();
		if ( empty( $upload['baseurl'] ) || empty( $upload['basedir'] ) ) {
			return null;
		}
		if ( 0 !== strpos( $url, $upload['baseurl'] ) ) {
			return null;
		}
		$path = str_replace( $upload['baseurl'], $upload['basedir'], $url );
		$path = strtok( $path, '?' );
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return null;
		}
		return array( (int) $size[0], (int) $size[1] );
	}
}
