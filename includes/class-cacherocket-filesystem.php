<?php
/**
 * Filesystem helpers for CacheRocket.
 *
 * Admin operations use WP_Filesystem (direct method when available).
 * Front-end page-cache read/write uses direct PHP I/O confined to the
 * CacheRocket cache directory (hot path; credentials cannot be prompted).
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Filesystem utilities.
 */
class CacheRocket_Filesystem {

	/**
	 * Initialize WP_Filesystem for admin operations.
	 *
	 * @return WP_Filesystem_Base|false
	 */
	public static function wp() {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
			return $wp_filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Prefer direct FS for drop-in sync (no credentials UI). Fall back to default detection.
		add_filter( 'filesystem_method', array( __CLASS__, 'force_direct_method' ), 100 );
		$initialized = WP_Filesystem();
		remove_filter( 'filesystem_method', array( __CLASS__, 'force_direct_method' ), 100 );

		if ( ( ! $initialized || ! ( $wp_filesystem instanceof WP_Filesystem_Base ) ) ) {
			$initialized = WP_Filesystem();
		}

		if ( ! $initialized || ! ( $wp_filesystem instanceof WP_Filesystem_Base ) ) {
			return false;
		}

		return $wp_filesystem;
	}

	/**
	 * Force the direct filesystem method when possible.
	 *
	 * @return string
	 */
	public static function force_direct_method() {
		return 'direct';
	}

	/**
	 * Whether a path is inside the CacheRocket cache directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_in_cache_dir( $path ) {
		$cache_dir = wp_normalize_path( CacheRocket_Cache::get_cache_dir() );
		$path      = wp_normalize_path( $path );
		$cache_dir = trailingslashit( $cache_dir );

		return 0 === strpos( $path, $cache_dir );
	}

	/**
	 * Write a file via WP_Filesystem (admin / drop-in ops).
	 *
	 * @param string $path     Absolute path.
	 * @param string $contents File contents.
	 * @return true|WP_Error
	 */
	public static function put_contents_admin( $path, $contents ) {
		$fs = self::wp();
		if ( ! $fs ) {
			return new WP_Error(
				'fs_unavailable',
				__( 'WordPress filesystem is not available. Check file permissions.', 'cacherocket' )
			);
		}

		$dir = dirname( $path );
		if ( ! $fs->is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! $fs->put_contents( $path, $contents, FS_CHMOD_FILE ) ) {
			return new WP_Error(
				'write_failed',
				__( 'Could not write the file. Check file permissions.', 'cacherocket' )
			);
		}

		return true;
	}

	/**
	 * Read a file via WP_Filesystem.
	 *
	 * @param string $path Absolute path.
	 * @return string|WP_Error
	 */
	public static function get_contents_admin( $path ) {
		$fs = self::wp();
		if ( ! $fs ) {
			return new WP_Error(
				'fs_unavailable',
				__( 'WordPress filesystem is not available. Check file permissions.', 'cacherocket' )
			);
		}

		if ( ! $fs->exists( $path ) ) {
			return new WP_Error( 'missing_file', __( 'File not found.', 'cacherocket' ) );
		}

		$contents = $fs->get_contents( $path );
		if ( false === $contents ) {
			return new WP_Error( 'read_failed', __( 'Could not read the file.', 'cacherocket' ) );
		}

		return $contents;
	}

	/**
	 * Delete a file via WP_Filesystem or wp_delete_file().
	 *
	 * @param string $path Absolute path.
	 * @return true|WP_Error
	 */
	public static function delete_admin( $path ) {
		$fs = self::wp();
		if ( $fs && $fs->exists( $path ) ) {
			if ( ! $fs->delete( $path ) ) {
				return new WP_Error( 'delete_failed', __( 'Could not delete the file.', 'cacherocket' ) );
			}
			return true;
		}

		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
			if ( file_exists( $path ) ) {
				return new WP_Error( 'delete_failed', __( 'Could not delete the file.', 'cacherocket' ) );
			}
		}

		return true;
	}

	/**
	 * Write cache HTML confined to the cache directory (front-end hot path).
	 *
	 * @param string $path     Absolute path inside cache dir.
	 * @param string $contents HTML contents.
	 * @return bool
	 */
	public static function put_cache_file( $path, $contents ) {
		if ( ! self::is_in_cache_dir( $path ) ) {
			return false;
		}

		$dir = dirname( $path );
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$tmp = $path . '.' . uniqid( 'tmp', true );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- page-cache hot path; path confined to cache dir.
		$written = file_put_contents( $tmp, $contents, LOCK_EX );
		if ( false === $written ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace inside cache dir.
		if ( ! rename( $tmp, $path ) ) {
			wp_delete_file( $tmp );
			return false;
		}

		return true;
	}

	/**
	 * Read cache HTML confined to the cache directory.
	 *
	 * @param string $path Absolute path inside cache dir.
	 * @return string|false
	 */
	public static function get_cache_file( $path ) {
		if ( ! self::is_in_cache_dir( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- page-cache hot path; path confined to cache dir.
		$contents = file_get_contents( $path );
		return false === $contents ? false : $contents;
	}
}
