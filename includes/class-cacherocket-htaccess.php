<?php
/**
 * Browser cache + GZIP rules for Apache .htaccess.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manage CacheRocket markers in the site root .htaccess.
 */
class CacheRocket_Htaccess {

	const MARKER = 'CacheRocket';

	/**
	 * Sync .htaccess rules from current settings.
	 *
	 * @return true|WP_Error
	 */
	public static function sync() {
		if ( ! function_exists( 'insert_with_markers' ) || ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$home_path = get_home_path();
		$htaccess  = $home_path . '.htaccess';

		if ( ! CacheRocket_Options::get( 'browser_cache' ) && ! CacheRocket_Options::get( 'gzip' ) ) {
			return self::remove();
		}

		if ( ! is_writable( $home_path ) && ! ( file_exists( $htaccess ) && is_writable( $htaccess ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			return new WP_Error( 'htaccess_unwritable', __( 'Could not write .htaccess. Check file permissions.', 'cacherocket' ) );
		}

		$rules = self::build_rules();
		$ok    = insert_with_markers( $htaccess, self::MARKER, $rules );
		if ( ! $ok ) {
			return new WP_Error( 'htaccess_write_failed', __( 'Failed to update .htaccess rules.', 'cacherocket' ) );
		}
		return true;
	}

	/**
	 * Remove CacheRocket markers from .htaccess.
	 *
	 * @return true|WP_Error
	 */
	public static function remove() {
		if ( ! function_exists( 'insert_with_markers' ) || ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$htaccess = get_home_path() . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			return true;
		}
		$ok = insert_with_markers( $htaccess, self::MARKER, array() );
		return $ok ? true : new WP_Error( 'htaccess_remove_failed', __( 'Failed to remove .htaccess rules.', 'cacherocket' ) );
	}

	/**
	 * Build Apache rules from settings.
	 *
	 * @return string[]
	 */
	private static function build_rules() {
		$lines = array();

		if ( CacheRocket_Options::get( 'gzip' ) ) {
			$lines[] = '<IfModule mod_deflate.c>';
			$lines[] = 'AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/x-javascript application/json application/xml image/svg+xml';
			$lines[] = '</IfModule>';
		}

		if ( CacheRocket_Options::get( 'browser_cache' ) ) {
			$lines[] = '<IfModule mod_expires.c>';
			$lines[] = 'ExpiresActive On';
			$lines[] = 'ExpiresByType text/css "access plus 1 year"';
			$lines[] = 'ExpiresByType text/javascript "access plus 1 year"';
			$lines[] = 'ExpiresByType application/javascript "access plus 1 year"';
			$lines[] = 'ExpiresByType application/x-javascript "access plus 1 year"';
			$lines[] = 'ExpiresByType image/jpeg "access plus 1 year"';
			$lines[] = 'ExpiresByType image/png "access plus 1 year"';
			$lines[] = 'ExpiresByType image/gif "access plus 1 year"';
			$lines[] = 'ExpiresByType image/webp "access plus 1 year"';
			$lines[] = 'ExpiresByType image/svg+xml "access plus 1 year"';
			$lines[] = 'ExpiresByType image/x-icon "access plus 1 year"';
			$lines[] = 'ExpiresByType font/woff "access plus 1 year"';
			$lines[] = 'ExpiresByType font/woff2 "access plus 1 year"';
			$lines[] = 'ExpiresByType application/font-woff "access plus 1 year"';
			$lines[] = 'ExpiresByType application/font-woff2 "access plus 1 year"';
			$lines[] = 'ExpiresByType video/mp4 "access plus 1 year"';
			$lines[] = '</IfModule>';
			$lines[] = '<IfModule mod_headers.c>';
			$lines[] = '<FilesMatch "\.(css|js|jpg|jpeg|png|gif|webp|svg|ico|woff|woff2|mp4)$">';
			$lines[] = 'Header set Cache-Control "public, max-age=31536000, immutable"';
			$lines[] = '</FilesMatch>';
			$lines[] = '</IfModule>';
		}

		return $lines;
	}
}
