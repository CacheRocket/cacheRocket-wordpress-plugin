<?php
/**
 * Admin layout shell.
 *
 * @package CacheRocket
 *
 * @var string               $section   Current section.
 * @var array                $pages     Page map.
 * @var array                $plan      Plan data.
 * @var array                $settings  Settings.
 * @var array                $conflicts Conflicts.
 * @var int                  $entries   Cached page count.
 * @var string               $file      Page template path.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="wrap cr-wrap">
	<?php settings_errors( 'cacherocket_messages' ); ?>
	<?php settings_errors( 'general' ); ?>

	<div class="cr-shell">
		<aside class="cr-sidebar">
			<div class="cr-brand">
				<img
					class="cr-brand__logo"
					src="<?php echo esc_url( plugins_url( 'assets/cacherocket-logo.png', CACHEROCKET_PLUGIN_FILE ) ); ?>"
					alt="<?php echo esc_attr__( 'CacheRocket', 'cacherocket' ); ?>"
					width="40"
					height="49"
				/>
				<div class="cr-brand__text">
					<strong>CacheRocket</strong>
					<span><?php esc_html_e( 'Performance suite', 'cacherocket' ); ?></span>
				</div>
			</div>

			<ul class="cr-nav">
				<?php foreach ( $pages as $cacherocket_slug => $page ) : ?>
					<?php
					$cacherocket_url = 'dashboard' === $cacherocket_slug
						? admin_url( 'admin.php?page=cacherocket' )
						: admin_url( 'admin.php?page=cacherocket-' . $cacherocket_slug );
					?>
					<li>
						<a class="<?php echo $section === $cacherocket_slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( $cacherocket_url ); ?>">
							<span class="dashicons <?php echo esc_attr( $page['icon'] ); ?>"></span>
							<?php echo esc_html( $page['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="cr-sidebar__foot">
				<?php
				printf(
					/* translators: %s: plan name */
					esc_html__( 'Plan: %s', 'cacherocket' ),
					esc_html( isset( $plan['planName'] ) ? $plan['planName'] : 'Free' )
				);
				?>
				<br />
				<a href="https://www.cacherocket.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open CacheRocket.com', 'cacherocket' ); ?></a>
			</div>
		</aside>

		<main class="cr-main">
			<?php include $file; ?>
		</main>
	</div>
</div>
