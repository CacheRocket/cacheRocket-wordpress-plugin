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
$cacherocket_account_url = admin_url( 'admin.php?page=cacherocket-account' );
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Preload', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'Build cache ahead of traffic — warm after publish, prefetch links, and warm from your XML sitemap.', 'cacherocket' ); ?></p>
	</div>
</div>

<?php if ( ! $cacherocket_api_ok ) : ?>
	<div class="cr-notice cr-notice--warn">
		<?php esc_html_e( 'Remote warming features need CacheRocket API keys. Prefetch on hover still works without an account.', 'cacherocket' ); ?>
		<a href="<?php echo esc_url( $cacherocket_account_url ); ?>"><?php esc_html_e( 'Connect API keys', 'cacherocket' ); ?></a>
	</div>
<?php else : ?>
	<div class="cr-notice cr-notice--ok">
		<?php esc_html_e( 'API connected — remote warming features are available.', 'cacherocket' ); ?>
	</div>
<?php endif; ?>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'Works without API', 'cacherocket' ),
		__( 'These options run entirely in the visitor’s browser. No CacheRocket account required.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'preload_links',
		__( 'Prefetch links on hover', 'cacherocket' ),
		__( 'Prefetch internal pages when visitors hover links for snappier navigation.', 'cacherocket' ),
		array(
			'badge'       => __( 'No API needed', 'cacherocket' ),
			'badge_class' => 'cr-badge--local',
		)
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Requires CacheRocket API', 'cacherocket' ),
		__( 'These options call CacheRocket.com to request URLs so your page cache is filled ahead of traffic. A site warmer is created automatically (if needed) so results show under Warmers in your CacheRocket account.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'warm_on_publish',
		__( 'Warm cache on publish / update', 'cacherocket' ),
		__( 'Priority-warm the post URL (and home/shop) via CacheRocket when content is published or updated.', 'cacherocket' ),
		array(
			'badge'       => __( 'Requires API', 'cacherocket' ),
			'badge_class' => 'cr-badge--api',
			'disabled'    => ! $cacherocket_api_ok,
			'preserve'    => true,
		)
	);
	CacheRocket_Admin::toggle(
		'preload_sitemap',
		__( 'Warm URLs from sitemap', 'cacherocket' ),
		__( 'Daily cron parses your XML sitemap (and nested indexes) and sends up to 200 URLs to CacheRocket warmUrls.', 'cacherocket' ),
		array(
			'badge'       => __( 'Requires API', 'cacherocket' ),
			'badge_class' => 'cr-badge--api',
			'disabled'    => ! $cacherocket_api_ok,
			'preserve'    => true,
		)
	);
	CacheRocket_Admin::input(
		'preload_sitemap_url',
		__( 'Sitemap URL', 'cacherocket' ),
		__( 'Leave empty to try /wp-sitemap.xml. Example: https://example.com/sitemap_index.xml', 'cacherocket' ),
		array(
			'type'        => 'url',
			'badge'       => __( 'Requires API', 'cacherocket' ),
			'badge_class' => 'cr-badge--api',
			'disabled'    => ! $cacherocket_api_ok,
			'preserve'    => true,
		)
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>

<section class="cr-card<?php echo $cacherocket_api_ok ? '' : ' is-disabled'; ?>" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2>
			<?php esc_html_e( 'Trigger a warm now', 'cacherocket' ); ?>
			<span class="cr-badge cr-badge--api"><?php esc_html_e( 'Requires API', 'cacherocket' ); ?></span>
		</h2>
		<p>
			<?php
			echo $cacherocket_api_ok
				? esc_html__( 'Send selected URLs to CacheRocket for priority warming.', 'cacherocket' )
				: esc_html__( 'Connect API keys on the Account page to use manual warming.', 'cacherocket' );
			?>
		</p>
	</header>
	<div class="cr-card__body" style="padding:16px;">
		<form method="post" class="cr-grid" style="gap:12px;">
			<?php wp_nonce_field( 'cacherocket_trigger_warm' ); ?>
			<input class="cr-input" type="url" name="cacherocket_warm_url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" value="<?php echo esc_attr( home_url( '/' ) ); ?>" <?php disabled( ! $cacherocket_api_ok ); ?> />
			<p class="cr-field__desc"><?php esc_html_e( 'Homepage is always included. Optionally enter another URL above.', 'cacherocket' ); ?></p>
			<button type="submit" name="cacherocket_trigger_warm" value="1" class="cr-btn cr-btn--primary" style="justify-self:start;" <?php disabled( ! $cacherocket_api_ok ); ?>><?php esc_html_e( 'Warm URLs', 'cacherocket' ); ?></button>
		</form>
	</div>
</section>

<section class="cr-card<?php echo $cacherocket_api_ok ? '' : ' is-disabled'; ?>" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2>
			<?php esc_html_e( 'Warm from sitemap now', 'cacherocket' ); ?>
			<span class="cr-badge cr-badge--api"><?php esc_html_e( 'Requires API', 'cacherocket' ); ?></span>
		</h2>
		<p>
			<?php
			echo $cacherocket_api_ok
				? esc_html__( 'Parse the configured sitemap immediately and request warming for discovered URLs.', 'cacherocket' )
				: esc_html__( 'Connect API keys on the Account page to run sitemap warming.', 'cacherocket' );
			?>
		</p>
	</header>
	<div class="cr-card__body" style="padding:16px;">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_sitemap_warm' ); ?>
			<button type="submit" name="cacherocket_sitemap_warm" value="1" class="cr-btn cr-btn--secondary" <?php disabled( ! $cacherocket_api_ok ); ?>><?php esc_html_e( 'Run sitemap warm', 'cacherocket' ); ?></button>
		</form>
	</div>
</section>
