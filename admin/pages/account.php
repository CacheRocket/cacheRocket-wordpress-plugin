<?php
/**
 * Account / API page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$cacherocket_api_key    = get_option( 'cacherocket_api_key', '' );
$cacherocket_api_secret = get_option( 'cacherocket_api_secret', '' );
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Account', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'Connect CacheRocket.com API keys, review your plan, and manage remote cache warmers.', 'cacherocket' ); ?></p>
	</div>
	<div class="cr-actions">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_sync_plan' ); ?>
			<button type="submit" name="cacherocket_sync_plan" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Refresh plan', 'cacherocket' ); ?></button>
		</form>
		<a class="cr-btn cr-btn--primary" href="https://www.cacherocket.com/account/account-crawlers" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Manage warmers', 'cacherocket' ); ?></a>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_account_group' ); ?>

	<section class="cr-card">
		<header class="cr-card__header">
			<h2><?php esc_html_e( 'API credentials', 'cacherocket' ); ?></h2>
			<p><?php esc_html_e( 'Create a free account at CacheRocket.com, generate keys, then paste them here.', 'cacherocket' ); ?></p>
		</header>
		<div class="cr-card__body">
			<div class="cr-field cr-field--stack">
				<label class="cr-field__label" for="cr-api-key"><?php esc_html_e( 'Public API Key', 'cacherocket' ); ?></label>
				<input class="cr-input" type="text" id="cr-api-key" name="cacherocket_api_key" value="<?php echo esc_attr( $cacherocket_api_key ); ?>" autocomplete="off" />
			</div>
			<div class="cr-field cr-field--stack">
				<label class="cr-field__label" for="cr-api-secret"><?php esc_html_e( 'Secret API Key', 'cacherocket' ); ?></label>
				<input class="cr-input" type="password" id="cr-api-secret" name="cacherocket_api_secret" value="<?php echo esc_attr( $cacherocket_api_secret ); ?>" autocomplete="new-password" />
			</div>
		</div>
	</section>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save credentials', 'cacherocket' ); ?></button>
	</div>
</form>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Plan status', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'Entitlements are synced from your CacheRocket subscription.', 'cacherocket' ); ?></p>
	</header>
	<div class="cr-card__body" style="padding:8px 4px;">
		<?php
		$cacherocket_plan_error = CacheRocket_Plan::get_last_error();
		if ( $cacherocket_plan_error ) :
			?>
			<div class="cr-notice cr-notice--warn" style="margin:8px 12px;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: error message */
						__( 'Last plan sync failed: %s', 'cacherocket' ),
						$cacherocket_plan_error
					)
				);
				?>
			</div>
		<?php endif; ?>
		<table class="cr-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Plan', 'cacherocket' ); ?></th>
					<td><?php echo esc_html( isset( $plan['planName'] ) ? $plan['planName'] : 'Free' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Tier', 'cacherocket' ); ?></th>
					<td><?php echo ! empty( $plan['isPaid'] ) ? esc_html__( 'Paid', 'cacherocket' ) : esc_html__( 'Free', 'cacherocket' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WooCommerce page cache', 'cacherocket' ); ?></th>
					<td><?php echo CacheRocket_Plan::can_cache_plugin_pages() ? esc_html__( 'Allowed', 'cacherocket' ) : esc_html__( 'Upgrade required', 'cacherocket' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Early cache delivery', 'cacherocket' ); ?></th>
					<td><?php echo CacheRocket_Plan::can_use_early_cache() ? esc_html__( 'Allowed', 'cacherocket' ) : esc_html__( 'Upgrade required', 'cacherocket' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</section>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Cache warmers', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'Create, edit, enable, and disable warmers from WordPress. Limits are enforced by the CacheRocket API.', 'cacherocket' ); ?></p>
	</header>
	<div class="cr-card__body" style="padding:12px;">
		<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-warmers' ) ); ?>"><?php esc_html_e( 'Manage warmers', 'cacherocket' ); ?></a>
		<a class="cr-btn cr-btn--secondary" href="https://www.cacherocket.com/account/account-crawlers" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open on CacheRocket.com', 'cacherocket' ); ?></a>
	</div>
</section>
