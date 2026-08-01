<?php
/**
 * Cache warmers management page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$cacherocket_api_ok   = (bool) get_option( 'cacherocket_api_key' ) && (bool) get_option( 'cacherocket_api_secret' );
$cacherocket_ents     = CacheRocket_Warmers::entitlements();
$cacherocket_action   = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cacherocket_edit_id  = isset( $_GET['crawler_id'] ) ? sanitize_text_field( wp_unslash( $_GET['crawler_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cacherocket_is_form  = ( 'new' === $cacherocket_action ) || ( '' !== $cacherocket_edit_id );
$cacherocket_crawler  = null;
$cacherocket_form     = CacheRocket_Warmers::form_defaults();
$cacherocket_list     = array();
$cacherocket_list_err = '';

if ( ! $cacherocket_api_ok ) {
	?>
	<div class="cr-main__header">
		<div>
			<h1><?php esc_html_e( 'Cache Warmers', 'cacherocket' ); ?></h1>
			<p><?php esc_html_e( 'Create and manage remote CacheRocket warmers from WordPress. Configuration is enforced by your CacheRocket plan on the server.', 'cacherocket' ); ?></p>
		</div>
	</div>
	<div class="cr-notice cr-notice--warn">
		<?php esc_html_e( 'Connect API keys on the Account page before managing warmers.', 'cacherocket' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-account' ) ); ?>"><?php esc_html_e( 'Open Account', 'cacherocket' ); ?></a>
	</div>
	<?php
	return;
}

if ( $cacherocket_is_form && '' !== $cacherocket_edit_id ) {
	$cacherocket_fetched = cacherocket_crawler_get( $cacherocket_edit_id );
	if ( is_wp_error( $cacherocket_fetched ) ) {
		echo '<div class="cr-notice cr-notice--warn">' . esc_html( $cacherocket_fetched->get_error_message() ) . '</div>';
		$cacherocket_is_form = false;
	} else {
		$cacherocket_crawler = isset( $cacherocket_fetched['crawler'] ) ? $cacherocket_fetched['crawler'] : null;
		$cacherocket_form    = CacheRocket_Warmers::form_defaults( is_array( $cacherocket_crawler ) ? $cacherocket_crawler : null );
	}
}

if ( ! $cacherocket_is_form ) {
	$cacherocket_fetched = cacherocket_crawlers_fetch_data();
	if ( is_wp_error( $cacherocket_fetched ) ) {
		$cacherocket_list_err = $cacherocket_fetched->get_error_message();
	} else {
		$cacherocket_list = ! empty( $cacherocket_fetched['crawlers'] ) && is_array( $cacherocket_fetched['crawlers'] ) ? $cacherocket_fetched['crawlers'] : array();
	}
}

$cacherocket_at_limit = count( $cacherocket_list ) >= (int) $cacherocket_ents['maxCrawlers'];

if ( ! empty( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<div class="cr-notice cr-notice--info">' . esc_html__( 'Warmer saved.', 'cacherocket' ) . '</div>';
}
if ( ! empty( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<div class="cr-notice cr-notice--info">' . esc_html__( 'Warmer deleted.', 'cacherocket' ) . '</div>';
}
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Cache Warmers', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'Create, edit, enable, and disable remote warmers. Limits and advanced options come from your CacheRocket subscription and are enforced by the API.', 'cacherocket' ); ?></p>
	</div>
	<div class="cr-actions">
		<?php if ( $cacherocket_is_form ) : ?>
			<a class="cr-btn cr-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-warmers' ) ); ?>"><?php esc_html_e( 'Back to list', 'cacherocket' ); ?></a>
		<?php else : ?>
			<?php if ( ! $cacherocket_at_limit ) : ?>
				<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-warmers&action=new' ) ); ?>"><?php esc_html_e( 'Add warmer', 'cacherocket' ); ?></a>
			<?php endif; ?>
			<a class="cr-btn cr-btn--secondary" href="https://www.cacherocket.com/account/account-hostnames" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Verify hostnames', 'cacherocket' ); ?></a>
		<?php endif; ?>
	</div>
</div>

<div class="cr-notice cr-notice--info">
	<?php
	printf(
		/* translators: 1: current plan name, 2: max warmers */
		esc_html__( 'Plan: %1$s — up to %2$d warmer(s). Entry URLs must use verified hostnames on your CacheRocket account.', 'cacherocket' ),
		esc_html( isset( $plan['planName'] ) ? (string) $plan['planName'] : 'Free' ),
		(int) $cacherocket_ents['maxCrawlers']
	);
	?>
</div>

<?php if ( $cacherocket_is_form ) : ?>
	<?php
	$cacherocket_is_edit = '' !== $cacherocket_form['crawlerId'];
	?>
	<form method="post">
		<?php wp_nonce_field( 'cacherocket_warmer_save' ); ?>
		<?php if ( $cacherocket_is_edit ) : ?>
			<input type="hidden" name="cacherocket_warmer_id" value="<?php echo esc_attr( $cacherocket_form['crawlerId'] ); ?>" />
		<?php endif; ?>

		<section class="cr-card">
			<header class="cr-card__header">
				<h2><?php echo $cacherocket_is_edit ? esc_html__( 'Edit warmer', 'cacherocket' ) : esc_html__( 'New warmer', 'cacherocket' ); ?></h2>
				<p><?php esc_html_e( 'Same configuration options as CacheRocket.com, filtered by your plan.', 'cacherocket' ); ?></p>
			</header>
			<div class="cr-card__body">
				<div class="cr-field cr-field--stack">
					<label class="cr-field__label" for="cr-warmer-name"><?php esc_html_e( 'Name', 'cacherocket' ); ?></label>
					<input class="cr-input" id="cr-warmer-name" name="cacherocket_warmer_name" type="text" maxlength="120" required value="<?php echo esc_attr( $cacherocket_form['name'] ); ?>" />
				</div>
				<div class="cr-field cr-field--toggle">
					<div class="cr-field__text">
						<div class="cr-field__label"><?php esc_html_e( 'Active', 'cacherocket' ); ?></div>
						<p class="cr-field__desc"><?php esc_html_e( 'Disable to stop the warmer without deleting it.', 'cacherocket' ); ?></p>
					</div>
					<label class="cr-switch">
						<input type="checkbox" name="cacherocket_warmer_active" value="1" <?php checked( $cacherocket_form['active'] ); ?> />
						<span class="cr-switch__slider"></span>
					</label>
				</div>
			</div>
		</section>

		<section class="cr-card" style="margin-top:16px;">
			<header class="cr-card__header">
				<h2><?php esc_html_e( 'Entry points', 'cacherocket' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %d: max entry urls */
						esc_html__( 'One URL per line (max %d). Hostnames must be verified.', 'cacherocket' ),
						(int) $cacherocket_ents['maxEntryUrlsPerCrawler']
					);
					?>
				</p>
			</header>
			<div class="cr-card__body">
				<div class="cr-field cr-field--stack">
					<label class="cr-field__label" for="cr-warmer-entry"><?php esc_html_e( 'Entry URLs', 'cacherocket' ); ?></label>
					<textarea class="cr-input" id="cr-warmer-entry" name="cacherocket_warmer_entry_urls" rows="5" required><?php echo esc_textarea( $cacherocket_form['entryUrls'] ); ?></textarea>
				</div>
				<?php if ( ! empty( $cacherocket_ents['allowIncludeSitemaps'] ) ) : ?>
					<div class="cr-field cr-field--toggle">
						<div class="cr-field__text">
							<div class="cr-field__label"><?php esc_html_e( 'Include sitemaps', 'cacherocket' ); ?></div>
						</div>
						<label class="cr-switch">
							<input type="checkbox" name="cacherocket_warmer_include_sitemaps" value="1" <?php checked( $cacherocket_form['includeSitemaps'] ); ?> />
							<span class="cr-switch__slider"></span>
						</label>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( ! empty( $cacherocket_ents['allowExcludedUrls'] ) || ! empty( $cacherocket_ents['allowUrlParams'] ) || ! empty( $cacherocket_ents['allowCookies'] ) || ! empty( $cacherocket_ents['allowRequestHeaders'] ) || ! empty( $cacherocket_ents['allowUserAgents'] ) || ! empty( $cacherocket_ents['allowMobileUserAgents'] ) ) : ?>
			<section class="cr-card" style="margin-top:16px;">
				<header class="cr-card__header">
					<h2><?php esc_html_e( 'Exclusions & request options', 'cacherocket' ); ?></h2>
					<p><?php esc_html_e( 'Optional filters and request overrides available on your plan.', 'cacherocket' ); ?></p>
				</header>
				<div class="cr-card__body">
					<?php if ( ! empty( $cacherocket_ents['allowExcludedUrls'] ) ) : ?>
						<div class="cr-field cr-field--stack">
							<label class="cr-field__label" for="cr-warmer-excluded"><?php esc_html_e( 'Excluded URLs', 'cacherocket' ); ?></label>
							<textarea class="cr-input" id="cr-warmer-excluded" name="cacherocket_warmer_excluded_urls" rows="4"><?php echo esc_textarea( $cacherocket_form['excludedUrls'] ); ?></textarea>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $cacherocket_ents['allowUrlParams'] ) ) : ?>
						<div class="cr-field cr-field--stack">
							<label class="cr-field__label" for="cr-warmer-params"><?php esc_html_e( 'URL params (name=value per line)', 'cacherocket' ); ?></label>
							<textarea class="cr-input" id="cr-warmer-params" name="cacherocket_warmer_url_params" rows="3"><?php echo esc_textarea( $cacherocket_form['urlParams'] ); ?></textarea>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $cacherocket_ents['allowCookies'] ) ) : ?>
						<div class="cr-field cr-field--stack">
							<label class="cr-field__label" for="cr-warmer-cookies"><?php esc_html_e( 'Cookies (name=value). Re-enter to change; blank keeps redacted secrets untouched on save only if omitted — enter new values to update.', 'cacherocket' ); ?></label>
							<textarea class="cr-input" id="cr-warmer-cookies" name="cacherocket_warmer_cookies" rows="3" placeholder="<?php esc_attr_e( 'session=…', 'cacherocket' ); ?>"><?php echo esc_textarea( $cacherocket_form['cookies'] ); ?></textarea>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $cacherocket_ents['allowRequestHeaders'] ) ) : ?>
						<div class="cr-field cr-field--stack">
							<label class="cr-field__label" for="cr-warmer-headers"><?php esc_html_e( 'Request headers (name=value)', 'cacherocket' ); ?></label>
							<textarea class="cr-input" id="cr-warmer-headers" name="cacherocket_warmer_headers" rows="3"><?php echo esc_textarea( $cacherocket_form['requestHeaders'] ); ?></textarea>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $cacherocket_ents['allowUserAgents'] ) ) : ?>
						<div class="cr-field cr-field--stack">
							<label class="cr-field__label" for="cr-warmer-ua"><?php esc_html_e( 'User agents (one per line)', 'cacherocket' ); ?></label>
							<textarea class="cr-input" id="cr-warmer-ua" name="cacherocket_warmer_user_agents" rows="3"><?php echo esc_textarea( $cacherocket_form['userAgents'] ); ?></textarea>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $cacherocket_ents['allowMobileUserAgents'] ) ) : ?>
						<div class="cr-field cr-field--stack">
							<label class="cr-field__label" for="cr-warmer-mua"><?php esc_html_e( 'Mobile user agents (one per line)', 'cacherocket' ); ?></label>
							<textarea class="cr-input" id="cr-warmer-mua" name="cacherocket_warmer_mobile_agents" rows="3"><?php echo esc_textarea( $cacherocket_form['mobileUserAgents'] ); ?></textarea>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="cr-card" style="margin-top:16px;">
			<header class="cr-card__header">
				<h2><?php esc_html_e( 'Limits', 'cacherocket' ); ?></h2>
				<p><?php esc_html_e( 'Values are clamped to your plan on the server.', 'cacherocket' ); ?></p>
			</header>
			<div class="cr-card__body">
				<?php if ( ! empty( $cacherocket_ents['allowCustomDepth'] ) ) : ?>
					<div class="cr-field cr-field--stack">
						<label class="cr-field__label" for="cr-warmer-depth"><?php esc_html_e( 'Depth', 'cacherocket' ); ?></label>
						<input class="cr-input" id="cr-warmer-depth" name="cacherocket_warmer_depth" type="number" min="0" max="<?php echo esc_attr( (string) $cacherocket_ents['maxDepth'] ); ?>" value="<?php echo esc_attr( (string) $cacherocket_form['depth'] ); ?>" />
					</div>
				<?php else : ?>
					<input type="hidden" name="cacherocket_warmer_depth" value="<?php echo esc_attr( (string) $cacherocket_form['depth'] ); ?>" />
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowMaxUrlsPerMinute'] ) ) : ?>
					<div class="cr-field cr-field--stack">
						<label class="cr-field__label" for="cr-warmer-rate"><?php esc_html_e( 'Max URLs / minute', 'cacherocket' ); ?></label>
						<input class="cr-input" id="cr-warmer-rate" name="cacherocket_warmer_max_urls_minute" type="number" min="1" max="<?php echo esc_attr( (string) $cacherocket_ents['maxUrlCrawlsMinute'] ); ?>" value="<?php echo esc_attr( (string) $cacherocket_form['maxUrlCrawlsMinute'] ); ?>" />
					</div>
				<?php else : ?>
					<input type="hidden" name="cacherocket_warmer_max_urls_minute" value="<?php echo esc_attr( (string) $cacherocket_form['maxUrlCrawlsMinute'] ); ?>" />
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowRequestTimeout'] ) ) : ?>
					<div class="cr-field cr-field--stack">
						<label class="cr-field__label" for="cr-warmer-timeout"><?php esc_html_e( 'Request timeout (seconds)', 'cacherocket' ); ?></label>
						<input class="cr-input" id="cr-warmer-timeout" name="cacherocket_warmer_request_timeout" type="number" min="1" max="<?php echo esc_attr( (string) $cacherocket_ents['maxRequestTimeout'] ); ?>" value="<?php echo esc_attr( (string) $cacherocket_form['requestTimeout'] ); ?>" />
					</div>
				<?php else : ?>
					<input type="hidden" name="cacherocket_warmer_request_timeout" value="<?php echo esc_attr( (string) $cacherocket_form['requestTimeout'] ); ?>" />
				<?php endif; ?>
				<?php
				$cacherocket_intervals = CacheRocket_Warmers::intervals_for_cache_ttl();
				?>
				<input type="hidden" name="cacherocket_warmer_auto_start" value="<?php echo esc_attr( (string) $cacherocket_intervals['autoStartInterval'] ); ?>" />
				<input type="hidden" name="cacherocket_warmer_enqueue" value="<?php echo esc_attr( (string) $cacherocket_intervals['enqueueInterval'] ); ?>" />
				<div class="cr-notice cr-notice--info" style="margin:8px 0 0;">
					<?php
					printf(
						/* translators: 1: cache TTL seconds, 2: auto-start seconds, 3: enqueue seconds */
						esc_html__( 'Crawl intervals follow your page cache lifetime (%1$s s). Auto-start %2$s s · enqueue %3$s s — not configurable here.', 'cacherocket' ),
						esc_html( (string) $cacherocket_intervals['cacheTtl'] ),
						esc_html( (string) $cacherocket_intervals['autoStartInterval'] ),
						esc_html( (string) $cacherocket_intervals['enqueueInterval'] )
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-cache' ) ); ?>"><?php esc_html_e( 'Edit cache lifetime', 'cacherocket' ); ?></a>
				</div>
			</div>
		</section>

		<section class="cr-card" style="margin-top:16px;">
			<header class="cr-card__header">
				<h2><?php esc_html_e( 'Options', 'cacherocket' ); ?></h2>
			</header>
			<div class="cr-card__body">
				<?php if ( ! empty( $cacherocket_ents['allowIncludePublicPosts'] ) ) : ?>
					<div class="cr-field cr-field--toggle">
						<div class="cr-field__text">
							<div class="cr-field__label"><?php esc_html_e( 'Include public posts', 'cacherocket' ); ?></div>
						</div>
						<label class="cr-switch">
							<input type="checkbox" name="cacherocket_warmer_include_public_posts" value="1" <?php checked( $cacherocket_form['includePublicPosts'] ); ?> />
							<span class="cr-switch__slider"></span>
						</label>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowUseRegex'] ) ) : ?>
					<div class="cr-field cr-field--toggle">
						<div class="cr-field__text">
							<div class="cr-field__label"><?php esc_html_e( 'Use regex for exclusions', 'cacherocket' ); ?></div>
						</div>
						<label class="cr-switch">
							<input type="checkbox" name="cacherocket_warmer_use_regex" value="1" <?php checked( $cacherocket_form['useRegex'] ); ?> />
							<span class="cr-switch__slider"></span>
						</label>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowUseCanonical'] ) ) : ?>
					<div class="cr-field cr-field--toggle">
						<div class="cr-field__text">
							<div class="cr-field__label"><?php esc_html_e( 'Prefer canonical URLs', 'cacherocket' ); ?></div>
						</div>
						<label class="cr-switch">
							<input type="checkbox" name="cacherocket_warmer_use_canonical" value="1" <?php checked( $cacherocket_form['useCanonical'] ); ?> />
							<span class="cr-switch__slider"></span>
						</label>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowRewriteToHttps'] ) ) : ?>
					<div class="cr-field cr-field--toggle">
						<div class="cr-field__text">
							<div class="cr-field__label"><?php esc_html_e( 'Rewrite to HTTPS', 'cacherocket' ); ?></div>
						</div>
						<label class="cr-switch">
							<input type="checkbox" name="cacherocket_warmer_rewrite_https" value="1" <?php checked( $cacherocket_form['rewriteToHttps'] ); ?> />
							<span class="cr-switch__slider"></span>
						</label>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowCrawlMobile'] ) ) : ?>
					<div class="cr-field cr-field--toggle">
						<div class="cr-field__text">
							<div class="cr-field__label"><?php esc_html_e( 'Crawl mobile variants', 'cacherocket' ); ?></div>
						</div>
						<label class="cr-switch">
							<input type="checkbox" name="cacherocket_warmer_crawl_mobile" value="1" <?php checked( $cacherocket_form['crawlMobile'] ); ?> />
							<span class="cr-switch__slider"></span>
						</label>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowBrowserWarm'] ) ) : ?>
					<div class="cr-field cr-field--toggle">
						<div class="cr-field__text">
							<div class="cr-field__label"><?php esc_html_e( 'Browser warm', 'cacherocket' ); ?></div>
						</div>
						<label class="cr-switch">
							<input type="checkbox" name="cacherocket_warmer_browser_warm" value="1" <?php checked( $cacherocket_form['browserWarm'] ); ?> />
							<span class="cr-switch__slider"></span>
						</label>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $cacherocket_ents['allowWarmSchedule'] ) ) : ?>
					<div class="cr-field cr-field--stack">
						<label class="cr-field__label" for="cr-warmer-schedule"><?php esc_html_e( 'Warm schedule JSON', 'cacherocket' ); ?></label>
						<textarea class="cr-input" id="cr-warmer-schedule" name="cacherocket_warmer_schedule" rows="4"><?php echo esc_textarea( $cacherocket_form['warmScheduleJson'] ); ?></textarea>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<div class="cr-savebar">
			<button type="submit" name="cacherocket_warmer_save" value="1" class="cr-btn cr-btn--primary"><?php echo $cacherocket_is_edit ? esc_html__( 'Save warmer', 'cacherocket' ) : esc_html__( 'Create warmer', 'cacherocket' ); ?></button>
		</div>
	</form>

	<?php if ( $cacherocket_is_edit ) : ?>
		<section class="cr-card" style="margin-top:16px;">
			<header class="cr-card__header">
				<h2><?php esc_html_e( 'Danger zone', 'cacherocket' ); ?></h2>
			</header>
			<div class="cr-card__body" style="display:flex;gap:12px;flex-wrap:wrap;">
				<form method="post">
					<?php wp_nonce_field( 'cacherocket_warmer_delete' ); ?>
					<input type="hidden" name="cacherocket_warmer_id" value="<?php echo esc_attr( $cacherocket_form['crawlerId'] ); ?>" />
					<button type="submit" name="cacherocket_warmer_delete" value="1" class="cr-btn cr-btn--secondary" onclick="return confirm('<?php echo esc_js( __( 'Delete this warmer permanently?', 'cacherocket' ) ); ?>');"><?php esc_html_e( 'Delete warmer', 'cacherocket' ); ?></button>
				</form>
			</div>
		</section>
	<?php endif; ?>

<?php else : ?>

	<?php if ( $cacherocket_list_err ) : ?>
		<div class="cr-notice cr-notice--warn"><?php echo esc_html( $cacherocket_list_err ); ?></div>
	<?php elseif ( empty( $cacherocket_list ) ) : ?>
		<section class="cr-card">
			<div class="cr-card__body">
				<p class="cr-field__desc"><?php esc_html_e( 'No warmers yet. Create one here or on CacheRocket.com.', 'cacherocket' ); ?></p>
				<?php if ( ! $cacherocket_at_limit ) : ?>
					<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-warmers&action=new' ) ); ?>"><?php esc_html_e( 'Add warmer', 'cacherocket' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
	<?php else : ?>
		<?php if ( $cacherocket_at_limit ) : ?>
			<div class="cr-notice cr-notice--info">
				<?php esc_html_e( 'You have reached the warmer limit for your plan. Upgrade on CacheRocket.com to add more.', 'cacherocket' ); ?>
				<a href="<?php echo esc_url( CacheRocket_Plan::wordpress_grow_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Grow', 'cacherocket' ); ?></a>
			</div>
		<?php endif; ?>
		<section class="cr-card">
			<div class="cr-card__body" style="padding:12px;">
				<table class="cr-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'cacherocket' ); ?></th>
							<th><?php esc_html_e( 'Hostname', 'cacherocket' ); ?></th>
							<th><?php esc_html_e( 'Active', 'cacherocket' ); ?></th>
							<th><?php esc_html_e( 'Updated', 'cacherocket' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'cacherocket' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cacherocket_list as $cacherocket_row ) : ?>
							<?php
							$cacherocket_row_id   = isset( $cacherocket_row['id'] ) ? (string) $cacherocket_row['id'] : '';
							$cacherocket_row_name = isset( $cacherocket_row['name'] ) ? (string) $cacherocket_row['name'] : '';
							$cacherocket_row_host = isset( $cacherocket_row['hostName'] ) ? (string) $cacherocket_row['hostName'] : '';
							$cacherocket_row_on   = ! empty( $cacherocket_row['active'] );
							$cacherocket_row_upd  = ! empty( $cacherocket_row['updatedAt'] ) ? date_i18n( 'Y-m-d H:i', strtotime( $cacherocket_row['updatedAt'] ) ) : '';
							?>
							<tr>
								<td><?php echo esc_html( $cacherocket_row_name ); ?></td>
								<td><?php echo esc_html( $cacherocket_row_host ); ?></td>
								<td><?php echo $cacherocket_row_on ? esc_html__( 'Yes', 'cacherocket' ) : esc_html__( 'No', 'cacherocket' ); ?></td>
								<td><?php echo esc_html( $cacherocket_row_upd ); ?></td>
								<td>
									<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
										<a class="cr-btn cr-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=cacherocket-warmers&crawler_id=' . rawurlencode( $cacherocket_row_id ) ) ); ?>"><?php esc_html_e( 'Edit', 'cacherocket' ); ?></a>
										<form method="post" style="display:inline;">
											<?php wp_nonce_field( 'cacherocket_warmer_toggle' ); ?>
											<input type="hidden" name="cacherocket_warmer_id" value="<?php echo esc_attr( $cacherocket_row_id ); ?>" />
											<?php if ( $cacherocket_row_on ) : ?>
												<button type="submit" name="cacherocket_warmer_toggle" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Disable', 'cacherocket' ); ?></button>
											<?php else : ?>
												<input type="hidden" name="cacherocket_warmer_enable" value="1" />
												<button type="submit" name="cacherocket_warmer_toggle" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Enable', 'cacherocket' ); ?></button>
											<?php endif; ?>
										</form>
										<form method="post" style="display:inline;">
											<?php wp_nonce_field( 'cacherocket_warmer_lifecycle' ); ?>
											<input type="hidden" name="cacherocket_warmer_id" value="<?php echo esc_attr( $cacherocket_row_id ); ?>" />
											<button type="submit" name="cacherocket_warmer_start" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Start', 'cacherocket' ); ?></button>
											<button type="submit" name="cacherocket_warmer_stop" value="1" class="cr-btn cr-btn--secondary"><?php esc_html_e( 'Stop', 'cacherocket' ); ?></button>
										</form>
										<form method="post" style="display:inline;">
											<?php wp_nonce_field( 'cacherocket_warmer_delete' ); ?>
											<input type="hidden" name="cacherocket_warmer_id" value="<?php echo esc_attr( $cacherocket_row_id ); ?>" />
											<button type="submit" name="cacherocket_warmer_delete" value="1" class="cr-btn cr-btn--secondary" onclick="return confirm('<?php echo esc_js( __( 'Delete this warmer permanently?', 'cacherocket' ) ); ?>');"><?php esc_html_e( 'Delete', 'cacherocket' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>
<?php endif; ?>
