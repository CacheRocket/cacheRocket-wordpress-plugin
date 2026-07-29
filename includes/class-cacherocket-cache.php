<?php
/**
 * Filesystem page cache for CacheRocket.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Core cache storage and eligibility.
 */
class CacheRocket_Cache {

	const OPTION_ENABLED     = 'cacherocket_cache_enabled';
	const OPTION_DELIVERY    = 'cacherocket_cache_delivery';
	const OPTION_WOOCOMMERCE = 'cacherocket_cache_woocommerce';
	const OPTION_TTL         = 'cacherocket_cache_ttl';
	const DEFAULT_TTL        = 86400;
	const DELIVERY_STANDARD  = 'standard';
	const DELIVERY_EARLY     = 'early';

	/**
	 * Absolute path to the CacheRocket cache root.
	 *
	 * @return string
	 */
	public static function get_cache_dir() {
		$dir = trailingslashit( WP_CONTENT_DIR ) . 'cache/cacherocket';

		/**
		 * Filter CacheRocket cache directory.
		 *
		 * @param string $dir Absolute path.
		 */
		return untrailingslashit( apply_filters( 'cacherocket_cache_dir', $dir ) );
	}

	/**
	 * Ensure cache directory exists with protection files.
	 *
	 * @return bool
	 */
	public static function ensure_cache_dir() {
		$dir = self::get_cache_dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "# CacheRocket — deny web access; pages are served by WordPress/drop-in only.\n"
				. "<IfModule mod_authz_core.c>\n"
				. "Require all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "Deny from all\n"
				. "</IfModule>\n";
			CacheRocket_Filesystem::put_cache_file( $htaccess, $rules );
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			CacheRocket_Filesystem::put_cache_file( $index, "<?php\n// Silence is golden.\n" );
		}

		return true;
	}

	/**
	 * Whether page caching is enabled in settings and allowed by compatibility.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( CacheRocket_Compatibility::is_caching_disabled() ) {
			return false;
		}

		if ( class_exists( 'CacheRocket_Options' ) ) {
			return (bool) CacheRocket_Options::get( 'cache_enabled', true );
		}

		return (bool) get_option( self::OPTION_ENABLED, true );
	}

	/**
	 * Current delivery mode (standard|early).
	 *
	 * @return string
	 */
	public static function get_delivery_mode() {
		$mode = class_exists( 'CacheRocket_Options' )
			? CacheRocket_Options::get( 'cache_delivery', self::DELIVERY_STANDARD )
			: get_option( self::OPTION_DELIVERY, self::DELIVERY_STANDARD );
		if ( self::DELIVERY_EARLY === $mode && ! CacheRocket_Plan::can_use_early_cache() ) {
			return self::DELIVERY_STANDARD;
		}
		if ( self::DELIVERY_EARLY !== $mode ) {
			return self::DELIVERY_STANDARD;
		}
		return self::DELIVERY_EARLY;
	}

	/**
	 * Whether WooCommerce page caching is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_caching_enabled() {
		if ( ! CacheRocket_Plan::can_cache_plugin_pages() ) {
			return false;
		}
		if ( class_exists( 'CacheRocket_Options' ) ) {
			return (bool) CacheRocket_Options::get( 'cache_woocommerce', false );
		}
		return (bool) get_option( self::OPTION_WOOCOMMERCE, false );
	}

	/**
	 * Cache TTL in seconds.
	 *
	 * @return int
	 */
	public static function get_ttl() {
		$ttl = class_exists( 'CacheRocket_Options' )
			? (int) CacheRocket_Options::get( 'cache_ttl', self::DEFAULT_TTL )
			: (int) get_option( self::OPTION_TTL, self::DEFAULT_TTL );
		return $ttl > 0 ? $ttl : self::DEFAULT_TTL;
	}

	/**
	 * Build a stable cache key for the current (or given) request URI.
	 *
	 * @param string|null $request_uri Optional request URI.
	 * @return string
	 */
	public static function get_cache_key( $request_uri = null ) {
		if ( null === $request_uri ) {
			$scheme = is_ssl() ? 'https' : 'http';
			$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalized below.
		} else {
			$parts  = wp_parse_url( home_url( $request_uri ) );
			$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : ( is_ssl() ? 'https' : 'http' );
			$host   = isset( $parts['host'] ) ? $parts['host'] : '';
			$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
			$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			$uri    = $path . $query;
		}

		$uri = self::normalize_uri( $uri );

		$suffix = '';
		if ( class_exists( 'CacheRocket_Options' ) && CacheRocket_Options::get( 'cache_mobile' ) && self::is_mobile_request() ) {
			$suffix = '|mobile';
		}

		return md5( $scheme . '://' . strtolower( $host ) . $uri . $suffix );
	}

	/**
	 * Whether the current request looks like a mobile UA.
	 *
	 * @return bool
	 */
	public static function is_mobile_request() {
		if ( function_exists( 'wp_is_mobile' ) ) {
			return wp_is_mobile();
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return (bool) preg_match( '/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/i', $ua );
	}

	/**
	 * Normalize URI for hashing (strip tracking params).
	 *
	 * @param string $uri Request URI.
	 * @return string
	 */
	public static function normalize_uri( $uri ) {
		$parts = wp_parse_url( $uri );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
		$query = array();

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			$ignore = array(
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_term',
				'utm_content',
				'fbclid',
				'gclid',
				'mc_cid',
				'mc_eid',
			);
			foreach ( $ignore as $key ) {
				unset( $query[ $key ] );
			}
			ksort( $query );
		}

		$normalized = $path;
		if ( ! empty( $query ) ) {
			$normalized .= '?' . http_build_query( $query );
		}

		return $normalized;
	}

	/**
	 * Absolute path to the HTML cache file for a key.
	 *
	 * @param string|null $cache_key Optional key.
	 * @return string
	 */
	public static function get_cache_file_path( $cache_key = null ) {
		if ( null === $cache_key ) {
			$cache_key = self::get_cache_key();
		}

		// Only allow hex cache keys.
		$cache_key = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $cache_key ) );
		if ( '' === $cache_key ) {
			$cache_key = 'invalid';
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_file_name( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'default';
		$host = $host ? $host : 'default';

		return self::get_cache_dir() . '/' . $host . '/' . $cache_key . '/index.html';
	}

	/**
	 * Whether the current request may be served/written to cache (request-level gates).
	 *
	 * @return bool
	 */
	public static function is_request_cacheable() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}

		$allow_logged_in = class_exists( 'CacheRocket_Options' ) && CacheRocket_Options::get( 'cache_logged_user' );
		if ( is_user_logged_in() && ! $allow_logged_in ) {
			return false;
		}

		if ( class_exists( 'CacheRocket_Options' ) && ! CacheRocket_Options::get( 'cache_ssl', true ) && is_ssl() ) {
			return false;
		}

		if ( ! self::query_string_is_cacheable() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache bypass flags.
		if ( ! empty( $_GET['preview'] ) || ! empty( $_GET['customize_changeset_uuid'] ) ) {
			return false;
		}

		if ( self::has_bypassing_cookies() ) {
			return false;
		}

		if ( self::is_excluded_uri() || self::is_excluded_user_agent() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether request query strings are allowed to be cached.
	 *
	 * Tracking params are ignored. Other query args require the "cache query strings" setting.
	 *
	 * @return bool
	 */
	public static function query_string_is_cacheable() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- cache eligibility only.
		$get = isset( $_GET ) && is_array( $_GET ) ? $_GET : array();
		if ( empty( $get ) ) {
			return true;
		}

		$ignore = array(
			'utm_source'   => true,
			'utm_medium'   => true,
			'utm_campaign' => true,
			'utm_term'     => true,
			'utm_content'  => true,
			'fbclid'       => true,
			'gclid'        => true,
			'mc_cid'       => true,
			'mc_eid'       => true,
		);

		$remaining = array();
		foreach ( $get as $key => $value ) {
			if ( ! isset( $ignore[ $key ] ) ) {
				$remaining[ $key ] = $value;
			}
		}

		if ( empty( $remaining ) ) {
			return true;
		}

		return class_exists( 'CacheRocket_Options' ) && (bool) CacheRocket_Options::get( 'cache_query_strings' );
	}

	/**
	 * Whether the request URI matches an exclusion rule.
	 *
	 * @return bool
	 */
	public static function is_excluded_uri() {
		if ( ! class_exists( 'CacheRocket_Options' ) ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( CacheRocket_Options::lines( 'cache_reject_uri' ) as $needle ) {
			if ( '' !== $needle && false !== strpos( $uri, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the user agent matches an exclusion rule.
	 *
	 * @return bool
	 */
	public static function is_excluded_user_agent() {
		if ( ! class_exists( 'CacheRocket_Options' ) ) {
			return false;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( CacheRocket_Options::lines( 'cache_reject_ua' ) as $needle ) {
			if ( '' !== $needle && false !== stripos( $ua, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Cookies that indicate personalized / cart state.
	 *
	 * @return bool
	 */
	public static function has_bypassing_cookies() {
		if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
			return false;
		}

		$custom = class_exists( 'CacheRocket_Options' ) ? CacheRocket_Options::lines( 'cache_reject_cookies' ) : array();

		foreach ( array_keys( $_COOKIE ) as $name ) {
			$name = (string) $name;
			if ( 0 === strpos( $name, 'wordpress_logged_in_' ) ) {
				$allow_logged_in = class_exists( 'CacheRocket_Options' ) && CacheRocket_Options::get( 'cache_logged_user' );
				if ( ! $allow_logged_in ) {
					return true;
				}
			}
			if ( 0 === strpos( $name, 'comment_author_' ) ) {
				return true;
			}
			if ( in_array( $name, array( 'woocommerce_items_in_cart', 'woocommerce_cart_hash' ), true ) ) {
				return true;
			}
			if ( 0 === strpos( $name, 'wp_woocommerce_session_' ) ) {
				return true;
			}
			foreach ( $custom as $needle ) {
				if ( '' !== $needle && false !== strpos( $name, $needle ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the current WP query is an allowed cacheable page type.
	 *
	 * @return bool
	 */
	public static function is_query_cacheable() {
		if ( is_404() || is_feed() || is_trackback() || is_robots() || is_favicon() ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return false;
		}

		if ( is_front_page() || is_home() ) {
			return true;
		}
		if ( is_singular( array( 'post', 'page' ) ) ) {
			return true;
		}
		if ( is_category() || is_tag() || is_author() || is_date() ) {
			return true;
		}

		if ( self::is_woocommerce_caching_enabled() ) {
			if ( function_exists( 'is_shop' ) && is_shop() ) {
				return true;
			}
			if ( function_exists( 'is_product' ) && is_product() ) {
				return true;
			}
			if ( function_exists( 'is_product_category' ) && is_product_category() ) {
				return true;
			}
			if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Combined eligibility for writing a new cache entry.
	 *
	 * @return bool
	 */
	public static function should_cache_response() {
		if ( ! self::is_request_cacheable() ) {
			return false;
		}
		if ( ! self::is_query_cacheable() ) {
			return false;
		}

		$status = http_response_code();
		if ( $status && ( 200 !== (int) $status ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Read cached HTML if present and fresh.
	 *
	 * @param string|null $cache_key Optional key.
	 * @return string|false
	 */
	public static function read( $cache_key = null ) {
		$file = self::get_cache_file_path( $cache_key );
		if ( ! CacheRocket_Filesystem::is_in_cache_dir( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		$mtime = filemtime( $file );
		if ( false === $mtime || ( time() - $mtime ) > self::get_ttl() ) {
			return false;
		}

		return CacheRocket_Filesystem::get_cache_file( $file );
	}

	/**
	 * Write HTML to the cache filesystem.
	 *
	 * @param string      $html      Page HTML.
	 * @param string|null $cache_key Optional key.
	 * @return bool
	 */
	public static function write( $html, $cache_key = null ) {
		if ( '' === $html || false === $html ) {
			return false;
		}

		if ( false === stripos( $html, '</html>' ) ) {
			return false;
		}

		if ( ! self::ensure_cache_dir() ) {
			return false;
		}

		$file = self::get_cache_file_path( $cache_key );
		if ( ! CacheRocket_Filesystem::is_in_cache_dir( $file ) ) {
			return false;
		}

		$marker = "\n<!-- CacheRocket cache generated at " . gmdate( 'c' ) . " -->\n";
		$html   = $html . $marker;

		return CacheRocket_Filesystem::put_cache_file( $file, $html );
	}

	/**
	 * Serve a cache hit and exit.
	 *
	 * @param string $html Cached HTML.
	 */
	public static function serve( $html ) {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
			header( 'X-CacheRocket-Cache: HIT' );
			header( 'Cache-Control: public, max-age=0, s-maxage=' . self::get_ttl() );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- full-page cache output (same pattern as other page-cache plugins).
		echo $html;
		exit;
	}

	/**
	 * Delete all cached files under the CacheRocket directory.
	 *
	 * @return bool
	 */
	public static function purge_all() {
		$dir = self::get_cache_dir();
		if ( ! is_dir( $dir ) ) {
			return true;
		}

		return self::delete_directory_contents( $dir, true );
	}

	/**
	 * Recursively delete directory contents.
	 *
	 * @param string $dir       Directory path.
	 * @param bool   $keep_root Keep the root directory and protection files.
	 * @return bool
	 */
	private static function delete_directory_contents( $dir, $keep_root = false ) {
		$dir             = untrailingslashit( $dir );
		$normalized_dir  = wp_normalize_path( $dir );
		$normalized_root = wp_normalize_path( self::get_cache_dir() );

		if ( ! is_dir( $dir ) ) {
			return true;
		}

		if ( $normalized_dir !== $normalized_root && 0 !== strpos( $normalized_dir, trailingslashit( $normalized_root ) ) ) {
			return false;
		}

		$items = scandir( $dir );
		if ( false === $items ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			if ( $keep_root && in_array( $item, array( '.htaccess', 'index.php' ), true ) ) {
				continue;
			}

			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::delete_directory_contents( $path, false );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- confined to cache directory.
				rmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		return true;
	}

	/**
	 * Approximate number of cached HTML files.
	 *
	 * @return int
	 */
	public static function count_entries() {
		$dir = self::get_cache_dir();
		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$count    = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'index.html' === $file->getFilename() ) {
				++$count;
			}
		}

		return $count;
	}
}
