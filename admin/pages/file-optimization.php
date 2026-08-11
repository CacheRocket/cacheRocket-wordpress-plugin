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
		<h1><?php esc_html_e( 'File Optimization', 'cache-rocket' ); ?></h1>
		<p><?php esc_html_e( 'Make your files lighter — minify CSS/JS, defer scripts, delay JavaScript, and optimize fonts for better Core Web Vitals.', 'cache-rocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'CSS files', 'cache-rocket' ),
		__( 'Reduce stylesheet weight and improve font loading.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'minify_css',
		__( 'Minify CSS', 'cache-rocket' ),
		__( 'Minify inline styles and local stylesheet files (no combine). Cached under wp-content/cache/cacherocket/min/.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'optimize_google_fonts',
		__( 'Optimize Google Fonts', 'cache-rocket' ),
		__( 'Add preconnect hints and display=swap for Google Fonts stylesheets.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'self_host_fonts',
		__( 'Self-host Google Fonts', 'cache-rocket' ),
		__( 'Download Google Fonts CSS and files locally and rewrite links. Overrides the optimize-only option when both are on.', 'cache-rocket' ),
		array( 'badge' => __( 'Recommended', 'cache-rocket' ) )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'JavaScript files', 'cache-rocket' ),
		__( 'Control how scripts load to improve LCP and Interaction to Next Paint.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'minify_js',
		__( 'Minify JavaScript', 'cache-rocket' ),
		__( 'Minify inline scripts and local JS files (no combine).', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'defer_js',
		__( 'Load JavaScript deferred', 'cache-rocket' ),
		__( 'Adds the defer attribute so scripts download in parallel without blocking render. jQuery core is excluded.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js',
		__( 'Delay JavaScript execution', 'cache-rocket' ),
		__( 'Hold non-critical scripts until the visitor interacts (or after a short timeout). Strongest for LCP/INP.', 'cache-rocket' ),
		array( 'badge' => __( 'Recommended', 'cache-rocket' ) )
	);
	CacheRocket_Admin::toggle(
		'delay_js_pack_analytics',
		__( 'One-click exclusion: Analytics', 'cache-rocket' ),
		__( 'Never delay Google Analytics / Tag Manager / gtag scripts.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js_pack_ads',
		__( 'One-click exclusion: Ads', 'cache-rocket' ),
		__( 'Never delay Google Ads / DoubleClick scripts.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js_pack_chat',
		__( 'One-click exclusion: Chat widgets', 'cache-rocket' ),
		__( 'Never delay Intercom, Drift, HubSpot, Crisp, Tawk, Zendesk, LiveChat.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js_pack_maps',
		__( 'One-click exclusion: Maps', 'cache-rocket' ),
		__( 'Never delay Google Maps scripts.', 'cache-rocket' )
	);
	CacheRocket_Admin::textarea(
		'delay_js_exclusions',
		__( 'Delay JS exclusions', 'cache-rocket' ),
		__( 'One keyword per line. Matching script handles or URLs are never delayed.', 'cache-rocket' ),
		"jquery\ngtm.js"
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Extras', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'remove_query_strings',
		__( 'Remove query strings from static resources', 'cache-rocket' ),
		__( 'Strips ?ver= from CSS/JS URLs for better proxy/CDN caching.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'remove_emoji',
		__( 'Disable WordPress emoji scripts', 'cache-rocket' ),
		__( 'Removes emoji detection scripts and styles from the front end and admin.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'disable_embeds',
		__( 'Disable WordPress embeds', 'cache-rocket' ),
		__( 'Turns off oEmbed discovery and the embed script to save a request.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'remove_jquery_migrate',
		__( 'Remove jQuery Migrate', 'cache-rocket' ),
		__( 'Dequeues jquery-migrate on the front end. Only enable if your theme/plugins do not need it.', 'cache-rocket' )
	);
	CacheRocket_Admin::textarea(
		'dns_prefetch',
		__( 'DNS prefetch hosts', 'cache-rocket' ),
		__( 'One hostname per line (e.g. //cdn.example.com or fonts.gstatic.com). Adds dns-prefetch hints.', 'cache-rocket' ),
		'cdn.example.com'
	);
	CacheRocket_Admin::textarea(
		'preload_fonts',
		__( 'Preload fonts', 'cache-rocket' ),
		__( 'One local font URL per line (.woff2 recommended). Adds preload hints for those fonts.', 'cache-rocket' ),
		'/wp-content/themes/your-theme/fonts/font.woff2'
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cache-rocket' ); ?></button>
	</div>
</form>
