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
$cacherocket_org_id     = get_option( 'cacherocket_organization_id', '' );
$cacherocket_orgs       = array();
$cacherocket_orgs_error = null;
if ( $cacherocket_api_key && $cacherocket_api_secret ) {
	$cacherocket_orgs_result = cacherocket_organizations_fetch();
	if ( is_wp_error( $cacherocket_orgs_result ) ) {
		$cacherocket_orgs_error = $cacherocket_orgs_result->get_error_message();
	} else {
		$cacherocket_orgs = $cacherocket_orgs_result;
		if ( function_exists( 'cacherocket_reconcile_organization_option' ) ) {
			cacherocket_reconcile_organization_option( $cacherocket_orgs );
			$cacherocket_org_id = get_option( 'cacherocket_organization_id', '' );
		}
	}
}
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Account', 'cache-rocket' ); ?></h1>
		<p><?php esc_html_e( 'Connect CacheRocket.com API keys, review your plan, and manage remote cache warmers.', 'cache-rocket' ); ?></p>
	</div>
	<div class="cr-actions">
		<form method="post">
			<?php wp_nonce_field( 'cacherocket_sync_plan' ); ?>
			<button type="submit" name="cacherocket_sync_plan" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Refresh plan', 'cache-rocket' ); ?></button>
		</form>
		<a class="cr-btn cr-btn--primary" href="https://www.cacherocket.com/account/account-crawlers" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Manage warmers', 'cache-rocket' ); ?></a>
	</div>
</div>

<form method="post" action="options.php">
	<?php settings_fields( 'cacherocket_account_group' ); ?>

	<section class="cr-card">
		<header class="cr-card__header">
			<h2><?php esc_html_e( 'API credentials', 'cache-rocket' ); ?></h2>
			<p><?php esc_html_e( 'Create a free account at CacheRocket.com, generate keys, then paste them here.', 'cache-rocket' ); ?></p>
		</header>
		<div class="cr-card__body">
			<div class="cr-field cr-field--stack">
				<label class="cr-field__label" for="cr-api-key"><?php esc_html_e( 'Public API Key', 'cache-rocket' ); ?></label>
				<input class="cr-input" type="text" id="cr-api-key" name="cacherocket_api_key" value="<?php echo esc_attr( $cacherocket_api_key ); ?>" autocomplete="off" />
			</div>
			<div class="cr-field cr-field--stack">
				<label class="cr-field__label" for="cr-api-secret"><?php esc_html_e( 'Secret API Key', 'cache-rocket' ); ?></label>
				<input class="cr-input" type="password" id="cr-api-secret" name="cacherocket_api_secret" value="<?php echo esc_attr( $cacherocket_api_secret ); ?>" autocomplete="new-password" />
			</div>
			<?php if ( $cacherocket_api_key && $cacherocket_api_secret ) : ?>
			<div class="cr-field cr-field--stack">
				<label class="cr-field__label" for="cr-organization-id"><?php esc_html_e( 'Team / workspace', 'cache-rocket' ); ?></label>
				<?php if ( $cacherocket_orgs_error ) : ?>
					<p class="cr-field__desc" style="color:#b45309;"><?php echo esc_html( $cacherocket_orgs_error ); ?></p>
				<?php elseif ( empty( $cacherocket_orgs ) ) : ?>
					<input type="hidden" name="cacherocket_organization_id" value="personal" />
					<p class="cr-field__desc"><?php esc_html_e( 'No teams on this account — warmers and hostnames use your personal workspace.', 'cache-rocket' ); ?></p>
				<?php else : ?>
					<select class="cr-input" id="cr-organization-id" name="cacherocket_organization_id" required>
						<option value="" <?php selected( $cacherocket_org_id, '' ); ?>><?php esc_html_e( '— Select a workspace —', 'cache-rocket' ); ?></option>
						<option value="personal" <?php selected( $cacherocket_org_id, 'personal' ); ?>><?php esc_html_e( 'Personal account', 'cache-rocket' ); ?></option>
						<?php foreach ( $cacherocket_orgs as $cacherocket_org ) : ?>
							<?php
							if ( empty( $cacherocket_org['id'] ) ) {
								continue;
							}
							$cacherocket_org_label = isset( $cacherocket_org['name'] ) ? (string) $cacherocket_org['name'] : (string) $cacherocket_org['id'];
							?>
							<option value="<?php echo esc_attr( (string) $cacherocket_org['id'] ); ?>" <?php selected( $cacherocket_org_id, (string) $cacherocket_org['id'] ); ?>>
								<?php echo esc_html( $cacherocket_org_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="cr-field__desc">
						<?php esc_html_e( 'Hostnames and warmers are created in this workspace. Pick the team that should own this site.', 'cache-rocket' ); ?>
					</p>
					<?php if ( '' === $cacherocket_org_id ) : ?>
						<div class="cr-notice cr-notice--warn" style="margin-top:8px;">
							<?php esc_html_e( 'Select a team (or Personal account) and save before using preload / warmers.', 'cache-rocket' ); ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<div class="cr-notice cr-notice--info" style="margin:8px 12px 16px;">
				<?php
				esc_html_e(
					'When API keys are connected, this site periodically reports connection status to CacheRocket.com so we can show active installs for your account. We store: your API key id, this site’s URL/domain, plugin version, WordPress version, PHP version, and a last-seen timestamp. No page content or visitor data is sent. See our privacy policy on CacheRocket.com.', 'cache-rocket'
				);
				?>
				<a href="https://cacherocket.com/terms-and-conditions" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Terms & privacy', 'cache-rocket' ); ?></a>
			</div>
		</div>
	</section>

	<div class="cr-savebar">
		<button type="submit" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Save credentials', 'cache-rocket' ); ?></button>
	</div>
</form>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Plan status', 'cache-rocket' ); ?></h2>
		<p><?php esc_html_e( 'Entitlements are synced from your CacheRocket subscription.', 'cache-rocket' ); ?></p>
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
						__( 'Last plan sync failed: %s', 'cache-rocket' ),
						$cacherocket_plan_error
					)
				);
				?>
			</div>
		<?php endif; ?>
		<table class="cr-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Plan', 'cache-rocket' ); ?></th>
					<td><?php echo esc_html( isset( $plan['planName'] ) ? $plan['planName'] : 'Free' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Tier', 'cache-rocket' ); ?></th>
					<td><?php echo ! empty( $plan['isPaid'] ) ? esc_html__( 'Paid', 'cache-rocket' ) : esc_html__( 'Free', 'cache-rocket' ); ?></td>
				</tr>
				<?php if ( CacheRocket_Plan::is_wordpress_plan() || empty( $plan['isPaid'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'WordPress plan', 'cache-rocket' ); ?></th>
					<td>
						<?php if ( CacheRocket_Plan::is_wordpress_grow_plan() ) : ?>
							<?php esc_html_e( 'Grow active (€5/month)', 'cache-rocket' ); ?>
						<?php elseif ( CacheRocket_Plan::is_wordpress_starter_plan() ) : ?>
							<?php esc_html_e( 'Starter active (€1/month)', 'cache-rocket' ); ?>
							—
							<a href="<?php echo esc_url( CacheRocket_Plan::wordpress_grow_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Upgrade to Grow €5', 'cache-rocket' ); ?>
							</a>
						<?php elseif ( CacheRocket_Plan::is_wordpress_plan() ) : ?>
							<?php esc_html_e( 'Active', 'cache-rocket' ); ?>
						<?php else : ?>
							<a href="<?php echo esc_url( CacheRocket_Plan::wordpress_pricing_url() ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Compare Starter €1 & Grow €5', 'cache-rocket' ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'WooCommerce page cache', 'cache-rocket' ); ?></th>
					<td><?php esc_html_e( 'Included (Free)', 'cache-rocket' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Early cache delivery', 'cache-rocket' ); ?></th>
					<td><?php esc_html_e( 'Included (Free)', 'cache-rocket' ); ?></td>
				</tr>
				<?php
				$cacherocket_hb = get_option( 'cacherocket_last_heartbeat', null );
				if ( is_array( $cacherocket_hb ) && ! empty( $cacherocket_hb['sentAt'] ) ) :
					?>
				<tr>
					<th><?php esc_html_e( 'Install heartbeat', 'cache-rocket' ); ?></th>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: datetime, 2: domain */
								__( 'Last reported %1$s (%2$s)', 'cache-rocket' ),
								$cacherocket_hb['sentAt'],
								isset( $cacherocket_hb['domain'] ) ? $cacherocket_hb['domain'] : ''
							)
						);
						?>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="cr-card" style="margin-top:16px;">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Cache warmers', 'cache-rocket' ); ?></h2>
		<p><?php esc_html_e( 'Create, edit, enable, and disable warmers from WordPress. Limits are enforced by the CacheRocket API.', 'cache-rocket' ); ?></p>
	</header>
	<div class="cr-card__body" style="padding:12px;">
		<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-warmers' ) ); ?>"><?php esc_html_e( 'Manage warmers', 'cache-rocket' ); ?></a>
		<a class="cr-btn cr-btn--secondary" href="https://www.cacherocket.com/account/account-crawlers" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open on CacheRocket.com', 'cache-rocket' ); ?></a>
	</div>
</section>
