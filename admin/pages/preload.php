<?php
/**
 * Preload page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$cacherocket_api_ok = (bool) get_option( 'cacherocket_api_key' ) && (bool) get_option( 'cacherocket_api_secret' );
$cacherocket_account_url = admin_url( 'admin.php?page=cache-rocket-account' );
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Preload', 'cache-rocket' ); ?></h1>
		<p><?php esc_html_e( 'Build cache ahead of traffic — warm after publish, prefetch links, and warm from your XML sitemap.', 'cache-rocket' ); ?></p>
	</div>
</div>

<?php if ( ! $cacherocket_api_ok ) : ?>
	<div class="cr-notice cr-notice--warn">
		<?php esc_html_e( 'Remote warming features need CacheRocket API keys. Prefetch on hover still works without an account.', 'cache-rocket' ); ?>
		<a href="<?php echo esc_url( $cacherocket_account_url ); ?>"><?php esc_html_e( 'Connect API keys', 'cache-rocket' ); ?></a>
	</div>
<?php else : ?>
	<div class="cr-notice cr-notice--ok">
		<?php esc_html_e( 'API connected — remote warming features are available.', 'cache-rocket' ); ?>
	</div>
<?php endif; ?>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'Works without API', 'cache-rocket' ),
		__( 'These options run entirely in the visitor’s browser. No CacheRocket account required.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'preload_links',
		__( 'Prefetch links on hover', 'cache-rocket' ),
		__( 'Prefetch internal pages when visitors hover links for snappier navigation.', 'cache-rocket' ),
		array(
			'badge'       => __( 'No API needed', 'cache-rocket' ),
			'badge_class' => 'cr-badge--local',
		)
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Requires CacheRocket API', 'cache-rocket' ),
		__( 'These options call CacheRocket.com to request URLs so your page cache is filled ahead of traffic. A site warmer is created automatically (if needed) so results show under Warmers in your CacheRocket account.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'warm_on_publish',
		__( 'Warm cache on publish / update', 'cache-rocket' ),
		__( 'Priority-warm the post URL (and home/shop) via CacheRocket when content is published or updated.', 'cache-rocket' ),
		array(
			'badge'       => __( 'Requires API', 'cache-rocket' ),
			'badge_class' => 'cr-badge--api',
			'disabled'    => ! $cacherocket_api_ok,
			'preserve'    => true,
		)
	);
	CacheRocket_Admin::toggle(
		'preload_sitemap',
		__( 'Warm URLs from sitemap', 'cache-rocket' ),
		sprintf(
			/* translators: %d: max URLs for current plan */
			__( 'Daily cron parses your XML sitemap (and nested indexes) and sends up to %d URLs to CacheRocket warmUrls (limit from your plan).', 'cache-rocket' ),
			(int) CacheRocket_Sitemap_Preload::collect_limit()
		),
		array(
			'badge'       => __( 'Requires API', 'cache-rocket' ),
			'badge_class' => 'cr-badge--api',
			'disabled'    => ! $cacherocket_api_ok,
			'preserve'    => true,
		)
	);
	CacheRocket_Admin::input(
		'preload_sitemap_url',
		__( 'Sitemap URL', 'cache-rocket' ),
		__( 'Leave empty to try /wp-sitemap.xml. Example: https://example.com/sitemap_index.xml', 'cache-rocket' ),
		array(
			'type'        => 'url',
			'badge'       => __( 'Requires API', 'cache-rocket' ),
			'badge_class' => 'cr-badge--api',
			'disabled'    => ! $cacherocket_api_ok,
			'preserve'    => true,
		)
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cache-rocket' ); ?></button>
	</div>
</form>

<section class="cr-card<?php echo $cacherocket_api_ok ? '' : ' is-disabled'; ?>" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2>
			<?php esc_html_e( 'Trigger a warm now', 'cache-rocket' ); ?>
			<span class="cr-badge cr-badge--api"><?php esc_html_e( 'Requires API', 'cache-rocket' ); ?></span>
		</h2>
		<p>
			<?php
			echo $cacherocket_api_ok
				? esc_html__( 'Send selected URLs to CacheRocket for priority warming.', 'cache-rocket' )
				: esc_html__( 'Connect API keys on the Account page to use manual warming.', 'cache-rocket' );
			?>
		</p>
	</header>
	<div class="cr-card__body" style="padding:16px;">
		<form method="post" class="cr-grid" style="gap:12px;">
			<?php wp_nonce_field( 'cacherocket_trigger_warm' ); ?>
			<input class="cr-input" type="url" name="cacherocket_warm_url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" value="<?php echo esc_attr( home_url( '/' ) ); ?>" <?php disabled( ! $cacherocket_api_ok ); ?> />
			<p class="cr-field__desc"><?php esc_html_e( 'Homepage is always included. Optionally enter another URL above.', 'cache-rocket' ); ?></p>
			<button type="submit" name="cacherocket_trigger_warm" value="1" class="cr-btn cr-btn--primary" style="justify-self:start;" <?php disabled( ! $cacherocket_api_ok ); ?>><?php esc_html_e( 'Warm URLs', 'cache-rocket' ); ?></button>
		</form>
	</div>
</section>

<section class="cr-card<?php echo $cacherocket_api_ok ? '' : ' is-disabled'; ?>" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2>
			<?php esc_html_e( 'Warm from sitemap now', 'cache-rocket' ); ?>
			<span class="cr-badge cr-badge--api"><?php esc_html_e( 'Requires API', 'cache-rocket' ); ?></span>
		</h2>
		<p>
			<?php
			echo $cacherocket_api_ok
				? esc_html__( 'Parse the configured sitemap immediately and request warming for discovered URLs.', 'cache-rocket' )
				: esc_html__( 'Connect API keys on the Account page to run sitemap warming.', 'cache-rocket' );
			?>
		</p>
	</header>
	<div class="cr-card__body" style="padding:16px;">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_sitemap_warm' ); ?>
			<button type="submit" name="cacherocket_sitemap_warm" value="1" class="cr-btn cr-btn--secondary" <?php disabled( ! $cacherocket_api_ok ); ?>><?php esc_html_e( 'Run sitemap warm', 'cache-rocket' ); ?></button>
		</form>
	</div>
</section>
