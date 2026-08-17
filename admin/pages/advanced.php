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
		<h1><?php esc_html_e( 'Advanced', 'cache-rocket' ); ?></h1>
		<p><?php esc_html_e( 'CDN integration, browser caching, GZIP compression, Heartbeat control, and settings tools.', 'cache-rocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'CacheRocket CDN', 'cache-rocket' ),
		__( 'Optimized images are served from img.cacherocket.com and Critical CSS from assets.cacherocket.com when those Media features are enabled. You do not need to add those hostnames yourself. Clearing the cache, or disabling a Media cloud feature, deletes those files from CacheRocket CDN storage for this site.', 'cache-rocket' )
	);
	?>
	<p class="description" style="margin:0 0 1.25rem;">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: image CDN hostname, 2: assets CDN hostname */
				__( 'Image CDN: %1$s · Assets CDN: %2$s', 'cache-rocket' ),
				'img.cacherocket.com',
				'assets.cacherocket.com'
			)
		);
		?>
	</p>
	<?php
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Custom CDN (optional)', 'cache-rocket' ),
		__( 'Optionally rewrite your site’s scripts, styles, and media to your own CDN hostnames. This is separate from CacheRocket CDN.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'cdn',
		__( 'Enable custom CDN rewriting', 'cache-rocket' ),
		__( 'Rewrites scripts, styles, and attachment URLs to the hostnames below. Leave this off if you only use CacheRocket CDN for cloud-optimized assets.', 'cache-rocket' )
	);
	CacheRocket_Admin::textarea(
		'cdn_cnames',
		__( 'Your CDN hostname(s)', 'cache-rocket' ),
		__( 'One hostname per line, without protocol (e.g. cdn.example.com). Do not add assets.cacherocket.com or img.cacherocket.com here — those are configured automatically.', 'cache-rocket' ),
		'cdn.example.com'
	);
	CacheRocket_Admin::textarea(
		'cdn_reject_files',
		__( 'Exclude files from custom CDN', 'cache-rocket' ),
		__( 'One path/keyword per line. Matching URLs keep the origin host.', 'cache-rocket' ),
		'.php'
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'Browser caching & compression', 'cache-rocket' ),
		__( 'Adds Apache .htaccess rules for long-lived static assets and GZIP. Nginx users should configure equivalent rules at the server.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'browser_cache',
		__( 'Browser caching', 'cache-rocket' ),
		__( 'Set long Cache-Control / Expires headers for CSS, JS, images, and fonts.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'gzip',
		__( 'GZIP compression', 'cache-rocket' ),
		__( 'Compress HTML, CSS, JS, and XML on the front end when mod_deflate is available. Skips wp-admin, AJAX, and JSON so admin tools (e.g. file managers) keep working. Turn this off if your host already enables Gzip.', 'cache-rocket' )
	);
	CacheRocket_Admin::section_end();

	CacheRocket_Admin::section_start(
		__( 'WordPress Heartbeat', 'cache-rocket' ),
		__( 'Reduce admin-ajax traffic from the Heartbeat API.', 'cache-rocket' )
	);
	CacheRocket_Admin::toggle(
		'heartbeat_control',
		__( 'Control Heartbeat frequency', 'cache-rocket' ),
		__( 'Override the default interval used by wp-admin / post editing.', 'cache-rocket' )
	);
	CacheRocket_Admin::input(
		'heartbeat_frequency',
		__( 'Heartbeat interval (seconds)', 'cache-rocket' ),
		__( 'Higher values reduce server load. 60 is a good balance.', 'cache-rocket' ),
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
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save changes', 'cache-rocket' ); ?></button>
	</div>
</form>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Import / export settings', 'cache-rocket' ); ?></h2>
		<p><?php esc_html_e( 'Download a JSON backup of CacheRocket settings, or restore from a previous export. API keys are not included.', 'cache-rocket' ); ?></p>
	</header>
	<div class="cr-card__body" style="padding:16px;display:grid;gap:16px;">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_export_settings' ); ?>
			<button type="submit" name="cacherocket_export_settings" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Export settings', 'cache-rocket' ); ?></button>
		</form>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'cacherocket_import_settings' ); ?>
			<input type="file" name="cacherocket_import_file" accept="application/json,.json" required />
			<button type="submit" name="cacherocket_import_settings" value="1" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Import settings', 'cache-rocket' ); ?></button>
		</form>
	</div>
</section>
