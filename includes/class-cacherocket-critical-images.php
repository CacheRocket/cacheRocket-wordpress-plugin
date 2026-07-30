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

		add_action( 'wp_footer', array( __CLASS__, 'print_beacon' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 3 );
	}

	/**
	 * Start HTML buffer to inject preload / fetchpriority.
	 */
	public static function start_buffer() {
		if ( is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( __CLASS__, 'process_html' ) );
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
	 * Beacon that reports LCP image URL once.
	 */
	public static function print_beacon() {
		$url = admin_url( 'admin-ajax.php' );
		?>
		<script id="cacherocket-lcp-beacon">
		(function(){
			if(!('PerformanceObserver' in window))return;
			var sent=false;
			try{
				var po=new PerformanceObserver(function(list){
					var entries=list.getEntries();
					if(!entries.length||sent)return;
					var last=entries[entries.length-1];
					var el=last.element||null;
					var src='';
					if(el){
						if(el.currentSrc)src=el.currentSrc;
						else if(el.src)src=el.src;
						else if(el.tagName==='IMG'&&el.getAttribute)src=el.getAttribute('src')||'';
					}
					if(!src&&last.url)src=last.url;
					if(!src||sent)return;
					sent=true;
					po.disconnect();
					var body=new FormData();
					body.append('action','cacherocket_lcp');
					body.append('path',location.pathname||'/');
					body.append('src',src);
					navigator.sendBeacon?navigator.sendBeacon(<?php echo wp_json_encode( $url ); ?>,body):fetch(<?php echo wp_json_encode( $url ); ?>,{method:'POST',body:body,credentials:'same-origin',keepalive:true});
				});
				po.observe({type:'largest-contentful-paint',buffered:true});
			}catch(e){}
		})();
		</script>
		<?php
	}

	/**
	 * AJAX: store LCP image for a path.
	 */
	public static function ajax_store_lcp() {
		$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public beacon.
		$src  = isset( $_POST['src'] ) ? esc_url_raw( wp_unslash( $_POST['src'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $path || '' === $src ) {
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
