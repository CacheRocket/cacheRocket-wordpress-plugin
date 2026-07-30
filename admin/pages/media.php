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
		<p><?php esc_html_e( 'Load images and embeds only when needed — and prioritize LCP images for Core Web Vitals.', 'cacherocket' ); ?></p>
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
		__( 'Adds native loading="lazy" to images including those inside <picture> (skips fetchpriority=high / LCP candidates).', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload_iframes',
		__( 'Enable for iframes', 'cacherocket' ),
		__( 'Lazy-load embedded iframes such as maps and third-party widgets.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload_youtube',
		__( 'Replace YouTube iframe with preview', 'cacherocket' ),
		__( 'Swap YouTube embeds for a lightweight thumbnail facade; the iframe loads on click.', 'cacherocket' ),
		array( 'badge' => __( 'Recommended', 'cacherocket' ) )
	);
	CacheRocket_Admin::toggle(
		'lazyload_css_bg',
		__( 'LazyLoad CSS background images', 'cacherocket' ),
		__( 'Defers inline style background-image until the element nears the viewport.', 'cacherocket' )
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

	CacheRocket_Admin::section_start(
		__( 'Critical images & rendering', 'cacherocket' ),
		__( 'Prioritize above-the-fold images and delay rendering of below-the-fold sections.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'critical_images',
		__( 'Optimize Critical Images (LCP)', 'cacherocket' ),
		__( 'Detect the Largest Contentful Paint image, preload it, and set fetchpriority=high on later visits.', 'cacherocket' ),
		array( 'badge' => __( 'Recommended', 'cacherocket' ) )
	);
	CacheRocket_Admin::toggle(
		'lazy_rendering',
		__( 'Automatic Lazy Rendering', 'cacherocket' ),
		__( 'Apply content-visibility:auto to below-the-fold sections so the browser can skip rendering them initially.', 'cacherocket' )
	);
	CacheRocket_Admin::textarea(
		'lazy_rendering_selectors',
		__( 'Lazy rendering selectors', 'cacherocket' ),
		__( 'One CSS id/class/tag per line to mark for lazy rendering (e.g. footer, .site-footer, #colophon).', 'cacherocket' ),
		"footer\n.site-footer\n#colophon\naside"
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>
