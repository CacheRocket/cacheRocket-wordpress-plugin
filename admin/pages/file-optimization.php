<?php
/**
 * File optimization page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'File Optimization', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'Make your files lighter — minify CSS/JS, defer scripts, and delay JavaScript until interaction for better Core Web Vitals.', 'cacherocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'CSS files', 'cacherocket' ),
		__( 'Reduce stylesheet weight and improve font loading.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'minify_css',
		__( 'Minify CSS', 'cacherocket' ),
		__( 'Strip comments and whitespace from inline CSS in the HTML response.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'optimize_google_fonts',
		__( 'Optimize Google Fonts', 'cacherocket' ),
		__( 'Add preconnect hints and display=swap for Google Fonts stylesheets.', 'cacherocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'JavaScript files', 'cacherocket' ),
		__( 'Control how scripts load to improve LCP and Interaction to Next Paint.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'minify_js',
		__( 'Minify JavaScript', 'cacherocket' ),
		__( 'Strip comments and excess whitespace from inline scripts.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'defer_js',
		__( 'Load JavaScript deferred', 'cacherocket' ),
		__( 'Adds the defer attribute so scripts download in parallel without blocking render. jQuery core is excluded.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js',
		__( 'Delay JavaScript execution', 'cacherocket' ),
		__( 'Hold non-critical scripts until the visitor interacts (or after a short timeout). Strongest for LCP/INP.', 'cacherocket' ),
		array( 'badge' => __( 'Recommended', 'cacherocket' ) )
	);
	CacheRocket_Admin::textarea(
		'delay_js_exclusions',
		__( 'Delay JS exclusions', 'cacherocket' ),
		__( 'One keyword per line. Matching script handles or URLs are never delayed.', 'cacherocket' ),
		"jquery\ngtm.js"
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Extras', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'remove_query_strings',
		__( 'Remove query strings from static resources', 'cacherocket' ),
		__( 'Strips ?ver= from CSS/JS URLs for better proxy/CDN caching.', 'cacherocket' )
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>
