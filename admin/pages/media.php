<?php
/**
 * Media page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Media', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'Load images and embeds only when needed — saving bandwidth and improving perceived performance.', 'cacherocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'LazyLoad', 'cacherocket' ),
		__( 'Defer off-screen media until visitors scroll near it.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload',
		__( 'Enable for images', 'cacherocket' ),
		__( 'Adds native loading="lazy" to images (skips fetchpriority=high candidates).', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload_iframes',
		__( 'Enable for iframes', 'cacherocket' ),
		__( 'Lazy-load embedded iframes such as maps and third-party widgets.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload_youtube',
		__( 'Replace YouTube iframe with lazy load', 'cacherocket' ),
		__( 'Applies lazy loading specifically to YouTube embeds.', 'cacherocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Image dimensions', 'cacherocket' ),
		__( 'Help the browser reserve space and reduce layout shift (CLS).', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'image_dimensions',
		__( 'Add missing image dimensions', 'cacherocket' ),
		__( 'When possible, add width/height attributes to local upload images missing them.', 'cacherocket' )
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>
