<?php
/**
 * Dashboard page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$cacherocket_delivery = CacheRocket_Cache::get_delivery_mode();
$cacherocket_api_ok   = (bool) get_option( 'cacherocket_api_key' ) && (bool) get_option( 'cacherocket_api_secret' );
$cacherocket_enabled  = CacheRocket_Cache::is_enabled();
$cacherocket_is_paid  = ! empty( $plan['isPaid'] );
$cacherocket_is_wp_plan = CacheRocket_Plan::is_wordpress_plan();
$cacherocket_is_wp_starter = CacheRocket_Plan::is_wordpress_starter_plan();
$cacherocket_is_wp_grow = CacheRocket_Plan::is_wordpress_grow_plan();
$cacherocket_plugin_connected = ! empty( $plan['pluginConnected'] );
$cacherocket_ents     = isset( $plan['entitlements'] ) && is_array( $plan['entitlements'] ) ? $plan['entitlements'] : array();
$cacherocket_usage    = isset( $plan['usage'] ) && is_array( $plan['usage'] ) ? $plan['usage'] : array();
$cacherocket_cdn_remaining = null;
if ( isset( $cacherocket_usage['cdn']['bandwidthGbMonth']['remaining'] ) ) {
	$cacherocket_cdn_remaining = (float) $cacherocket_usage['cdn']['bandwidthGbMonth']['remaining'];
} elseif ( isset( $cacherocket_ents['maxCdnBandwidthGbMonth'] ) ) {
	$cacherocket_cdn_remaining = (float) $cacherocket_ents['maxCdnBandwidthGbMonth'];
}
$cacherocket_cdn_cap = isset( $cacherocket_ents['maxCdnBandwidthGbMonth'] ) ? (int) $cacherocket_ents['maxCdnBandwidthGbMonth'] : ( $cacherocket_is_wp_grow ? 80 : 10 );
$cacherocket_image_cap = isset( $cacherocket_ents['maxImageOptMonth'] ) ? (int) $cacherocket_ents['maxImageOptMonth'] : ( $cacherocket_is_wp_grow ? 400 : 40 );
$cacherocket_ccss_cap = isset( $cacherocket_ents['maxCriticalCssPagesMonth'] ) ? (int) $cacherocket_ents['maxCriticalCssPagesMonth'] : ( $cacherocket_is_wp_grow ? 12 : 0 );
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Dashboard', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'See how CacheRocket is accelerating your site — cache, warming, and front-end optimizations in one place.', 'cacherocket' ); ?></p>
	</div>
	<div class="cr-actions">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_clear_cache' ); ?>
			<button type="submit" name="cacherocket_clear_cache" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Clear cache', 'cacherocket' ); ?></button>
		</form>
		<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-cache' ) ); ?>"><?php esc_html_e( 'Configure cache', 'cacherocket' ); ?></a>
	</div>
</div>

<?php if ( ! empty( $conflicts ) ) : ?>
	<div class="cr-notice cr-notice--warn"><?php echo esc_html( CacheRocket_Compatibility::get_conflict_message() ); ?></div>
<?php endif; ?>

<?php if ( ! $cacherocket_api_ok ) : ?>
	<div class="cr-notice cr-notice--info">
		<?php esc_html_e( 'Connect your CacheRocket account to unlock remote cache warming and plan features.', 'cacherocket' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-account' ) ); ?>"><?php esc_html_e( 'Add API keys', 'cacherocket' ); ?></a>
	</div>
<?php endif; ?>

<?php if ( ! $cacherocket_is_paid ) : ?>
<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'WordPress plans for small sites', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'Plugin-only cloud tiers. Warmers stay managed in WordPress — no API or browser warmers.', 'cacherocket' ); ?></p>
	</header>
	<div class="cr-card__body" style="padding:12px 16px 20px;">
		<div class="cr-grid cr-grid--stats" style="margin-bottom:16px;">
			<div class="cr-stat">
				<div class="cr-stat__label"><?php esc_html_e( 'WordPress Starter', 'cacherocket' ); ?></div>
				<div class="cr-stat__value">€1</div>
				<div class="cr-stat__meta"><?php esc_html_e( 'CDN 10 GB · 40 WebP · 1 warmer · 8k crawls', 'cacherocket' ); ?></div>
			</div>
			<div class="cr-stat">
				<div class="cr-stat__label"><?php esc_html_e( 'WordPress Grow', 'cacherocket' ); ?></div>
				<div class="cr-stat__value">€5</div>
				<div class="cr-stat__meta"><?php esc_html_e( 'CDN 80 GB · 400 WebP · CCSS · LQIP · PSI · 2 warmers', 'cacherocket' ); ?></div>
			</div>
		</div>
		<p class="cr-field__desc" style="margin:0 0 12px;">
			<?php esc_html_e( 'Requires connected API keys. Starter is CDN + WebP + warming; Grow adds Critical CSS, LQIP, and PageSpeed.', 'cacherocket' ); ?>
		</p>
		<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( CacheRocket_Plan::wordpress_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Get Starter €1', 'cacherocket' ); ?>
		</a>
		<a class="cr-btn cr-btn--secondary" href="<?php echo esc_url( CacheRocket_Plan::wordpress_grow_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Get Grow €5', 'cacherocket' ); ?>
		</a>
		<a class="cr-btn cr-btn--secondary" href="<?php echo esc_url( CacheRocket_Plan::wordpress_pricing_url() ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Compare plans', 'cacherocket' ); ?>
		</a>
		<a class="cr-btn cr-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-account' ) ); ?>">
			<?php esc_html_e( 'Connect API keys', 'cacherocket' ); ?>
		</a>
	</div>
</section>
<?php elseif ( $cacherocket_is_wp_plan ) : ?>
<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2>
			<?php
			echo $cacherocket_is_wp_grow
				? esc_html__( 'WordPress Grow usage', 'cacherocket' )
				: esc_html__( 'WordPress Starter usage', 'cacherocket' );
			?>
		</h2>
		<p>
			<?php
			echo $cacherocket_plugin_connected
				? esc_html__( 'Plugin connected — cloud features are available within your monthly limits.', 'cacherocket' )
				: esc_html__( 'Waiting for plugin heartbeat. Keep API keys saved so this site stays connected.', 'cacherocket' );
			?>
		</p>
	</header>
	<div class="cr-grid cr-grid--stats" style="padding:8px 12px 16px;">
		<div class="cr-stat">
			<div class="cr-stat__label"><?php esc_html_e( 'CDN remaining', 'cacherocket' ); ?></div>
			<div class="cr-stat__value">
				<?php
				echo null !== $cacherocket_cdn_remaining
					? esc_html( number_format_i18n( $cacherocket_cdn_remaining, 1 ) . ' GB' )
					: '—';
				?>
			</div>
			<div class="cr-stat__meta">
				<?php
				printf(
					/* translators: %s: monthly CDN GB cap */
					esc_html__( 'Cap %s GB / month', 'cacherocket' ),
					esc_html( (string) $cacherocket_cdn_cap )
				);
				?>
			</div>
		</div>
		<div class="cr-stat">
			<div class="cr-stat__label"><?php esc_html_e( 'Image opt / month', 'cacherocket' ); ?></div>
			<div class="cr-stat__value"><?php echo esc_html( (string) $cacherocket_image_cap ); ?></div>
			<div class="cr-stat__meta"><?php esc_html_e( 'WebP included', 'cacherocket' ); ?></div>
		</div>
		<div class="cr-stat">
			<div class="cr-stat__label"><?php esc_html_e( 'Critical CSS / month', 'cacherocket' ); ?></div>
			<div class="cr-stat__value"><?php echo esc_html( (string) $cacherocket_ccss_cap ); ?></div>
			<div class="cr-stat__meta">
				<?php
				echo $cacherocket_is_wp_grow
					? esc_html__( 'LQIP + PageSpeed included', 'cacherocket' )
					: esc_html__( 'Upgrade to Grow for CCSS / LQIP / PSI', 'cacherocket' );
				?>
			</div>
		</div>
	</div>
	<?php if ( $cacherocket_is_wp_starter ) : ?>
		<div class="cr-card__body" style="padding:0 16px 16px;">
			<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( CacheRocket_Plan::wordpress_grow_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade to Grow €5', 'cacherocket' ); ?>
			</a>
		</div>
	<?php endif; ?>
</section>
<?php endif; ?>

<div class="cr-grid cr-grid--stats">
	<div class="cr-stat">
		<div class="cr-stat__label"><?php esc_html_e( 'Page cache', 'cacherocket' ); ?></div>
		<div class="cr-stat__value"><?php echo $cacherocket_enabled ? esc_html__( 'On', 'cacherocket' ) : esc_html__( 'Off', 'cacherocket' ); ?></div>
		<div class="cr-stat__meta">
			<?php
			echo CacheRocket_Cache::DELIVERY_EARLY === $cacherocket_delivery
				? esc_html__( 'Early delivery', 'cacherocket' )
				: esc_html__( 'Standard delivery', 'cacherocket' );
			?>
		</div>
	</div>
	<div class="cr-stat">
		<div class="cr-stat__label"><?php esc_html_e( 'Cached pages', 'cacherocket' ); ?></div>
		<div class="cr-stat__value"><?php echo esc_html( (string) $entries ); ?></div>
		<div class="cr-stat__meta"><code><?php echo esc_html( 'wp-content/cache/cacherocket/' ); ?></code></div>
	</div>
	<div class="cr-stat">
		<div class="cr-stat__label"><?php esc_html_e( 'Plan', 'cacherocket' ); ?></div>
		<div class="cr-stat__value"><?php echo esc_html( isset( $plan['planName'] ) ? $plan['planName'] : 'Free' ); ?></div>
		<div class="cr-stat__meta"><?php echo ! empty( $plan['isPaid'] ) ? esc_html__( 'Paid features unlocked', 'cacherocket' ) : esc_html__( 'Free tier', 'cacherocket' ); ?></div>
	</div>
	<div class="cr-stat">
		<div class="cr-stat__label"><?php esc_html_e( 'API', 'cacherocket' ); ?></div>
		<div class="cr-stat__value"><?php echo $cacherocket_api_ok ? esc_html__( 'Connected', 'cacherocket' ) : esc_html__( 'Missing', 'cacherocket' ); ?></div>
		<div class="cr-stat__meta"><?php esc_html_e( 'CacheRocket.com account', 'cacherocket' ); ?></div>
	</div>
</div>

<section class="cr-card">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Feature status', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'A quick look at the optimizations currently active on your site.', 'cacherocket' ); ?></p>
	</header>
	<div class="cr-feature-grid">
		<?php
		$cacherocket_features = array(
			array( __( 'Page caching', 'cacherocket' ), __( 'Serve static HTML for public pages.', 'cacherocket' ), ! empty( $settings['cache_enabled'] ) && $cacherocket_enabled ),
			array( __( 'Cache preloading', 'cacherocket' ), __( 'Warm URLs when you publish or update content.', 'cacherocket' ), ! empty( $settings['warm_on_publish'] ) && $cacherocket_api_ok ),
			array( __( 'File optimization', 'cacherocket' ), __( 'Minify, defer, delay JS, or self-host fonts.', 'cacherocket' ), ! empty( $settings['minify_css'] ) || ! empty( $settings['minify_js'] ) || ! empty( $settings['defer_js'] ) || ! empty( $settings['delay_js'] ) || ! empty( $settings['self_host_fonts'] ) ),
			array( __( 'LazyLoad media', 'cacherocket' ), __( 'Images, iframes, YouTube facade, CSS backgrounds.', 'cacherocket' ), ! empty( $settings['lazyload'] ) || ! empty( $settings['lazyload_iframes'] ) || ! empty( $settings['lazyload_youtube'] ) || ! empty( $settings['lazyload_css_bg'] ) ),
			array( __( 'Critical Images / LRC', 'cacherocket' ), __( 'Prioritize LCP images and lazy-render below-fold sections.', 'cacherocket' ), ! empty( $settings['critical_images'] ) || ! empty( $settings['lazy_rendering'] ) ),
			array( __( 'CDN', 'cacherocket' ), __( 'Custom CDN rewriting and/or CacheRocket CDN for cloud assets.', 'cacherocket' ), ! empty( $settings['cdn'] ) || ! empty( $settings['cloud_image_opt'] ) || ! empty( $settings['cloud_critical_css'] ) ),
			array( __( 'Browser cache / GZIP', 'cacherocket' ), __( 'Long-lived assets and compressed responses via .htaccess.', 'cacherocket' ), ! empty( $settings['browser_cache'] ) || ! empty( $settings['gzip'] ) ),
			array( __( 'eCommerce cache', 'cacherocket' ), __( 'Cache WooCommerce catalog pages safely.', 'cacherocket' ), ! empty( $settings['cache_woocommerce'] ) ),
			array( __( 'Link prefetch', 'cacherocket' ), __( 'Prefetch internal pages on hover for snappier navigation.', 'cacherocket' ), ! empty( $settings['preload_links'] ) ),
			array( __( 'Sitemap warm', 'cacherocket' ), __( 'Warm URLs discovered from your XML sitemap.', 'cacherocket' ), ! empty( $settings['preload_sitemap'] ) && $cacherocket_api_ok ),
		);
		foreach ( $cacherocket_features as $cacherocket_feature ) :
			?>
			<div class="cr-feature">
				<h3><?php echo esc_html( $cacherocket_feature[0] ); ?></h3>
				<p><?php echo esc_html( $cacherocket_feature[1] ); ?></p>
				<div class="cr-feature__status <?php echo $cacherocket_feature[2] ? 'is-on' : 'is-off'; ?>">
					<?php echo $cacherocket_feature[2] ? esc_html__( 'Enabled', 'cacherocket' ) : esc_html__( 'Disabled', 'cacherocket' ); ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'What CacheRocket optimizes', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'Inspired by the same performance pillars visitors expect from modern cache plugins — with CacheRocket warming on top.', 'cacherocket' ); ?></p>
	</header>
	<div class="cr-feature-grid">
		<div class="cr-feature">
			<h3><?php esc_html_e( 'Blazing-fast cached pages', 'cacherocket' ); ?></h3>
			<p><?php esc_html_e( 'Store public HTML locally and optionally serve it early via advanced-cache.php.', 'cacherocket' ); ?></p>
		</div>
		<div class="cr-feature">
			<h3><?php esc_html_e( 'Lighter files', 'cacherocket' ); ?></h3>
			<p><?php esc_html_e( 'Minify inline CSS/JS, defer scripts, and delay JavaScript until user interaction to improve LCP and INP.', 'cacherocket' ); ?></p>
		</div>
		<div class="cr-feature">
			<h3><?php esc_html_e( 'Media loaded when needed', 'cacherocket' ); ?></h3>
			<p><?php esc_html_e( 'Lazy-load images, iframes, and YouTube embeds so above-the-fold content paints first.', 'cacherocket' ); ?></p>
		</div>
		<div class="cr-feature">
			<h3><?php esc_html_e( 'Preload & remote warming', 'cacherocket' ); ?></h3>
			<p><?php esc_html_e( 'Warm cache after publish and prefetch links so visitors always hit a hot cache.', 'cacherocket' ); ?></p>
		</div>
	</div>
</section>
