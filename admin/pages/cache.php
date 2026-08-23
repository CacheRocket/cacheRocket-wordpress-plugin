<?php
/**
 * Cache settings page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$cacherocket_cache_disabled = CacheRocket_Compatibility::is_caching_disabled();
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Cache', 'cache-rocket' ); ?></h1>
		<p><?php esc_html_e( 'Page caching stores static HTML so visitors get ultra-fast responses. Fine-tune lifetime, exclusions, and eCommerce behavior.', 'cache-rocket' ); ?></p>
	</div>
	<div class="cr-actions">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_clear_cache' ); ?>
			<button type="submit" name="cacherocket_clear_cache" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Clear cache', 'cache-rocket' ); ?></button>
		</form>
	</div>
</div>

<?php if ( $cacherocket_cache_disabled ) : ?>
	<div class="cr-notice cr-notice--warn"><?php echo esc_html( CacheRocket_Compatibility::get_conflict_message() ); ?></div>
<?php endif; ?>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'Page caching', 'cache-rocket' ),
		__( 'Generate static HTML for public pages under wp-content/cache/cacherocket/.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_enabled',
		__( 'Enable page caching', 'cache-rocket' ),
		__( 'Cache public pages for anonymous visitors. Automatically disabled when another page-cache plugin is active.', 'cache-rocket' ),
		array( 'disabled' => $cacherocket_cache_disabled )
	);
	CacheRocket_Admin::input(
		'cache_delivery',
		__( 'Delivery mode', 'cache-rocket' ),
		__( 'Early mode serves cache before WordPress boots (requires WP_CACHE in wp-config.php).', 'cache-rocket' ),
		array(
			'type'     => 'select',
			'disabled' => $cacherocket_cache_disabled,
			'options'  => array(
				CacheRocket_Cache::DELIVERY_STANDARD => __( 'Standard (PHP)', 'cache-rocket' ),
				CacheRocket_Cache::DELIVERY_EARLY    => __( 'Early (advanced-cache.php)', 'cache-rocket' ),
			),
		)
	);
	if ( CacheRocket_Cache::DELIVERY_EARLY === CacheRocket_Cache::get_delivery_mode() && ! CacheRocket_Dropin::is_wp_cache_enabled() ) {
		echo '<div class="cr-notice cr-notice--warn" style="margin:8px 12px 16px;">' . esc_html__( 'Add define( \'WP_CACHE\', true ); to wp-config.php so the early drop-in can run.', 'cache-rocket' ) . '</div>';
	}
	CacheRocket_Admin::input(
		'cache_ttl',
		__( 'Cache lifespan (seconds)', 'cache-rocket' ),
		__( 'How long a cached page stays valid before being regenerated (300–604800).', 'cache-rocket' ),
		array(
			'type' => 'number',
			'min'  => 300,
			'max'  => 604800,
		)
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Cache types', 'cache-rocket' ),
		__( 'Control which kinds of visitors and requests get a cached page.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_mobile',
		__( 'Separate mobile cache', 'cache-rocket' ),
		__( 'Store a distinct cache file for mobile user agents (useful with mobile-specific themes).', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_ssl',
		__( 'Cache SSL (HTTPS) pages', 'cache-rocket' ),
		__( 'Recommended for HTTPS sites. Disable only if you intentionally serve mixed HTTP/HTTPS.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_query_strings',
		__( 'Cache URLs with query strings', 'cache-rocket' ),
		__( 'By default only tracking params (utm_*, gclid, …) are ignored and other query strings bypass the cache. Enable to cache those variants too.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_logged_user',
		__( 'Cache for logged-in users', 'cache-rocket' ),
		__( 'Not recommended for most sites. Personalized dashboards and admin bars will be wrong.', 'cache-rocket' ),
		array( 'badge' => __( 'Advanced', 'cache-rocket' ) )
	);
	CacheRocket_Admin::toggle(
		'cache_webp',
		__( 'Separate cache for WebP browsers', 'cache-rocket' ),
		__( 'Serve a distinct cache file when the visitor Accept header includes image/webp (works with WebP converter plugins).', 'cache-rocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'eCommerce', 'cache-rocket' ),
		__( 'Safely cache catalog pages while never touching cart, checkout, or account.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_woocommerce',
		__( 'Cache WooCommerce shop & product pages', 'cache-rocket' ),
		__( 'Caches shop, product, and taxonomy pages. Cart, checkout, and account are always excluded.', 'cache-rocket' ),
		array(
			'disabled' => $cacherocket_cache_disabled,
		)
	);
	CacheRocket_Admin::toggle(
		'cache_wc_empty_cart',
		__( 'Cache empty cart fragments', 'cache-rocket' ),
		__( 'Speeds up WooCommerce get_refreshed_fragments AJAX when the cart is empty.', 'cache-rocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Never cache', 'cache-rocket' ),
		__( 'Exclude paths, cookies, and user agents from the page cache. You can also tick “Do not cache” in the editor for a single post, page, or other content type.', 'cache-rocket' )
	);
	CacheRocket_Admin::textarea(
		'cache_reject_uri',
		__( 'Excluded URL paths', 'cache-rocket' ),
		__( 'One path per line. Partial matches are excluded (e.g. /cart/).', 'cache-rocket' ),
		"/cart/\n/checkout/\n/my-account/"
	);
	CacheRocket_Admin::textarea(
		'cache_reject_cookies',
		__( 'Excluded cookies', 'cache-rocket' ),
		__( 'If any of these cookies are present, the page will not be cached.', 'cache-rocket' ),
		'cookie_name'
	);
	CacheRocket_Admin::textarea(
		'cache_reject_ua',
		__( 'Excluded user agents', 'cache-rocket' ),
		__( 'One user-agent substring per line.', 'cache-rocket' ),
		'facebookexternalhit'
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Automatic purge', 'cache-rocket' ),
		__( 'Keep the cache fresh when content changes.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_purge_pages',
		__( 'Clear cache when posts/pages update', 'cache-rocket' ),
		__( 'Purges the CacheRocket page cache after content, menus, or comments change.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_purge_home',
		__( 'Also warm homepage after updates', 'cache-rocket' ),
		__( 'When warm-on-publish is enabled, the homepage is included in the warm list.', 'cache-rocket' )
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cache-rocket' ); ?></button>
	</div>
</form>
