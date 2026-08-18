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

	$cacherocket_can_image = CacheRocket_Plan::can_use_image_optimization();
	$cacherocket_can_lqip  = CacheRocket_Plan::can_use_lqip();
	$cacherocket_can_ccss  = CacheRocket_Plan::can_use_critical_css();
	$cacherocket_can_psi   = CacheRocket_Plan::can_use_page_speed_scores();
	$cacherocket_cloud_notice = ! $cacherocket_can_image || ! $cacherocket_can_lqip || ! $cacherocket_can_ccss || ! $cacherocket_can_psi;

	if ( $cacherocket_cloud_notice ) :
		?>
		<div class="cr-notice cr-notice--info" style="margin-bottom:16px;">
			<?php esc_html_e( 'Cloud media features are processed by CacheRocket.com. Your plan gates quotas on the service: WordPress Starter (€1) for CDN/WebP, or WordPress Grow (€5) for Critical CSS, LQIP, and PageSpeed. You can enable the toggles anytime; the API enforces entitlements.', 'cache-rocket' ); ?>
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
		__( 'Queues new uploads plus your existing library for cloud optimization, and rewrites front-end URLs to img.cacherocket.com when ready.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_webp',
		__( 'Prefer WebP', 'cache-rocket' ),
		__( 'Serve WebP variants from img.cacherocket.com when available.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_avif',
		__( 'Prefer AVIF (Pro+)', 'cache-rocket' ),
		__( 'Prefer AVIF over WebP when your plan allows it (served from img.cacherocket.com).', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_image_cdn',
		__( 'Responsive on-demand delivery', 'cache-rocket' ),
		__( 'Serve every registered size from img.cacherocket.com/i/* with a proper srcset so browsers download the right resolution (fixes “properly size images” in PageSpeed).', 'cache-rocket' ),
		array(
			'badge'    => CacheRocket_Plan::can_use_on_demand_image_cdn() ? '' : __( 'Starter+', 'cache-rocket' ),
			'disabled' => ! CacheRocket_Plan::can_use_on_demand_image_cdn(),
			'preserve' => true,
		)
	);
	CacheRocket_Admin::input(
		'cloud_image_quality',
		__( 'Compression quality', 'cache-rocket' ),
		__( 'Lower = smaller files, higher = sharper. 75 is a good balance (40–95).', 'cache-rocket' ),
		array(
			'type' => 'number',
			'min'  => 40,
			'max'  => 95,
		)
	);
	CacheRocket_Admin::input(
		'cloud_image_max_width',
		__( 'Max width on upload (px)', 'cache-rocket' ),
		__( 'Downscale oversized originals to this width before optimizing. Set 0 to keep full resolution.', 'cache-rocket' ),
		array(
			'type' => 'number',
			'min'  => 0,
			'max'  => 4096,
		)
	);
	CacheRocket_Admin::toggle(
		'cloud_lqip',
		__( 'Low-quality image placeholders (LQIP)', 'cache-rocket' ),
		__( 'Generate tiny blurred placeholders for your images and use them while full images load.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_image_backup',
		__( 'Back up originals (restore support)', 'cache-rocket' ),
		__( 'Keep an untouched copy of each original in cloud storage so you can restore it later. Uses image storage quota.', 'cache-rocket' ),
		array(
			'badge'    => CacheRocket_Plan::can_use_image_backup() ? '' : __( 'Grow', 'cache-rocket' ),
			'disabled' => ! CacheRocket_Plan::can_use_image_backup(),
			'preserve' => true,
		)
	);
	CacheRocket_Admin::toggle(
		'cloud_pdf_opt',
		__( 'Optimize PDF uploads', 'cache-rocket' ),
		__( 'Also compress PDF documents on upload where your plan allows it.', 'cache-rocket' ),
		array(
			'badge'    => CacheRocket_Plan::can_use_pdf_optimization() ? '' : __( 'Grow', 'cache-rocket' ),
			'disabled' => ! CacheRocket_Plan::can_use_pdf_optimization(),
			'preserve' => true,
		)
	);
	CacheRocket_Admin::textarea(
		'cloud_image_exclusions',
		__( 'Exclude from CDN rewriting', 'cache-rocket' ),
		__( 'One URL fragment per line. Any image URL containing a fragment is served from your origin unchanged (e.g. /logo.svg, /wp-content/uploads/2020/).', 'cache-rocket' ),
		"/logo\n/wp-content/uploads/no-cdn/"
	);
	CacheRocket_Admin::section_end();

	$cacherocket_store = CacheRocket_Plan::image_storage_usage();
	$cacherocket_usage = CacheRocket_Plan::get_plan();
	$cacherocket_usage = isset( $cacherocket_usage['usage'] ) && is_array( $cacherocket_usage['usage'] ) ? $cacherocket_usage['usage'] : array();
	$cacherocket_img_opts = isset( $cacherocket_usage['imageOptsMonth'] ) && is_array( $cacherocket_usage['imageOptsMonth'] ) ? $cacherocket_usage['imageOptsMonth'] : array();
	$cacherocket_cdn      = isset( $cacherocket_usage['cdn']['bandwidthGbMonth'] ) && is_array( $cacherocket_usage['cdn']['bandwidthGbMonth'] ) ? $cacherocket_usage['cdn']['bandwidthGbMonth'] : array();

	if ( $cacherocket_can_image ) :
		CacheRocket_Admin::section_start(
			__( 'Usage this period', 'cache-rocket' ),
			__( 'Your monthly image optimizations, CDN bandwidth, and stored image bytes.', 'cache-rocket' )
		);
		?>
		<div class="cr-meters">
			<div class="cr-meter">
				<span class="cr-meter__label"><?php esc_html_e( 'Image optimizations', 'cache-rocket' ); ?></span>
				<span class="cr-meter__value">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: used, 2: limit */
							__( '%1$s / %2$s', 'cache-rocket' ),
							number_format_i18n( isset( $cacherocket_img_opts['used'] ) ? (int) $cacherocket_img_opts['used'] : 0 ),
							number_format_i18n( isset( $cacherocket_img_opts['limit'] ) ? (int) $cacherocket_img_opts['limit'] : 0 )
						)
					);
					?>
				</span>
			</div>
			<div class="cr-meter">
				<span class="cr-meter__label"><?php esc_html_e( 'Image storage', 'cache-rocket' ); ?></span>
				<span class="cr-meter__value">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: used GB, 2: limit GB */
							__( '%1$s GB / %2$s GB', 'cache-rocket' ),
							number_format_i18n( $cacherocket_store['usedGb'], 2 ),
							number_format_i18n( $cacherocket_store['limitGb'], 0 )
						)
					);
					?>
				</span>
			</div>
			<div class="cr-meter">
				<span class="cr-meter__label"><?php esc_html_e( 'CDN bandwidth', 'cache-rocket' ); ?></span>
				<span class="cr-meter__value">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: used GB, 2: limit GB */
							__( '%1$s GB / %2$s GB', 'cache-rocket' ),
							number_format_i18n( isset( $cacherocket_cdn['used'] ) ? (float) $cacherocket_cdn['used'] : 0, 2 ),
							number_format_i18n( isset( $cacherocket_cdn['limit'] ) ? (float) $cacherocket_cdn['limit'] : 0, 0 )
						)
					);
					?>
				</span>
			</div>
		</div>
		<?php
		CacheRocket_Admin::section_end();

		CacheRocket_Admin::section_start(
			__( 'Bulk optimize', 'cache-rocket' ),
			__( 'Queue your existing media library for cloud optimization. Runs in batches; you can leave this page and progress continues in the background.', 'cache-rocket' )
		);
		?>
		<div class="cr-bulk" id="cr-bulk-optimize">
			<div class="cr-bulk__progress">
				<div class="cr-bulk__bar"><span class="cr-bulk__fill" style="width:0%"></span></div>
				<p class="cr-bulk__status" aria-live="polite"></p>
			</div>
			<div class="cr-bulk__actions">
				<button type="button" class="cr-btn cr-btn--primary" id="cr-bulk-start">
					<?php esc_html_e( 'Start bulk optimization', 'cache-rocket' ); ?>
				</button>
				<button type="button" class="cr-btn" id="cr-bulk-stop" hidden>
					<?php esc_html_e( 'Pause', 'cache-rocket' ); ?>
				</button>
			</div>
		</div>
		<?php
		CacheRocket_Admin::section_end();

		if ( CacheRocket_Plan::can_use_directory_optimize() ) :
			CacheRocket_Admin::section_start(
				__( 'Optimize other directories', 'cache-rocket' ),
				__( 'Optimize images outside the media library (theme or plugin folders). One path per line, relative to the WordPress root.', 'cache-rocket' )
			);
			CacheRocket_Admin::textarea(
				'cloud_directory_paths',
				__( 'Directories to scan', 'cache-rocket' ),
				__( 'Example: wp-content/themes/mytheme/images', 'cache-rocket' ),
				"wp-content/themes/\nwp-content/uploads/custom/"
			);
			?>
			<div class="cr-bulk__actions">
				<button type="button" class="cr-btn" id="cr-dir-optimize">
					<?php esc_html_e( 'Scan & optimize directories', 'cache-rocket' ); ?>
				</button>
				<span id="cr-dir-status" class="cr-psi__status" aria-live="polite"></span>
			</div>
			<p class="cr-field__desc"><?php esc_html_e( 'Save your directory list first, then run the scan.', 'cache-rocket' ); ?></p>
			<?php
			CacheRocket_Admin::section_end();
		endif;

		CacheRocket_Admin::section_start(
			__( 'Recent optimization jobs', 'cache-rocket' ),
			__( 'The latest jobs processed by CacheRocket for this account.', 'cache-rocket' )
		);
		?>
		<div id="cr-job-history">
			<table class="cr-jobs" hidden>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'cache-rocket' ); ?></th>
						<th><?php esc_html_e( 'Status', 'cache-rocket' ); ?></th>
						<th><?php esc_html_e( 'Source', 'cache-rocket' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'cache-rocket' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
			<p class="cr-jobs__status" aria-live="polite"><?php esc_html_e( 'Loading…', 'cache-rocket' ); ?></p>
		</div>
		<?php
		CacheRocket_Admin::section_end();
	endif;

	CacheRocket_Admin::section_start(
		__( 'Critical CSS & PageSpeed', 'cache-rocket' ),
		__( 'Generate above-the-fold CSS and run Lighthouse audits in CacheRocket cloud.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_critical_css',
		__( 'Generate Critical CSS', 'cache-rocket' ),
		__( 'Automatically queue critical CSS for singular pages. CacheRocket generates it in the cloud; the plugin checks for completion within seconds on traffic and then loads the stylesheet from assets.cacherocket.com.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cloud_pagespeed',
		__( 'Enable PageSpeed tools', 'cache-rocket' ),
		__( 'Unlocks the “Run PageSpeed” action below (uses daily audit quota).', 'cache-rocket' )
	);
	CacheRocket_Admin::section_end();
	?>

	<?php if ( CacheRocket_Options::get( 'cloud_pagespeed' ) ) : ?>
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
	<?php endif; ?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cache-rocket' ); ?></button>
	</div>
</form>
