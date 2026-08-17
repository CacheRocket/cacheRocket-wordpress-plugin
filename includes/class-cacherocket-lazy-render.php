<?php
/**
 * Automatic Lazy Rendering (content-visibility).
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Delay rendering of below-the-fold DOM with content-visibility.
 */
class CacheRocket_Lazy_Render {

	/**
	 * Output buffer nesting level opened by this class, if any.
	 *
	 * @var int|null
	 */
	private static $ob_level = null;

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( ! CacheRocket_Options::get( 'lazy_rendering' ) ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 4 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 2 );
	}

	/**
	 * Enqueue lazy-render CSS.
	 */
	public static function enqueue_assets() {
		wp_register_style( 'cacherocket-lrc', false, array(), CACHEROCKET_VERSION );
		wp_enqueue_style( 'cacherocket-lrc' );
		wp_add_inline_style( 'cacherocket-lrc', '[data-cacherocket-lrc]{content-visibility:auto;contain-intrinsic-size:1px 1000px;}' );
	}

	/**
	 * Start buffer and pair with an explicit shutdown flush.
	 */
	public static function start_buffer() {
		if ( is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( __CLASS__, 'process_html' ) );
		self::$ob_level = ob_get_level();
		add_action( 'shutdown', array( __CLASS__, 'end_buffer' ), 20 );
	}

	/**
	 * Explicitly close the buffer opened in start_buffer().
	 */
	public static function end_buffer() {
		if ( null === self::$ob_level ) {
			return;
		}
		if ( ob_get_level() === self::$ob_level ) {
			ob_end_flush();
		}
		self::$ob_level = null;
	}

	/**
	 * Mark configured selectors with data-cacherocket-lrc.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		$selectors = CacheRocket_Options::lines( 'lazy_rendering_selectors' );
		if ( empty( $selectors ) ) {
			return $html;
		}

		foreach ( $selectors as $selector ) {
			$selector = trim( $selector );
			if ( '' === $selector ) {
				continue;
			}

			// ID.
			if ( 0 === strpos( $selector, '#' ) ) {
				$id = sanitize_html_class( substr( $selector, 1 ) );
				if ( $id ) {
					$html = preg_replace(
						'/(<(?:footer|div|aside|section|nav|main)\b[^>]*\bid=(["\'])' . preg_quote( $id, '/' ) . '\2[^>]*)(>)/i',
						'$1 data-cacherocket-lrc="1"$3',
						$html,
						1
					);
				}
				continue;
			}

			// Class.
			if ( 0 === strpos( $selector, '.' ) ) {
				$class = sanitize_html_class( substr( $selector, 1 ) );
				if ( $class ) {
					$html = preg_replace(
						'/(<(?:footer|div|aside|section|nav|main|ul|article)\b[^>]*\bclass=(["\'])[^"\']*\b' . preg_quote( $class, '/' ) . '\b[^"\']*\2[^>]*)(>)/i',
						'$1 data-cacherocket-lrc="1"$3',
						$html,
						5
					);
				}
				continue;
			}

			// Tag name.
			$tag = strtolower( preg_replace( '/[^a-z0-9\-]/', '', $selector ) );
			if ( $tag && in_array( $tag, array( 'footer', 'aside', 'section', 'nav' ), true ) ) {
				$html = preg_replace(
					'/(<' . $tag . '\b)([^>]*)(>)/i',
					'$1$2 data-cacherocket-lrc="1"$3',
					$html,
					3
				);
			}
		}

		return $html;
	}
}
