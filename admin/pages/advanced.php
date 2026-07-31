<?php
/**
 * Advanced page (CDN, browser cache, heartbeat, tools).
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Advanced', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'CDN integration, browser caching, GZIP compression, Heartbeat control, and settings tools.', 'cacherocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	$cacherocket_cdn_locked = CacheRocket_Plan::can_use_cdn()
		? array()
		: array(
			'disabled'    => true,
			'preserve'    => true,
			'badge'       => __( 'Plan', 'cacherocket' ),
			'badge_class' => 'cr-badge--muted',
		);

	CacheRocket_Admin::section_start(
		__( 'CacheRocket CDN', 'cacherocket' ),
		__( 'Optimized images and Critical CSS are served automatically from assets.cacherocket.com when those Media features are enabled. You do not need to add that hostname yourself. Clearing the cache, or disabling a Media cloud feature, deletes those files from CacheRocket CDN storage for this site.', 'cacherocket' )
	);
	?>
	<p class="description" style="margin:0 0 1.25rem;">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: CDN hostname */
				__( 'Managed CDN host: %s', 'cacherocket' ),
				'assets.cacherocket.com'
			)
		);
		?>
	</p>
	<?php
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Custom CDN (optional)', 'cacherocket' ),
		__( 'Optionally rewrite your site’s scripts, styles, and media to your own CDN hostnames. This is separate from CacheRocket CDN.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cdn',
		__( 'Enable custom CDN rewriting', 'cacherocket' ),
		__( 'Rewrites scripts, styles, and attachment URLs to the hostnames below. Leave this off if you only use CacheRocket CDN for cloud-optimized assets.', 'cacherocket' ),
		$cacherocket_cdn_locked
	);
	CacheRocket_Admin::textarea(
		'cdn_cnames',
		__( 'Your CDN hostname(s)', 'cacherocket' ),
		__( 'One hostname per line, without protocol (e.g. cdn.example.com). Do not add assets.cacherocket.com here — that is configured automatically.', 'cacherocket' ),
		'cdn.example.com'
	);
	CacheRocket_Admin::textarea(
		'cdn_reject_files',
		__( 'Exclude files from custom CDN', 'cacherocket' ),
		__( 'One path/keyword per line. Matching URLs keep the origin host.', 'cacherocket' ),
		'.php'
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Browser caching & compression', 'cacherocket' ),
		__( 'Adds Apache .htaccess rules for long-lived static assets and GZIP. Nginx users should configure equivalent rules at the server.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'browser_cache',
		__( 'Browser caching', 'cacherocket' ),
		__( 'Set long Cache-Control / Expires headers for CSS, JS, images, and fonts.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'gzip',
		__( 'GZIP compression', 'cacherocket' ),
		__( 'Compress HTML, CSS, JS, and XML on the front end when mod_deflate is available. Skips wp-admin, AJAX, and JSON so admin tools (e.g. file managers) keep working. Turn this off if your host already enables Gzip.', 'cacherocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'WordPress Heartbeat', 'cacherocket' ),
		__( 'Reduce admin-ajax traffic from the Heartbeat API.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'heartbeat_control',
		__( 'Control Heartbeat frequency', 'cacherocket' ),
		__( 'Override the default interval used by wp-admin / post editing.', 'cacherocket' )
	);
	CacheRocket_Admin::input(
		'heartbeat_frequency',
		__( 'Heartbeat interval (seconds)', 'cacherocket' ),
		__( 'Higher values reduce server load. 60 is a good balance.', 'cacherocket' ),
		array(
			'type'    => 'select',
			'options' => array(
				15  => '15',
				30  => '30',
				60  => '60',
				120 => '120',
			),
		)
	);
	CacheRocket_Admin::section_end();
	?>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cacherocket' ); ?></button>
	</div>
</form>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Import / export settings', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'Download a JSON backup of CacheRocket settings, or restore from a previous export. API keys are not included.', 'cacherocket' ); ?></p>
	</header>
	<div class="cr-card__body" style="padding:16px;display:grid;gap:16px;">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_export_settings' ); ?>
			<button type="submit" name="cacherocket_export_settings" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Export settings', 'cacherocket' ); ?></button>
		</form>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'cacherocket_import_settings' ); ?>
			<input type="file" name="cacherocket_import_file" accept="application/json,.json" required />
			<button type="submit" name="cacherocket_import_settings" value="1" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Import settings', 'cacherocket' ); ?></button>
		</form>
	</div>
</section>
