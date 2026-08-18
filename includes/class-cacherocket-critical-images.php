<?php
/**
 * Optimize Critical Images (LCP) via beacon + preload.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Detect LCP images and prioritize them on subsequent views.
 */
class CacheRocket_Critical_Images {

	const OPTION_MAP = 'cacherocket_lcp_map';
	const MAX_ENTRIES = 200;

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
		if ( ! CacheRocket_Options::get( 'critical_images' ) ) {
			return;
		}

		add_action( 'wp_ajax_nopriv_cacherocket_lcp', array( __CLASS__, 'ajax_store_lcp' ) );
		add_action( 'wp_ajax_cacherocket_lcp', array( __CLASS__, 'ajax_store_lcp' ) );

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_beacon' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 3 );
	}

	/**
	 * Start HTML buffer to inject preload / fetchpriority and pair with shutdown flush.
	 */
	public static function start_buffer() {
		if ( is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( __CLASS__, 'process_html' ) );
		self::$ob_level = ob_get_level();
		add_action( 'shutdown', array( __CLASS__, 'end_buffer' ), 30 );
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
	 * Current request path key.
	 *
	 * @return string
	 */
	public static function path_key() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return $path ? $path : '/';
	}

	/**
	 * Stored LCP image URL for a path.
	 *
	 * @param string|null $path Path.
	 * @return string
	 */
	public static function get_lcp_for_path( $path = null ) {
		$path = null === $path ? self::path_key() : $path;
		$map  = get_option( self::OPTION_MAP, array() );
		if ( ! is_array( $map ) || empty( $map[ $path ] ) ) {
			return '';
		}
		return (string) $map[ $path ];
	}

	/**
	 * Inject preload + fetchpriority for known LCP image.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		$lcp = self::get_lcp_for_path();
		if ( ! $lcp ) {
			// Heuristic: first contentful img without fetchpriority.
			if ( preg_match( '/<img\b[^>]*\bsrc=(["\'])([^"\']+)\1[^>]*>/i', $html, $m ) ) {
				$lcp = $m[2];
			}
		}

		if ( ! $lcp ) {
			return $html;
		}

		$preload = sprintf(
			'<link rel="preload" as="image" href="%s" fetchpriority="high" />',
			esc_url( $lcp )
		);
		$html = preg_replace( '/<head([^>]*)>/i', '<head$1>' . $preload, $html, 1 );

		$html = preg_replace_callback(
			'/<img\b([^>]*?)>/is',
			static function ( $m ) use ( $lcp ) {
				$attrs = $m[1];
				if ( ! preg_match( '/src=(["\'])([^"\']+)\1/i', $attrs, $src ) ) {
					return $m[0];
				}
				if ( $src[2] !== $lcp && false === strpos( $src[2], basename( wp_parse_url( $lcp, PHP_URL_PATH ) ?: '' ) ) ) {
					return $m[0];
				}
				$attrs = preg_replace( '/\sloading=(["\'])[^"\']*\1/i', '', $attrs );
				if ( false === stripos( $attrs, 'fetchpriority=' ) ) {
					$attrs .= ' fetchpriority="high"';
				}
				if ( false === stripos( $attrs, 'decoding=' ) ) {
					$attrs .= ' decoding="async"';
				}
				$attrs .= ' data-cacherocket-lcp="1"';
				return '<img' . $attrs . '>';
			},
			$html
		);

		return $html;
	}

	/**
	 * Enqueue LCP beacon that reports the LCP image URL once.
	 */
	public static function enqueue_beacon() {
		$url   = admin_url( 'admin-ajax.php' );
		$nonce = wp_create_nonce( 'cacherocket_lcp' );
		$js    = '(function(){if(!(\'PerformanceObserver\' in window))return;var sent=false;try{var po=new PerformanceObserver(function(list){var entries=list.getEntries();if(!entries.length||sent)return;var last=entries[entries.length-1];var el=last.element||null;var src=\'\';if(el){if(el.currentSrc)src=el.currentSrc;else if(el.src)src=el.src;else if(el.tagName===\'IMG\'&&el.getAttribute)src=el.getAttribute(\'src\')||\'\';}if(!src&&last.url)src=last.url;if(!src||sent)return;sent=true;po.disconnect();var body=new FormData();body.append(\'action\',\'cacherocket_lcp\');body.append(\'nonce\',' . wp_json_encode( $nonce ) . ');body.append(\'path\',location.pathname||\'/\');body.append(\'src\',src);navigator.sendBeacon?navigator.sendBeacon(' . wp_json_encode( $url ) . ',body):fetch(' . wp_json_encode( $url ) . ',{method:\'POST\',body:body,credentials:\'same-origin\',keepalive:true});});po.observe({type:\'largest-contentful-paint\',buffered:true});}catch(e){}})();';
		wp_register_script( 'cacherocket-lcp-beacon', false, array(), CACHEROCKET_VERSION, true );
		wp_enqueue_script( 'cacherocket-lcp-beacon' );
		wp_add_inline_script( 'cacherocket-lcp-beacon', $js );
	}

	/**
	 * Per-IP throttle for the public beacon: a handful of real LCP reports per
	 * path load is normal, a scripted flood replaying the endpoint isn't.
	 *
	 * @return bool True if this request is within the allowed rate.
	 */
	private static function check_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'cacherocket_lcp_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );

		if ( $n >= 20 ) {
			return false;
		}

		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Whether a candidate LCP image URL is allowed to be stored: same host as
	 * the site (covers the common case of images served from a CDN/subdomain
	 * that shares the site's registrable domain), so the beacon can't be used
	 * to inject an arbitrary third-party URL into every visitor's <head>.
	 *
	 * @param string $src Candidate image URL.
	 * @return bool
	 */
	private static function is_same_site_src( $src ) {
		$src_host = wp_parse_url( $src, PHP_URL_HOST );
		if ( ! is_string( $src_host ) || '' === $src_host ) {
			return false;
		}
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! is_string( $site_host ) || '' === $site_host ) {
			return false;
		}
		$src_host  = strtolower( $src_host );
		$site_host = strtolower( $site_host );

		if ( $src_host === $site_host ) {
			return true;
		}

		// Allow a shared registrable domain (e.g. cdn.example.com for example.com)
		// without allowing an unrelated attacker-controlled domain that merely
		// ends in the same string (evil-example.com must not match example.com).
		$site_parts = explode( '.', $site_host );
		$root       = implode( '.', array_slice( $site_parts, -2 ) );
		$suffix = '.' . $root;
		$len    = strlen( $suffix );
		return $src_host === $root || ( strlen( $src_host ) >= $len && substr( $src_host, -$len ) === $suffix );
	}

	/**
	 * AJAX: store LCP image for a path.
	 */
	public static function ajax_store_lcp() {
		check_ajax_referer( 'cacherocket_lcp', 'nonce' );

		if ( ! self::check_rate_limit() ) {
			wp_send_json_error( null, 429 );
		}

		$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$src  = isset( $_POST['src'] ) ? esc_url_raw( wp_unslash( $_POST['src'] ) ) : '';

		if ( '' === $path || '' === $src ) {
			wp_send_json_error( null, 400 );
		}

		if ( ! self::is_same_site_src( $src ) ) {
			wp_send_json_error( null, 400 );
		}

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		$map = get_option( self::OPTION_MAP, array() );
		if ( ! is_array( $map ) ) {
			$map = array();
		}
		$map[ $path ] = $src;

		if ( count( $map ) > self::MAX_ENTRIES ) {
			$map = array_slice( $map, -self::MAX_ENTRIES, null, true );
		}

		update_option( self::OPTION_MAP, $map, false );
		wp_send_json_success();
	}

	/**
	 * Clear stored LCP map.
	 */
	public static function clear() {
		delete_option( self::OPTION_MAP );
	}
}
