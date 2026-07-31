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

	$cacherocket_plan_locked = array(
		'disabled'    => true,
		'preserve'    => true,
		'badge'       => __( 'Plan', 'cacherocket' ),
		'badge_class' => 'cr-badge--muted',
	);
	$cacherocket_can_image = CacheRocket_Plan::can_use_image_optimization();
	$cacherocket_can_lqip  = CacheRocket_Plan::can_use_lqip();
	$cacherocket_can_ccss  = CacheRocket_Plan::can_use_critical_css();
	$cacherocket_can_psi   = CacheRocket_Plan::can_use_page_speed_scores();

	CacheRocket_Admin::section_start(
		__( 'Cloud image optimization', 'cacherocket' ),
		__( 'Convert new uploads to WebP/AVIF and serve them from CacheRocket CDN (assets.cacherocket.com). No CDN hostname setup required. Uses your monthly image quota. Turning this off deletes those CDN files for this site.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_image_opt',
		__( 'Optimize new uploads on CacheRocket CDN', 'cacherocket' ),
		__( 'Queues each new image for cloud optimization and rewrites front-end URLs to assets.cacherocket.com when ready.', 'cacherocket' ),
		$cacherocket_can_image ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_webp',
		__( 'Prefer WebP', 'cacherocket' ),
		__( 'Serve WebP variants from assets.cacherocket.com when available.', 'cacherocket' ),
		$cacherocket_can_image ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_avif',
		__( 'Prefer AVIF (Pro+)', 'cacherocket' ),
		__( 'Prefer AVIF over WebP when your plan allows it (served from assets.cacherocket.com).', 'cacherocket' ),
		$cacherocket_can_image ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_lqip',
		__( 'Low-quality image placeholders (LQIP)', 'cacherocket' ),
		__( 'Generate tiny blurred placeholders for new uploads and use them while full images load.', 'cacherocket' ),
		$cacherocket_can_lqip ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Critical CSS & PageSpeed', 'cacherocket' ),
		__( 'Generate above-the-fold CSS and run Lighthouse audits in CacheRocket cloud.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_critical_css',
		__( 'Generate Critical CSS', 'cacherocket' ),
		__( 'Automatically queue critical CSS for singular pages and load the stylesheet from assets.cacherocket.com when ready.', 'cacherocket' ),
		$cacherocket_can_ccss ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_pagespeed',
		__( 'Enable PageSpeed tools', 'cacherocket' ),
		__( 'Unlocks the “Run PageSpeed” action below (uses daily audit quota).', 'cacherocket' ),
		$cacherocket_can_psi ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::section_end();
	?>

	<?php if ( $cacherocket_can_psi && CacheRocket_Options::get( 'cloud_pagespeed' ) ) : ?>
		<div class="cr-card" style="margin: 1.5rem 0;">
			<h2><?php esc_html_e( 'PageSpeed Insights', 'cacherocket' ); ?></h2>
			<p><?php esc_html_e( 'Queue a Lighthouse audit for your homepage. Results appear after the worker finishes (refresh this page).', 'cacherocket' ); ?></p>
			<p>
				<button type="button" class="cr-btn" id="cr-run-pagespeed"><?php esc_html_e( 'Run mobile PageSpeed', 'cacherocket' ); ?></button>
				<span id="cr-pagespeed-status" class="description" style="margin-left:.75rem;"></span>
			</p>
			<?php
			$cacherocket_psi = get_option( CacheRocket_Cloud_Opt::OPTION_PSI, array() );
			if ( is_array( $cacherocket_psi ) && ! empty( $cacherocket_psi['result']['scores'] ) ) :
				$cacherocket_scores = $cacherocket_psi['result']['scores'];
				?>
				<ul>
					<li><?php echo esc_html( sprintf( /* translators: %s score */ __( 'Performance: %s', 'cacherocket' ), isset( $cacherocket_scores['performance'] ) ? (string) $cacherocket_scores['performance'] : '—' ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %s score */ __( 'Accessibility: %s', 'cacherocket' ), isset( $cacherocket_scores['accessibility'] ) ? (string) $cacherocket_scores['accessibility'] : '—' ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %s score */ __( 'Best practices: %s', 'cacherocket' ), isset( $cacherocket_scores['bestPractices'] ) ? (string) $cacherocket_scores['bestPractices'] : '—' ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %s score */ __( 'SEO: %s', 'cacherocket' ), isset( $cacherocket_scores['seo'] ) ? (string) $cacherocket_scores['seo'] : '—' ) ); ?></li>
				</ul>
				<?php if ( ! empty( $cacherocket_psi['updated'] ) ) : ?>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %s datetime */ __( 'Last result: %s', 'cacherocket' ), (string) $cacherocket_psi['updated'] ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<script>
		(function () {
			var btn = document.getElementById('cr-run-pagespeed');
			var status = document.getElementById('cr-pagespeed-status');
			if (!btn) return;
			btn.addEventListener('click', function () {
				btn.disabled = true;
				status.textContent = <?php echo wp_json_encode( __( 'Queuing…', 'cacherocket' ) ); ?>;
				var body = new FormData();
				body.append('action', 'cacherocket_run_pagespeed');
				body.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'cacherocket_cloud_opt' ) ); ?>);
				body.append('strategy', 'mobile');
				fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (json && json.success) {
							status.textContent = <?php echo wp_json_encode( __( 'Queued. Refresh in a minute to see scores.', 'cacherocket' ) ); ?>;
						} else {
							status.textContent = (json && json.data && json.data.message) ? json.data.message : <?php echo wp_json_encode( __( 'Failed', 'cacherocket' ) ); ?>;
						}
					})
					.catch(function () {
						status.textContent = <?php echo wp_json_encode( __( 'Request failed', 'cacherocket' ) ); ?>;
					})
					.finally(function () { btn.disabled = false; });
			});
		})();
		</script>
	<?php endif; ?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>
