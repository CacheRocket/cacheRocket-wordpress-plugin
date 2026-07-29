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
			array( __( 'File optimization', 'cacherocket' ), __( 'Minify, defer, or delay CSS/JS delivery.', 'cacherocket' ), ! empty( $settings['minify_css'] ) || ! empty( $settings['minify_js'] ) || ! empty( $settings['defer_js'] ) || ! empty( $settings['delay_js'] ) ),
			array( __( 'LazyLoad media', 'cacherocket' ), __( 'Load images and iframes only when needed.', 'cacherocket' ), ! empty( $settings['lazyload'] ) || ! empty( $settings['lazyload_iframes'] ) ),
			array( __( 'CDN', 'cacherocket' ), __( 'Rewrite static assets to your CDN CNAMEs.', 'cacherocket' ), ! empty( $settings['cdn'] ) ),
			array( __( 'Browser cache / GZIP', 'cacherocket' ), __( 'Long-lived assets and compressed responses via .htaccess.', 'cacherocket' ), ! empty( $settings['browser_cache'] ) || ! empty( $settings['gzip'] ) ),
			array( __( 'eCommerce cache', 'cacherocket' ), __( 'Cache WooCommerce catalog pages safely.', 'cacherocket' ), ! empty( $settings['cache_woocommerce'] ) && CacheRocket_Plan::can_cache_plugin_pages() ),
			array( __( 'Link prefetch', 'cacherocket' ), __( 'Prefetch internal pages on hover for snappier navigation.', 'cacherocket' ), ! empty( $settings['preload_links'] ) ),
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
			<p><?php esc_html_e( 'Store public HTML locally and optionally serve it early via advanced-cache.php on paid plans.', 'cacherocket' ); ?></p>
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
