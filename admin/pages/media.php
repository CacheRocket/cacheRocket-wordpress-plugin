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
		<h1><?php esc_html_e( 'Media', 'cache-rocket' ); ?></h1>
		<p><?php esc_html_e( 'Load images and embeds only when needed — and prioritize LCP images for Core Web Vitals.', 'cache-rocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'LazyLoad', 'cache-rocket' ),
		__( 'Defer off-screen media until visitors scroll near it.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload',
		__( 'Enable for images', 'cache-rocket' ),
		__( 'Adds native loading="lazy" to images including those inside <picture> (skips fetchpriority=high / LCP candidates).', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload_iframes',
		__( 'Enable for iframes', 'cache-rocket' ),
		__( 'Lazy-load embedded iframes such as maps and third-party widgets.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'lazyload_youtube',
		__( 'Replace YouTube iframe with preview', 'cache-rocket' ),
		__( 'Swap YouTube embeds for a lightweight thumbnail facade; the iframe loads on click.', 'cache-rocket' ),
		array( 'badge' => __( 'Recommended', 'cache-rocket' ) )
	);
	CacheRocket_Admin::toggle(
		'lazyload_css_bg',
		__( 'LazyLoad CSS background images', 'cache-rocket' ),
		__( 'Defers inline style background-image until the element nears the viewport.', 'cache-rocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Image dimensions', 'cache-rocket' ),
		__( 'Help the browser reserve space and reduce layout shift (CLS).', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'image_dimensions',
		__( 'Add missing image dimensions', 'cache-rocket' ),
		__( 'When possible, add width/height attributes to local upload images missing them.', 'cache-rocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Critical images & rendering', 'cache-rocket' ),
		__( 'Prioritize above-the-fold images and delay rendering of below-the-fold sections.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'critical_images',
		__( 'Optimize Critical Images (LCP)', 'cache-rocket' ),
		__( 'Detect the Largest Contentful Paint image, preload it, and set fetchpriority=high on later visits.', 'cache-rocket' ),
		array( 'badge' => __( 'Recommended', 'cache-rocket' ) )
	);
	CacheRocket_Admin::toggle(
		'lazy_rendering',
		__( 'Automatic Lazy Rendering', 'cache-rocket' ),
		__( 'Apply content-visibility:auto to below-the-fold sections so the browser can skip rendering them initially.', 'cache-rocket' )
	);
	CacheRocket_Admin::textarea(
		'lazy_rendering_selectors',
		__( 'Lazy rendering selectors', 'cache-rocket' ),
		__( 'One CSS id/class/tag per line to mark for lazy rendering (e.g. footer, .site-footer, #colophon).', 'cache-rocket' ),
		"footer\n.site-footer\n#colophon\naside"
	);
	CacheRocket_Admin::section_end();

	$cacherocket_plan_locked = array(
		'disabled'    => true,
		'preserve'    => true,
		'badge'       => __( 'Plan', 'cache-rocket' ),
		'badge_class' => 'cr-badge--muted',
	);
	$cacherocket_can_image = CacheRocket_Plan::can_use_image_optimization();
	$cacherocket_can_lqip  = CacheRocket_Plan::can_use_lqip();
	$cacherocket_can_ccss  = CacheRocket_Plan::can_use_critical_css();
	$cacherocket_can_psi   = CacheRocket_Plan::can_use_page_speed_scores();
	$cacherocket_cloud_locked = ! $cacherocket_can_image || ! $cacherocket_can_lqip || ! $cacherocket_can_ccss || ! $cacherocket_can_psi;

	if ( $cacherocket_cloud_locked ) :
		?>
		<div class="cr-notice cr-notice--info" style="margin-bottom:16px;">
			<?php esc_html_e( 'Cloud media features need WordPress Starter (€1) for CDN/WebP, or WordPress Grow (€5) for Critical CSS, LQIP, and PageSpeed.', 'cache-rocket' ); ?>
			<a href="<?php echo esc_url( CacheRocket_Plan::wordpress_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Starter €1', 'cache-rocket' ); ?></a>
			·
			<a href="<?php echo esc_url( CacheRocket_Plan::wordpress_grow_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Grow €5', 'cache-rocket' ); ?></a>
		</div>
		<?php
	endif;

	CacheRocket_Admin::section_start(
		__( 'Cloud image optimization', 'cache-rocket' ),
		__( 'Convert your images to WebP/AVIF and serve them from CacheRocket Image CDN (img.cacherocket.com). No CDN hostname setup required. Uses your monthly image quota. Turning this off deletes those CDN files for this site.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_image_opt',
		__( 'Optimize images on CacheRocket CDN', 'cache-rocket' ),
		__( 'Queues new uploads plus your existing library for cloud optimization, and rewrites front-end URLs to img.cacherocket.com when ready.', 'cache-rocket' ),
		$cacherocket_can_image ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_webp',
		__( 'Prefer WebP', 'cache-rocket' ),
		__( 'Serve WebP variants from img.cacherocket.com when available.', 'cache-rocket' ),
		$cacherocket_can_image ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_avif',
		__( 'Prefer AVIF (Pro+)', 'cache-rocket' ),
		__( 'Prefer AVIF over WebP when your plan allows it (served from img.cacherocket.com).', 'cache-rocket' ),
		$cacherocket_can_image ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_lqip',
		__( 'Low-quality image placeholders (LQIP)', 'cache-rocket' ),
		__( 'Generate tiny blurred placeholders for your images and use them while full images load.', 'cache-rocket' ),
		$cacherocket_can_lqip ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Critical CSS & PageSpeed', 'cache-rocket' ),
		__( 'Generate above-the-fold CSS and run Lighthouse audits in CacheRocket cloud.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_critical_css',
		__( 'Generate Critical CSS', 'cache-rocket' ),
		__( 'Automatically queue critical CSS for singular pages. CacheRocket generates it in the cloud; the plugin checks for completion within seconds on traffic and then loads the stylesheet from assets.cacherocket.com.', 'cache-rocket' ),
		$cacherocket_can_ccss ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::toggle(
		'cloud_pagespeed',
		__( 'Enable PageSpeed tools', 'cache-rocket' ),
		__( 'Unlocks the “Run PageSpeed” action below (uses daily audit quota).', 'cache-rocket' ),
		$cacherocket_can_psi ? array() : $cacherocket_plan_locked
	);
	CacheRocket_Admin::section_end();
	?>

	<?php if ( $cacherocket_can_psi && CacheRocket_Options::get( 'cloud_pagespeed' ) ) : ?>
		<?php
		$cacherocket_psi     = get_option( CacheRocket_Cloud_Opt::OPTION_PSI, array() );
		$cacherocket_scores  = ( is_array( $cacherocket_psi ) && ! empty( $cacherocket_psi['result']['scores'] ) && is_array( $cacherocket_psi['result']['scores'] ) )
			? $cacherocket_psi['result']['scores']
			: array();
		$cacherocket_has_psi = ! empty( $cacherocket_scores );
		$cacherocket_psi_metrics = array(
			array(
				'key'   => 'performance',
				'label' => __( 'Performance', 'cache-rocket' ),
			),
			array(
				'key'   => 'accessibility',
				'label' => __( 'Accessibility', 'cache-rocket' ),
			),
			array(
				'key'   => 'bestPractices',
				'label' => __( 'Best practices', 'cache-rocket' ),
			),
			array(
				'key'   => 'seo',
				'label' => __( 'SEO', 'cache-rocket' ),
			),
		);

		/**
		 * Map a Lighthouse score to a rating class.
		 *
		 * @param mixed $score Score value.
		 * @return string
		 */
		$cacherocket_psi_rating = static function ( $score ) {
			if ( ! is_numeric( $score ) ) {
				return 'na';
			}
			$score = (int) $score;
			if ( $score >= 90 ) {
				return 'good';
			}
			if ( $score >= 50 ) {
				return 'average';
			}
			return 'poor';
		};

		$cacherocket_psi_updated = '';
		if ( ! empty( $cacherocket_psi['updated'] ) ) {
			$cacherocket_psi_ts = strtotime( (string) $cacherocket_psi['updated'] );
			if ( $cacherocket_psi_ts ) {
				$cacherocket_psi_updated = sprintf(
					/* translators: %s: localized date/time */
					__( 'Last result: %s', 'cache-rocket' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cacherocket_psi_ts )
				);
			} else {
				$cacherocket_psi_updated = sprintf(
					/* translators: %s: datetime string */
					__( 'Last result: %s', 'cache-rocket' ),
					(string) $cacherocket_psi['updated']
				);
			}
		}
		?>
		<section class="cr-card cr-psi" style="margin: 1.5rem 0;">
			<header class="cr-card__header cr-psi__header">
				<div>
					<h2><?php esc_html_e( 'PageSpeed Insights', 'cache-rocket' ); ?></h2>
					<p><?php esc_html_e( 'Queue a Lighthouse audit for your homepage. Results appear after the worker finishes (refresh this page).', 'cache-rocket' ); ?></p>
				</div>
				<div class="cr-psi__actions">
					<button type="button" class="cr-btn cr-btn--primary" id="cr-run-pagespeed">
						<span class="dashicons dashicons-performance" aria-hidden="true"></span>
						<?php esc_html_e( 'Run mobile PageSpeed', 'cache-rocket' ); ?>
					</button>
					<span id="cr-pagespeed-status" class="cr-psi__status" aria-live="polite"></span>
				</div>
			</header>
			<div class="cr-psi__body">
				<?php if ( $cacherocket_has_psi ) : ?>
					<div class="cr-psi__scores" role="list">
						<?php foreach ( $cacherocket_psi_metrics as $cacherocket_metric ) :
							$cacherocket_raw   = isset( $cacherocket_scores[ $cacherocket_metric['key'] ] ) ? $cacherocket_scores[ $cacherocket_metric['key'] ] : null;
							$cacherocket_score = is_numeric( $cacherocket_raw ) ? max( 0, min( 100, (int) $cacherocket_raw ) ) : null;
							$cacherocket_rate  = $cacherocket_psi_rating( $cacherocket_score );
							$cacherocket_pct   = null !== $cacherocket_score ? (string) $cacherocket_score : '0';
							?>
							<div class="cr-psi__metric" role="listitem">
								<div
									class="cr-gauge cr-gauge--<?php echo esc_attr( $cacherocket_rate ); ?>"
									style="--cr-score: <?php echo esc_attr( $cacherocket_pct ); ?>;"
									aria-label="<?php echo esc_attr( sprintf( /* translators: 1: metric name, 2: score */ __( '%1$s: %2$s', 'cache-rocket' ), $cacherocket_metric['label'], null !== $cacherocket_score ? (string) $cacherocket_score : '—' ) ); ?>"
								>
									<span class="cr-gauge__ring" aria-hidden="true"></span>
									<span class="cr-gauge__value"><?php echo null !== $cacherocket_score ? esc_html( (string) $cacherocket_score ) : esc_html( '—' ); ?></span>
								</div>
								<span class="cr-psi__label"><?php echo esc_html( $cacherocket_metric['label'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( $cacherocket_psi_updated ) : ?>
						<p class="cr-psi__meta"><?php echo esc_html( $cacherocket_psi_updated ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<div class="cr-psi__empty">
						<div class="cr-psi__empty-icon" aria-hidden="true">
							<span class="dashicons dashicons-chart-area"></span>
						</div>
						<p class="cr-psi__empty-title"><?php esc_html_e( 'No audit results yet', 'cache-rocket' ); ?></p>
						<p class="cr-psi__empty-desc"><?php esc_html_e( 'Run a mobile PageSpeed audit to see Performance, Accessibility, Best practices, and SEO scores here.', 'cache-rocket' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<script>
		(function () {
			var btn = document.getElementById('cr-run-pagespeed');
			var status = document.getElementById('cr-pagespeed-status');
			if (!btn) return;
			btn.addEventListener('click', function () {
				btn.disabled = true;
				status.textContent = <?php echo wp_json_encode( __( 'Queuing…', 'cache-rocket' ) ); ?>;
				status.classList.remove('is-error', 'is-ok');
				var body = new FormData();
				body.append('action', 'cacherocket_run_pagespeed');
				body.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'cacherocket_cloud_opt' ) ); ?>);
				body.append('strategy', 'mobile');
				fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (json && json.success) {
							status.textContent = <?php echo wp_json_encode( __( 'Queued. Refresh in a minute to see scores.', 'cache-rocket' ) ); ?>;
							status.classList.add('is-ok');
						} else {
							status.textContent = (json && json.data && json.data.message) ? json.data.message : <?php echo wp_json_encode( __( 'Failed', 'cache-rocket' ) ); ?>;
							status.classList.add('is-error');
						}
					})
					.catch(function () {
						status.textContent = <?php echo wp_json_encode( __( 'Request failed', 'cache-rocket' ) ); ?>;
						status.classList.add('is-error');
					})
					.finally(function () { btn.disabled = false; });
			});
		})();
		</script>
	<?php endif; ?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cache-rocket' ); ?></button>
	</div>
</form>
