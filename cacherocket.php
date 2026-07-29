<?php
/**
 * Plugin Name: CacheRocket
 * Plugin URI: https://www.cacherocket.com/wordpress
 * Description: Cache warming plus page caching, file optimization, LazyLoad, CDN, and database cleanup for WordPress — with remote warming via CacheRocket.com.
 * Version: 1.4.5
 * Author: NOOBBase
 * Author URI: https://www.cacherocket.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cacherocket
 * Domain Path: /languages
 * Requires at least: 5.5
 * Requires PHP: 7.4
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'CACHEROCKET_VERSION', '1.4.5' );
define( 'CACHEROCKET_PLUGIN_FILE', __FILE__ );
define( 'CACHEROCKET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CACHEROCKET_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load bundled translations (nl, de, fr, es, uk, ru, bel).
 *
 * Shipped for GitHub / SVN installs. WordPress.org language packs take precedence when present.
 */
function cacherocket_load_textdomain() {
	load_plugin_textdomain(
		'cacherocket',
		false,
		dirname( CACHEROCKET_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'init', 'cacherocket_load_textdomain' );

require_once CACHEROCKET_PLUGIN_DIR . 'includes/api.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-options.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-compatibility.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-plan.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-filesystem.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-cache.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-dropin.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-cache-engine.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-warm-on-publish.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-optimizer.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-lazyload.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-cdn.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-database.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-htaccess.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-preload.php';
require_once CACHEROCKET_PLUGIN_DIR . 'includes/class-cacherocket-warmers.php';
require_once CACHEROCKET_PLUGIN_DIR . 'admin/class-cacherocket-admin.php';

/**
 * Migrate legacy options and boot admin.
 */
function cacherocket_boot() {
	CacheRocket_Options::maybe_migrate();
	if ( is_admin() ) {
		CacheRocket_Admin::init();
	}
}
add_action( 'plugins_loaded', 'cacherocket_boot', 5 );

/**
 * Plugin activation.
 */
function cacherocket_activate() {
	add_option( 'cacherocket_api_key', '' );
	add_option( 'cacherocket_api_secret', '' );
	add_option( CacheRocket_Cache::OPTION_ENABLED, true );
	add_option( CacheRocket_Cache::OPTION_DELIVERY, CacheRocket_Cache::DELIVERY_STANDARD );
	add_option( CacheRocket_Cache::OPTION_WOOCOMMERCE, false );
	add_option( CacheRocket_Cache::OPTION_TTL, CacheRocket_Cache::DEFAULT_TTL );
	add_option( 'cacherocket_warm_on_publish', true );

	if ( false === get_option( CacheRocket_Options::OPTION_KEY, false ) ) {
		add_option( CacheRocket_Options::OPTION_KEY, CacheRocket_Options::defaults(), '', false );
	}

	CacheRocket_Cache::ensure_cache_dir();
}
register_activation_hook( __FILE__, 'cacherocket_activate' );

/**
 * Plugin deactivation: purge cache and remove drop-in / htaccess markers.
 */
function cacherocket_deactivate() {
	CacheRocket_Cache::purge_all();
	CacheRocket_Dropin::remove();
	CacheRocket_Htaccess::remove();
	CacheRocket_Plan::clear_cache();
}
register_deactivation_hook( __FILE__, 'cacherocket_deactivate' );

/**
 * Admin conflict notice on CacheRocket and plugins screens.
 */
function cacherocket_admin_conflict_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! CacheRocket_Compatibility::is_caching_disabled() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return;
	}

	$allowed = ( false !== strpos( $screen->id, 'cacherocket' ) ) || ( 'plugins' === $screen->id );
	if ( ! $allowed ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html( CacheRocket_Compatibility::get_conflict_message() )
	);
}
add_action( 'admin_notices', 'cacherocket_admin_conflict_notice' );

/**
 * Notice when early mode is on but WP_CACHE is not defined.
 */
function cacherocket_admin_wp_cache_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || false === strpos( $screen->id, 'cacherocket' ) ) {
		return;
	}

	if ( CacheRocket_Cache::DELIVERY_EARLY !== CacheRocket_Cache::get_delivery_mode() ) {
		return;
	}

	if ( CacheRocket_Dropin::is_wp_cache_enabled() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Early cache delivery requires WP_CACHE in wp-config.php. Add this line above “That’s all, stop editing!”:', 'cacherocket' );
	echo '</p><p><code>define( \'WP_CACHE\', true );</code></p></div>';
}
add_action( 'admin_notices', 'cacherocket_admin_wp_cache_notice' );

/**
 * Front-end bootstrap for page caching and optimizations.
 */
function cacherocket_bootstrap_frontend() {
	CacheRocket_Preload::init();

	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}

	CacheRocket_Cache_Engine::init();
	CacheRocket_Optimizer::init();
	CacheRocket_Lazyload::init();
	CacheRocket_CDN::init();
}
add_action( 'plugins_loaded', 'cacherocket_bootstrap_frontend', 20 );

/**
 * Warm URLs on publish / product update.
 */
function cacherocket_bootstrap_warm_on_publish() {
	CacheRocket_Warm_On_Publish::init();
}
add_action( 'plugins_loaded', 'cacherocket_bootstrap_warm_on_publish', 25 );

/**
 * Keep drop-in in sync on admin load when settings imply early mode.
 */
function cacherocket_admin_sync_dropin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	CacheRocket_Dropin::sync();
}
add_action( 'admin_init', 'cacherocket_admin_sync_dropin', 30 );
