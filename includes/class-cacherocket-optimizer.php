<?php
/**
 * Frontend file optimization (minify, defer, delay JS, fonts).
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
	 * Output buffer nesting level opened by this class, if any.
	 *
	 * @var int|null
	 */
	private static $ob_level = null;

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
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_delay_js_loader' ), 99 );
		}

		// Handle enqueued Google Fonts through the styles API; the output buffer below
		// only has to deal with tags hard-coded in a theme template.
		if ( CacheRocket_Options::get( 'self_host_fonts' ) ) {
			add_filter( 'style_loader_src', array( __CLASS__, 'filter_self_hosted_font_src' ), 5 );
		} elseif ( CacheRocket_Options::get( 'optimize_google_fonts' ) ) {
			add_filter( 'style_loader_src', array( __CLASS__, 'filter_font_display_swap_src' ), 5 );
			add_filter( 'wp_resource_hints', array( __CLASS__, 'filter_google_fonts_resource_hints' ), 10, 2 );
		}

		$needs_buffer = CacheRocket_Options::get( 'minify_css' )
			|| CacheRocket_Options::get( 'minify_js' )
			|| CacheRocket_Options::get( 'optimize_google_fonts' )
			|| CacheRocket_Options::get( 'self_host_fonts' );

		if ( $needs_buffer ) {
			add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 1 );
		}
	}

	/**
	 * Minified asset cache directory.
	 *
	 * @return string
	 */
	public static function min_dir() {
		$dir = trailingslashit( WP_CONTENT_DIR ) . 'cache/cacherocket/min';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		self::ensure_min_dir_public( $dir );
		return untrailingslashit( $dir );
	}

	/**
	 * Public URL for min directory.
	 *
	 * @return string
	 */
	public static function min_url() {
		return content_url( 'cache/cacherocket/min' );
	}

	/**
	 * Allow HTTP access to minified CSS/JS.
	 *
	 * The page-cache root writes Deny/Require-all-denied; without an override,
	 * browsers get 403 for /wp-content/cache/cacherocket/min/*.css|js.
	 *
	 * @param string $dir Absolute min directory.
	 */
	public static function ensure_min_dir_public( $dir ) {
		$dir = untrailingslashit( (string) $dir );
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$htaccess = $dir . '/.htaccess';
		$marker   = '# CacheRocket — public minify assets';
		$current  = is_readable( $htaccess ) ? (string) file_get_contents( $htaccess ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === strpos( $current, $marker ) ) {
			$rules = $marker . "\n"
				. "# Overrides parent cache/.htaccess deny so CSS/JS can load.\n"
				. "<IfModule mod_authz_core.c>\n"
				. "Require all granted\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "Order allow,deny\n"
				. "Allow from all\n"
				. "</IfModule>\n"
				. "<FilesMatch \"\\.php$\">\n"
				. "<IfModule mod_authz_core.c>\n"
				. "Require all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "Deny from all\n"
				. "</IfModule>\n"
				. "</FilesMatch>\n"
				. "Options -Indexes\n";
			CacheRocket_Filesystem::put_cache_file( $htaccess, $rules );
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			CacheRocket_Filesystem::put_cache_file( $index, "<?php\n// Silence is golden.\n" );
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

		$exclusions = CacheRocket_Options::delay_js_exclusion_list();
		foreach ( $exclusions as $needle ) {
			if ( '' !== $needle && ( false !== strpos( $handle, $needle ) || false !== strpos( (string) $src, $needle ) || false !== strpos( $tag, $needle ) ) ) {
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
	 * Enqueue delay-JS interaction loader.
	 */
	public static function enqueue_delay_js_loader() {
		$js = '(function(){var fired=false;function run(){if(fired)return;fired=true;var nodes=document.querySelectorAll(\'script[type="cacherocket/javascript"][data-cacherocket-delay]\');nodes.forEach(function(node){var s=document.createElement(\'script\');Array.prototype.forEach.call(node.attributes,function(a){if(a.name===\'type\'||a.name===\'data-cacherocket-delay\')return;s.setAttribute(a.name,a.value);});s.type=\'text/javascript\';if(node.src){s.src=node.src;}else{s.textContent=node.textContent;}node.parentNode.insertBefore(s,node);node.parentNode.removeChild(node);});}[\'keydown\',\'mousedown\',\'mousemove\',\'touchstart\',\'touchmove\',\'wheel\',\'scroll\'].forEach(function(evt){window.addEventListener(evt,run,{once:true,passive:true});});setTimeout(run,8000);})();';
		wp_register_script( 'cacherocket-delay-js', false, array(), CACHEROCKET_VERSION, true );
		wp_enqueue_script( 'cacherocket-delay-js' );
		wp_add_inline_script( 'cacherocket-delay-js', $js );
	}

	/**
	 * Start HTML buffer for minify / Google Fonts and pair with shutdown flush.
	 */
	public static function start_buffer() {
		ob_start( array( __CLASS__, 'process_html' ) );
		self::$ob_level = ob_get_level();
		add_action( 'shutdown', array( __CLASS__, 'end_buffer' ), 50 );
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
	 * Process buffered HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		if ( CacheRocket_Options::get( 'self_host_fonts' ) ) {
			$html = self::self_host_google_fonts( $html );
			$html = self::force_https_self_hosted_font_links( $html );
		} elseif ( CacheRocket_Options::get( 'optimize_google_fonts' ) ) {
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
			$html = preg_replace_callback(
				'/<link\b([^>]*rel=(["\'])stylesheet\2[^>]*)>/i',
				array( __CLASS__, 'minify_external_css_tag' ),
				$html
			);
		}

		if ( CacheRocket_Options::get( 'minify_js' ) ) {
			$html = preg_replace_callback(
				'/<script([^>]*)>(.*?)<\/script>/is',
				static function ( $m ) {
					$attrs = $m[1];
					$body  = $m[2];
					if ( false !== stripos( $attrs, 'src=' ) ) {
						return CacheRocket_Optimizer::minify_external_js_tag( $m[0], $attrs );
					}
					if ( '' === trim( $body ) ) {
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
	 * Minify a local stylesheet link tag.
	 *
	 * @param array<int, string> $m Match.
	 * @return string
	 */
	public static function minify_external_css_tag( $m ) {
		$attrs = $m[1];
		if ( ! preg_match( '/href=(["\'])([^"\']+)\1/i', $attrs, $href ) ) {
			return $m[0];
		}
		$min_url = self::minify_local_asset( $href[2], 'css' );
		if ( ! $min_url ) {
			return $m[0];
		}
		$attrs = preg_replace( '/href=(["\'])([^"\']+)\1/i', 'href=$1' . esc_url( $min_url ) . '$1', $attrs, 1 );
		return '<link ' . trim( $attrs ) . '>';
	}

	/**
	 * Minify a local external script tag.
	 *
	 * @param string $full  Full tag.
	 * @param string $attrs Attributes.
	 * @return string
	 */
	public static function minify_external_js_tag( $full, $attrs ) {
		if ( ! preg_match( '/src=(["\'])([^"\']+)\1/i', $attrs, $src ) ) {
			return $full;
		}
		$min_url = self::minify_local_asset( $src[2], 'js' );
		if ( ! $min_url ) {
			return $full;
		}
		return preg_replace( '/src=(["\'])([^"\']+)\1/i', 'src=$1' . esc_url( $min_url ) . '$1', $full, 1 );
	}

	/**
	 * Minify a same-origin CSS/JS file into the cache/min directory.
	 *
	 * @param string $url  Asset URL.
	 * @param string $type css|js.
	 * @return string|false Minified URL or false.
	 */
	public static function minify_local_asset( $url, $type ) {
		$url = strtok( $url, '#' );
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( preg_match( '/\.min\.(css|js)(\?|$)/i', $url ) ) {
			return false;
		}

		$home = home_url( '/' );
		$content = content_url( '/' );
		$path = '';

		if ( 0 === strpos( $url, $content ) ) {
			$path = WP_CONTENT_DIR . '/' . ltrim( substr( $url, strlen( $content ) ), '/' );
		} elseif ( 0 === strpos( $url, $home ) ) {
			$rel  = ltrim( substr( $url, strlen( $home ) ), '/' );
			$path = ABSPATH . $rel;
		} else {
			return false;
		}

		$path = strtok( $path, '?' );
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return false;
		}

		$min = ( 'css' === $type ) ? self::minify_css( $raw ) : self::minify_js( $raw );
		$hash = substr( md5( $path . '|' . filesize( $path ) . '|' . filemtime( $path ) ), 0, 12 );
		$file = $hash . '.' . $type;
		$dest = self::min_dir() . '/' . $file;

		if ( ! file_exists( $dest ) ) {
			CacheRocket_Filesystem::put_cache_file( $dest, $min );
		}

		return trailingslashit( self::min_url() ) . $file;
	}

	/**
	 * Add Google Fonts DNS prefetch and preconnect hints via the core resource hints API.
	 *
	 * @param array<int, mixed> $urls          Resource hint URLs.
	 * @param string            $relation_type Hint relation type.
	 * @return array<int, mixed>
	 */
	public static function filter_google_fonts_resource_hints( $urls, $relation_type ) {
		if ( ! is_array( $urls ) ) {
			return $urls;
		}

		if ( 'dns-prefetch' === $relation_type ) {
			$urls[] = '//fonts.googleapis.com';
			$urls[] = '//fonts.gstatic.com';
		}

		if ( 'preconnect' === $relation_type ) {
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}

		return $urls;
	}

	/**
	 * Add display=swap to an enqueued Google Fonts stylesheet URL.
	 *
	 * @param string $src Stylesheet URL.
	 * @return string
	 */
	public static function filter_font_display_swap_src( $src ) {
		if ( ! is_string( $src ) || false === stripos( $src, 'fonts.googleapis.com/css' ) ) {
			return $src;
		}
		if ( false !== strpos( $src, 'display=' ) ) {
			return $src;
		}
		return add_query_arg( 'display', 'swap', $src );
	}

	/**
	 * Point an enqueued Google Fonts stylesheet at its self-hosted copy.
	 *
	 * @param string $src Stylesheet URL.
	 * @return string
	 */
	public static function filter_self_hosted_font_src( $src ) {
		if ( ! is_string( $src ) || false === stripos( $src, 'fonts.googleapis.com/css' ) ) {
			return $src;
		}

		$local_url = self::localize_google_fonts_css( $src );

		return $local_url ? $local_url : $src;
	}

	/**
	 * Add display=swap to Google Fonts stylesheet tags in buffered HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function optimize_google_fonts( $html ) {
		if ( false === stripos( $html, 'fonts.googleapis.com' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/<link\b[^>]*fonts\.googleapis\.com\/css[^>]*>/i',
			static function ( $m ) {
				if ( false !== strpos( $m[0], 'display=' ) ) {
					return $m[0];
				}
				if ( ! preg_match( '/href=(["\'])([^"\']+)\1/i', $m[0], $href ) ) {
					return $m[0];
				}
				$url = add_query_arg( 'display', 'swap', html_entity_decode( $href[2], ENT_QUOTES ) );

				return CacheRocket_Optimizer::replace_tag_href( $m[0], $href, $url );
			},
			$html
		);
	}

	/**
	 * Swap the href value of a matched link tag, leaving the rest of the tag intact.
	 *
	 * @param string             $tag  Full link tag.
	 * @param array<int, string> $href Match of the href attribute (0 = attribute, 1 = quote char).
	 * @param string             $url  Replacement URL.
	 * @return string
	 */
	public static function replace_tag_href( $tag, $href, $url ) {
		$quote = $href[1];

		return str_replace( $href[0], 'href=' . $quote . esc_url( $url ) . $quote, $tag );
	}

	/**
	 * Download Google Fonts CSS + files locally and rewrite tags.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function self_host_google_fonts( $html ) {
		if ( false === stripos( $html, 'fonts.googleapis.com' ) ) {
			return $html;
		}

		$html = preg_replace_callback(
			'/<link\b[^>]*fonts\.googleapis\.com\/css[^>]*>/i',
			static function ( $m ) {
				if ( ! preg_match( '/href=(["\'])([^"\']+)\1/i', $m[0], $href ) ) {
					return $m[0];
				}

				$local_url = CacheRocket_Optimizer::localize_google_fonts_css( html_entity_decode( $href[2], ENT_QUOTES ) );
				if ( ! $local_url ) {
					return $m[0];
				}

				return CacheRocket_Optimizer::replace_tag_href( $m[0], $href, $local_url );
			},
			$html
		);

		// Drop hints for Google's font hosts, but leave any stylesheet we could not
		// localize alone so the page keeps its fonts.
		$html = preg_replace(
			'/<link\b(?=[^>]*rel=(["\'])(?:dns-prefetch|preconnect|preload)\1)[^>]*fonts\.(?:googleapis|gstatic)\.com[^>]*>\s*/i',
			'',
			$html
		);

		return $html;
	}

	/**
	 * Download a Google Fonts stylesheet and its font files into the uploads directory.
	 *
	 * @param string $css_url Remote Google Fonts CSS URL.
	 * @return string|false Local stylesheet URL, or false on failure.
	 */
	public static function localize_google_fonts_css( $css_url ) {
		if ( ! is_string( $css_url ) || false === stripos( $css_url, 'fonts.googleapis.com' ) ) {
			return false;
		}

		$upload = wp_upload_dir();
		if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return false;
		}

		$font_dir = trailingslashit( $upload['basedir'] ) . 'cacherocket-fonts';
		// wp_upload_dir() baseurl can be http:// even on HTTPS front ends (mixed content).
		$font_url = set_url_scheme( trailingslashit( $upload['baseurl'] ) . 'cacherocket-fonts' );
		if ( ! is_dir( $font_dir ) && ! wp_mkdir_p( $font_dir ) ) {
			return false;
		}

		if ( false === strpos( $css_url, 'display=' ) ) {
			$css_url = add_query_arg( 'display', 'swap', $css_url );
		}

		$key       = substr( md5( $css_url ), 0, 16 );
		$local_css = $font_dir . '/' . $key . '.css';
		$local_url = $font_url . '/' . $key . '.css';

		if ( file_exists( $local_css ) ) {
			self::normalize_self_hosted_font_css_scheme( $local_css, $font_url );
			return $local_url;
		}

		$response = wp_remote_get(
			$css_url,
			array(
				'timeout'    => 20,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$css = wp_remote_retrieve_body( $response );
		if ( ! is_string( $css ) || '' === $css ) {
			return false;
		}

		$css = preg_replace_callback(
			'/url\((["\']?)(https:\/\/fonts\.gstatic\.com\/[^)"\']+)\1\)/i',
			static function ( $um ) use ( $font_dir, $font_url ) {
				$remote = $um[2];
				$ext    = pathinfo( (string) wp_parse_url( $remote, PHP_URL_PATH ), PATHINFO_EXTENSION );
				$ext    = $ext ? $ext : 'woff2';
				$name   = substr( md5( $remote ), 0, 16 ) . '.' . $ext;
				$dest   = $font_dir . '/' . $name;

				if ( ! file_exists( $dest ) ) {
					$font_res = wp_remote_get( $remote, array( 'timeout' => 20 ) );
					if ( ! is_wp_error( $font_res ) ) {
						$body = wp_remote_retrieve_body( $font_res );
						if ( is_string( $body ) && '' !== $body ) {
							CacheRocket_Optimizer::write_font_file( $dest, $body );
						}
					}
				}

				if ( file_exists( $dest ) ) {
					return 'url(' . $font_url . '/' . $name . ')';
				}

				return $um[0];
			},
			$css
		);

		if ( ! self::write_font_file( $local_css, $css ) ) {
			return false;
		}

		return $local_url;
	}

	/**
	 * Write a self-hosted font asset into the uploads fonts directory.
	 *
	 * @param string $path     Absolute path inside the fonts directory.
	 * @param string $contents File contents.
	 * @return bool
	 */
	public static function write_font_file( $path, $contents ) {
		$upload = wp_upload_dir();
		if ( empty( $upload['basedir'] ) ) {
			return false;
		}

		$font_dir = trailingslashit( wp_normalize_path( trailingslashit( $upload['basedir'] ) . 'cacherocket-fonts' ) );
		if ( 0 !== strpos( wp_normalize_path( $path ), $font_dir ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- front-end path; confined to the plugin fonts directory.
		return false !== file_put_contents( $path, $contents, LOCK_EX );
	}

	/**
	 * Upgrade http://cacherocket-fonts links in HTML to the current scheme.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function force_https_self_hosted_font_links( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, 'cacherocket-fonts' ) ) {
			return $html;
		}

		$upload = wp_upload_dir();
		if ( empty( $upload['baseurl'] ) ) {
			return $html;
		}

		$font_url  = set_url_scheme( trailingslashit( $upload['baseurl'] ) . 'cacherocket-fonts' );
		$http_base = set_url_scheme( $font_url, 'http' );

		if ( $http_base !== $font_url ) {
			$html = str_replace( $http_base, $font_url, $html );
		}

		return $html;
	}

	/**
	 * Rewrite http:// self-hosted font URLs to the current scheme inside cached CSS.
	 *
	 * @param string $local_css Absolute path to local CSS file.
	 * @param string $font_url  Public fonts directory URL (correct scheme).
	 */
	public static function normalize_self_hosted_font_css_scheme( $local_css, $font_url ) {
		if ( ! is_string( $local_css ) || ! is_readable( $local_css ) || ! is_string( $font_url ) || '' === $font_url ) {
			return;
		}

		$css = file_get_contents( $local_css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $css ) || false === stripos( $css, 'http://' ) ) {
			return;
		}

		$http_base = set_url_scheme( $font_url, 'http' );
		$fixed     = str_replace( $http_base, $font_url, $css );

		if ( $fixed !== $css ) {
			self::write_font_file( $local_css, $fixed );
		}
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
