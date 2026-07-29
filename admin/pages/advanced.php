<?php
/**
 * Advanced page (CDN, browser cache, heartbeat).
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
		<p><?php esc_html_e( 'CDN integration, browser caching, GZIP compression, and Heartbeat control for finer performance tuning.', 'cacherocket' ); ?></p>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_settings_group' ); ?>

	<?php
	CacheRocket_Admin::section_start(
		__( 'CDN', 'cacherocket' ),
		__( 'Rewrite static asset URLs to your CDN CNAMEs to reduce latency for global visitors.', 'cacherocket' )
	);
	CacheRocket_Admin::toggle(
		'cdn',
		__( 'Enable Content Delivery Network', 'cacherocket' ),
		__( 'Rewrites scripts, styles, and attachment URLs to the CNAMEs below.', 'cacherocket' )
	);
	CacheRocket_Admin::textarea(
		'cdn_cnames',
		__( 'CDN CNAME(s)', 'cacherocket' ),
		__( 'One hostname per line, without protocol (e.g.cdn.example.com).', 'cacherocket' ),
		'cdn.example.com'
	);
	CacheRocket_Admin::textarea(
		'cdn_reject_files',
		__( 'Exclude files from CDN', 'cacherocket' ),
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
		__( 'Compress HTML, CSS, JS, and XML responses when mod_deflate is available.', 'cacherocket' )
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
