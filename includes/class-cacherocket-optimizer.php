<?php
/**
 * Frontend file optimization (minify, defer, delay JS).
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Lightweight HTML/asset optimizations for visitors.
 */
class CacheRocket_Optimizer {

	/**
	 * Register front-end filters.
	 */
	public static function init() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( CacheRocket_Options::get( 'remove_query_strings' ) ) {
			add_filter( 'script_loader_src', array( __CLASS__, 'strip_version_query' ), 15 );
			add_filter( 'style_loader_src', array( __CLASS__, 'strip_version_query' ), 15 );
		}

		if ( CacheRocket_Options::get( 'defer_js' ) || CacheRocket_Options::get( 'delay_js' ) ) {
			add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_tag' ), 20, 3 );
		}

		if ( CacheRocket_Options::get( 'delay_js' ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'print_delay_js_loader' ), 99 );
		}

		if ( CacheRocket_Options::get( 'minify_css' ) || CacheRocket_Options::get( 'minify_js' ) || CacheRocket_Options::get( 'optimize_google_fonts' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 1 );
		}
	}

	/**
	 * Remove ?ver= from asset URLs.
	 *
	 * @param string $src Asset URL.
	 * @return string
	 */
	public static function strip_version_query( $src ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}
		return remove_query_arg( 'ver', $src );
	}

	/**
	 * Add defer / data-cacherocket-delay attributes to script tags.
	 *
	 * @param string $tag    Full script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script src.
	 * @return string
	 */
	public static function filter_script_tag( $tag, $handle, $src ) {
		if ( ! is_string( $tag ) || false !== strpos( $tag, ' data-cacherocket-' ) ) {
			return $tag;
		}

		$skip = array( 'jquery', 'jquery-core', 'jquery-migrate', 'admin-bar' );
		if ( in_array( $handle, $skip, true ) ) {
			return $tag;
		}

		$exclusions = CacheRocket_Options::lines( 'delay_js_exclusions' );
		foreach ( $exclusions as $needle ) {
			if ( '' !== $needle && ( false !== strpos( $handle, $needle ) || false !== strpos( $src, $needle ) || false !== strpos( $tag, $needle ) ) ) {
				return $tag;
			}
		}

		if ( CacheRocket_Options::get( 'delay_js' ) ) {
			$tag = preg_replace( '/\s+type=(["\'])[^"\']*\1/i', '', $tag );
			$tag = str_replace( '<script ', '<script type="cacherocket/javascript" data-cacherocket-delay="1" ', $tag );
			return $tag;
		}

		if ( CacheRocket_Options::get( 'defer_js' ) && false === strpos( $tag, ' defer' ) && false === strpos( $tag, ' async' ) ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}

		return $tag;
	}

	/**
	 * Tiny loader that restores delayed scripts on first interaction.
	 */
	public static function print_delay_js_loader() {
		?>
		<script id="cacherocket-delay-js">
		(function(){
			var fired=false;
			function run(){
				if(fired)return;fired=true;
				var nodes=document.querySelectorAll('script[type="cacherocket/javascript"][data-cacherocket-delay]');
				nodes.forEach(function(node){
					var s=document.createElement('script');
					Array.prototype.forEach.call(node.attributes,function(a){
						if(a.name==='type'||a.name==='data-cacherocket-delay')return;
						s.setAttribute(a.name,a.value);
					});
					s.type='text/javascript';
					if(node.src){s.src=node.src;}else{s.textContent=node.textContent;}
					node.parentNode.insertBefore(s,node);
					node.parentNode.removeChild(node);
				});
			}
			['keydown','mousedown','mousemove','touchstart','touchmove','wheel','scroll'].forEach(function(evt){
				window.addEventListener(evt,run,{once:true,passive:true});
			});
			setTimeout(run,8000);
		})();
		</script>
		<?php
	}

	/**
	 * Start HTML buffer for minify / Google Fonts.
	 */
	public static function start_buffer() {
		if ( ! CacheRocket_Cache::is_request_cacheable() ) {
			return;
		}
		ob_start( array( __CLASS__, 'process_html' ) );
	}

	/**
	 * Process buffered HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		if ( CacheRocket_Options::get( 'optimize_google_fonts' ) ) {
			$html = self::optimize_google_fonts( $html );
		}

		if ( CacheRocket_Options::get( 'minify_css' ) ) {
			$html = preg_replace_callback(
				'/<style([^>]*)>(.*?)<\/style>/is',
				static function ( $m ) {
					return '<style' . $m[1] . '>' . CacheRocket_Optimizer::minify_css( $m[2] ) . '</style>';
				},
				$html
			);
		}

		if ( CacheRocket_Options::get( 'minify_js' ) ) {
			$html = preg_replace_callback(
				'/<script([^>]*)>(.*?)<\/script>/is',
				static function ( $m ) {
					$attrs = $m[1];
					$body  = $m[2];
					if ( '' === trim( $body ) || false !== stripos( $attrs, 'src=' ) ) {
						return $m[0];
					}
					if ( false !== stripos( $attrs, 'cacherocket/javascript' ) || false !== stripos( $attrs, 'application/ld+json' ) ) {
						return $m[0];
					}
					return '<script' . $attrs . '>' . CacheRocket_Optimizer::minify_js( $body ) . '</script>';
				},
				$html
			);
		}

		return $html;
	}

	/**
	 * Prefetch Google Fonts DNS and display=swap hint.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function optimize_google_fonts( $html ) {
		if ( false === stripos( $html, 'fonts.googleapis.com' ) ) {
			return $html;
		}
		$hints = "<link rel=\"dns-prefetch\" href=\"//fonts.googleapis.com\" />\n<link rel=\"dns-prefetch\" href=\"//fonts.gstatic.com\" />\n<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin />\n";
		$html  = preg_replace( '/<head([^>]*)>/i', '<head$1>' . $hints, $html, 1 );
		$html  = preg_replace_callback(
			'/<link([^>]*fonts\.googleapis\.com[^>]*)>/i',
			static function ( $m ) {
				$tag = $m[0];
				if ( false === strpos( $tag, 'display=' ) ) {
					$tag = preg_replace( '/href=(["\'])([^"\']+)(["\'])/', 'href=$1$2&display=swap$3', $tag, 1 );
				}
				return $tag;
			},
			$html
		);
		return $html;
	}

	/**
	 * Minimal CSS minify.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function minify_css( $css ) {
		$css = preg_replace( '!/\*.*?\*/!s', '', $css );
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = str_replace( array( ' {', '{ ', ' }', '} ', ': ', ' :', '; ', ' ;', ', ' ), array( '{', '{', '}', '}', ':', ':', ';', ';', ',' ), $css );
		return trim( $css );
	}

	/**
	 * Minimal JS minify (safe whitespace only).
	 *
	 * @param string $js JS.
	 * @return string
	 */
	public static function minify_js( $js ) {
		$js = preg_replace( '#/\*.*?\*/#s', '', $js );
		$js = preg_replace( '#^\s*//.*$#m', '', $js );
		$js = preg_replace( '/\s+/', ' ', $js );
		return trim( $js );
	}
}
