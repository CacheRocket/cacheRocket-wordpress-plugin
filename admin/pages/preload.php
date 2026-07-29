<?php
/**
 * Preload page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Preload', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'Build cache ahead of traffic — warm after publish, prefetch links, and trigger remote CacheRocket warmers.', 'cacherocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'Cache preloading', 'cacherocket' ),
		__( 'Ensure visitors get the cached version right away.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'warm_on_publish',
		__( 'Warm cache on publish / update', 'cacherocket' ),
		__( 'Priority-warm the post URL (and home/shop) via CacheRocket when content is published or updated.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'preload_links',
		__( 'Prefetch links on hover', 'cacherocket' ),
		__( 'Prefetch internal pages when visitors hover links for snappier navigation.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'preload_sitemap',
		__( 'Use sitemap URL for warming hints', 'cacherocket' ),
		__( 'Store your XML sitemap URL for manual / future automated preload runs.', 'cacherocket' )
	);
	CacheRocket_Admin::input(
		'preload_sitemap_url',
		__( 'Sitemap URL', 'cacherocket' ),
		__( 'Example: https://example.com/sitemap_index.xml', 'cacherocket' ),
		array( 'type' => 'url' )
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Trigger a warm now', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'Send selected URLs to CacheRocket for priority warming. Requires valid API keys.', 'cacherocket' ); ?></p>
	</header>
	<div class="cr-card__body" style="padding:16px;">
		<form method="post" class="cr-grid" style="gap:12px;">
			<?php wp_nonce_field( 'cacherocket_trigger_warm' ); ?>
			<input class="cr-input" type="url" name="cacherocket_warm_url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" value="<?php echo esc_attr( home_url( '/' ) ); ?>" />
			<p class="cr-field__desc"><?php esc_html_e( 'Homepage is always included. Optionally enter another URL above.', 'cacherocket' ); ?></p>
			<button type="submit" name="cacherocket_trigger_warm" value="1" class="cr-btn cr-btn--primary" style="justify-self:start;"><?php esc_html_e( 'Warm URLs', 'cacherocket' ); ?></button>
		</form>
	</div>
</section>
