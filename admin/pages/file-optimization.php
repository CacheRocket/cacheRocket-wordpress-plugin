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
		<p><?php esc_html_e( 'Make your files lighter — minify CSS/JS, defer scripts, delay JavaScript, and optimize fonts for better Core Web Vitals.', 'cacherocket' ); ?></p>
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
		__( 'Minify inline styles and local stylesheet files (no combine). Cached under wp-content/cache/cacherocket/min/.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'optimize_google_fonts',
		__( 'Optimize Google Fonts', 'cacherocket' ),
		__( 'Add preconnect hints and display=swap for Google Fonts stylesheets.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'self_host_fonts',
		__( 'Self-host Google Fonts', 'cacherocket' ),
		__( 'Download Google Fonts CSS and files locally and rewrite links. Overrides the optimize-only option when both are on.', 'cacherocket' ),
		array( 'badge' => __( 'Recommended', 'cacherocket' ) )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'JavaScript files', 'cacherocket' ),
		__( 'Control how scripts load to improve LCP and Interaction to Next Paint.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'minify_js',
		__( 'Minify JavaScript', 'cacherocket' ),
		__( 'Minify inline scripts and local JS files (no combine).', 'cacherocket' )
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
	CacheRocket_Admin::toggle(
		'delay_js_pack_analytics',
		__( 'One-click exclusion: Analytics', 'cacherocket' ),
		__( 'Never delay Google Analytics / Tag Manager / gtag scripts.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js_pack_ads',
		__( 'One-click exclusion: Ads', 'cacherocket' ),
		__( 'Never delay Google Ads / DoubleClick scripts.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js_pack_chat',
		__( 'One-click exclusion: Chat widgets', 'cacherocket' ),
		__( 'Never delay Intercom, Drift, HubSpot, Crisp, Tawk, Zendesk, LiveChat.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'delay_js_pack_maps',
		__( 'One-click exclusion: Maps', 'cacherocket' ),
		__( 'Never delay Google Maps scripts.', 'cacherocket' )
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
	CacheRocket_Admin::toggle(
		'remove_emoji',
		__( 'Disable WordPress emoji scripts', 'cacherocket' ),
		__( 'Removes emoji detection scripts and styles from the front end and admin.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'disable_embeds',
		__( 'Disable WordPress embeds', 'cacherocket' ),
		__( 'Turns off oEmbed discovery and the embed script to save a request.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'remove_jquery_migrate',
		__( 'Remove jQuery Migrate', 'cacherocket' ),
		__( 'Dequeues jquery-migrate on the front end. Only enable if your theme/plugins do not need it.', 'cacherocket' )
	);
	CacheRocket_Admin::textarea(
		'dns_prefetch',
		__( 'DNS prefetch hosts', 'cacherocket' ),
		__( 'One hostname per line (e.g. //cdn.example.com or fonts.gstatic.com). Adds dns-prefetch hints.', 'cacherocket' ),
		'cdn.example.com'
	);
	CacheRocket_Admin::textarea(
		'preload_fonts',
		__( 'Preload fonts', 'cacherocket' ),
		__( 'One local font URL per line (.woff2 recommended). Adds preload hints for those fonts.', 'cacherocket' ),
		'/wp-content/themes/your-theme/fonts/font.woff2'
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>
