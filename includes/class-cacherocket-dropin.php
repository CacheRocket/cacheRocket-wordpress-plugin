<?php
/**
 * Manage the CacheRocket advanced-cache.php drop-in (paid early delivery).
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Install / remove WP_CONTENT_DIR/advanced-cache.php for early serving.
 *
 * Does not modify wp-config.php. Site owners must set WP_CACHE themselves.
 */
class CacheRocket_Dropin {

	const DROPIN_MARKER = 'CacheRocket advanced-cache';

	/**
	 * Absolute path to the installed drop-in.
	 *
	 * @return string
	 */
	public static function get_dropin_path() {
		return trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
	}

	/**
	 * Absolute path to the plugin-bundled drop-in source.
	 *
	 * @return string
	 */
	public static function get_source_path() {
		return dirname( __DIR__ ) . '/includes/drop-in/advanced-cache.php';
	}

	/**
	 * Whether the installed drop-in belongs to CacheRocket.
	 *
	 * @return bool
	 */
	public static function is_ours() {
		$path = self::get_dropin_path();
		if ( ! file_exists( $path ) ) {
			return false;
		}

		$contents = CacheRocket_Filesystem::get_contents_admin( $path );
		if ( is_wp_error( $contents ) ) {
			return false;
		}

		return is_string( $contents ) && false !== strpos( $contents, self::DROPIN_MARKER );
	}

	/**
	 * Whether early delivery should be active right now.
	 *
	 * @return bool
	 */
	public static function should_be_installed() {
		if ( ! CacheRocket_Cache::is_enabled() ) {
			return false;
		}
		if ( CacheRocket_Compatibility::is_caching_disabled() ) {
			return false;
		}
		if ( ! CacheRocket_Plan::can_use_early_cache() ) {
			return false;
		}
		return CacheRocket_Cache::DELIVERY_EARLY === CacheRocket_Cache::get_delivery_mode();
	}

	/**
	 * Sync drop-in presence with current settings/plan.
	 *
	 * @return true|WP_Error
	 */
	public static function sync() {
		if ( self::should_be_installed() ) {
			return self::install();
		}
		return self::remove();
	}

	/**
	 * Install or refresh the drop-in file.
	 *
	 * @return true|WP_Error
	 */
	public static function install() {
		$source = self::get_source_path();
		$dest   = self::get_dropin_path();

		if ( ! file_exists( $source ) ) {
			return new WP_Error( 'missing_source', __( 'CacheRocket drop-in source file is missing.', 'cacherocket' ) );
		}

		if ( file_exists( $dest ) && ! self::is_ours() ) {
			return new WP_Error(
				'dropin_conflict',
				__( 'Another advanced-cache.php drop-in is already installed. Remove it before enabling CacheRocket early cache.', 'cacherocket' )
			);
		}

		$contents = CacheRocket_Filesystem::get_contents_admin( $source );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		$cache_dir = CacheRocket_Cache::get_cache_dir();
		$contents  = str_replace( '{{CACHEROCKET_CACHE_DIR}}', addcslashes( $cache_dir, '\\' ), $contents );
		$contents  = str_replace( '{{CACHEROCKET_TTL}}', (string) CacheRocket_Cache::get_ttl(), $contents );

		$result = CacheRocket_Filesystem::put_contents_admin( $dest, $contents );
		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'write_failed',
				__( 'Could not write advanced-cache.php. Check file permissions for wp-content.', 'cacherocket' )
			);
		}

		return true;
	}

	/**
	 * Remove our drop-in if present.
	 *
	 * @return true|WP_Error
	 */
	public static function remove() {
		$dest = self::get_dropin_path();
		if ( ! file_exists( $dest ) ) {
			return true;
		}
		if ( ! self::is_ours() ) {
			return true;
		}

		return CacheRocket_Filesystem::delete_admin( $dest );
	}

	/**
	 * Whether WP_CACHE is enabled for the drop-in to run.
	 *
	 * @return bool
	 */
	public static function is_wp_cache_enabled() {
		return defined( 'WP_CACHE' ) && WP_CACHE;
	}
}
