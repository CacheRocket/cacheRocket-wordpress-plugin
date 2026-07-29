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
$cacherocket_can_early      = CacheRocket_Plan::can_use_early_cache();
$cacherocket_can_woo        = CacheRocket_Plan::can_cache_plugin_pages();
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Cache', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'Page caching stores static HTML so visitors get ultra-fast responses. Fine-tune lifetime, exclusions, and eCommerce behavior.', 'cacherocket' ); ?></p>
	</div>
	<div class="cr-actions">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_clear_cache' ); ?>
			<button type="submit" name="cacherocket_clear_cache" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Clear cache', 'cacherocket' ); ?></button>
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
		__( 'Page caching', 'cacherocket' ),
		__( 'Generate static HTML for public pages under wp-content/cache/cacherocket/.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_enabled',
		__( 'Enable page caching', 'cacherocket' ),
		__( 'Cache public pages for anonymous visitors. Automatically disabled when another page-cache plugin is active.', 'cacherocket' ),
		array( 'disabled' => $cacherocket_cache_disabled )
	);
	CacheRocket_Admin::input(
		'cache_delivery',
		__( 'Delivery mode', 'cacherocket' ),
		__( 'Early mode serves cache before WordPress boots (requires WP_CACHE in wp-config.php).', 'cacherocket' ),
		array(
			'type'     => 'select',
			'disabled' => $cacherocket_cache_disabled,
			'options'  => array(
				CacheRocket_Cache::DELIVERY_STANDARD => __( 'Standard (PHP) — Free', 'cacherocket' ),
				CacheRocket_Cache::DELIVERY_EARLY    => __( 'Early (advanced-cache.php) — Paid', 'cacherocket' ),
			),
		)
	);
	if ( ! $cacherocket_can_early ) {
		echo '<div class="cr-notice cr-notice--info" style="margin:8px 12px 16px;">' . esc_html__( 'Early delivery requires a paid CacheRocket plan.', 'cacherocket' ) . ' <a href="https://www.cacherocket.com" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade', 'cacherocket' ) . '</a></div>';
	} elseif ( CacheRocket_Cache::DELIVERY_EARLY === CacheRocket_Cache::get_delivery_mode() && ! CacheRocket_Dropin::is_wp_cache_enabled() ) {
		echo '<div class="cr-notice cr-notice--warn" style="margin:8px 12px 16px;">' . esc_html__( 'Add define( \'WP_CACHE\', true ); to wp-config.php so the early drop-in can run.', 'cacherocket' ) . '</div>';
	}
	CacheRocket_Admin::input(
		'cache_ttl',
		__( 'Cache lifespan (seconds)', 'cacherocket' ),
		__( 'How long a cached page stays valid before being regenerated (300–604800).', 'cacherocket' ),
		array(
			'type' => 'number',
			'min'  => 300,
			'max'  => 604800,
		)
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Cache types', 'cacherocket' ),
		__( 'Control which kinds of visitors and requests get a cached page.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_mobile',
		__( 'Separate mobile cache', 'cacherocket' ),
		__( 'Store a distinct cache file for mobile user agents (useful with mobile-specific themes).', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_ssl',
		__( 'Cache SSL (HTTPS) pages', 'cacherocket' ),
		__( 'Recommended for HTTPS sites. Disable only if you intentionally serve mixed HTTP/HTTPS.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_query_strings',
		__( 'Cache URLs with query strings', 'cacherocket' ),
		__( 'By default only tracking params (utm_*, gclid, …) are ignored and other query strings bypass the cache. Enable to cache those variants too.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_logged_user',
		__( 'Cache for logged-in users', 'cacherocket' ),
		__( 'Not recommended for most sites. Personalized dashboards and admin bars will be wrong.', 'cacherocket' ),
		array( 'badge' => __( 'Advanced', 'cacherocket' ) )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'eCommerce', 'cacherocket' ),
		__( 'Safely cache catalog pages while never touching cart, checkout, or account.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_woocommerce',
		__( 'Cache WooCommerce shop & product pages', 'cacherocket' ),
		__( 'Caches shop, product, and taxonomy pages. Cart, checkout, and account are always excluded.', 'cacherocket' ),
		array(
			'disabled' => $cacherocket_cache_disabled || ! $cacherocket_can_woo,
			'badge'    => __( 'Paid', 'cacherocket' ),
			'checked'  => ! empty( $settings['cache_woocommerce'] ) && $cacherocket_can_woo,
		)
	);
	if ( ! $cacherocket_can_woo ) {
		echo '<div class="cr-notice cr-notice--info" style="margin:8px 12px 16px;">' . esc_html__( 'WooCommerce page caching requires a paid CacheRocket plan.', 'cacherocket' ) . '</div>';
	}
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Never cache', 'cacherocket' ),
		__( 'Exclude paths, cookies, and user agents from the page cache.', 'cacherocket' )
	);
	CacheRocket_Admin::textarea(
		'cache_reject_uri',
		__( 'Excluded URL paths', 'cacherocket' ),
		__( 'One path per line. Partial matches are excluded (e.g. /cart/).', 'cacherocket' ),
		"/cart/\n/checkout/\n/my-account/"
	);
	CacheRocket_Admin::textarea(
		'cache_reject_cookies',
		__( 'Excluded cookies', 'cacherocket' ),
		__( 'If any of these cookies are present, the page will not be cached.', 'cacherocket' ),
		'cookie_name'
	);
	CacheRocket_Admin::textarea(
		'cache_reject_ua',
		__( 'Excluded user agents', 'cacherocket' ),
		__( 'One user-agent substring per line.', 'cacherocket' ),
		'facebookexternalhit'
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Automatic purge', 'cacherocket' ),
		__( 'Keep the cache fresh when content changes.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_purge_pages',
		__( 'Clear cache when posts/pages update', 'cacherocket' ),
		__( 'Purges the CacheRocket page cache after content, menus, or comments change.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cache_purge_home',
		__( 'Also warm homepage after updates', 'cacherocket' ),
		__( 'When warm-on-publish is enabled, the homepage is included in the warm list.', 'cacherocket' )
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>
