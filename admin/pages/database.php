<?php
/**
 * Database cleanup page.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$cacherocket_counts = CacheRocket_Database::counts();
$cacherocket_items  = array(
	'revisions'          => array( __( 'Post revisions', 'cacherocket' ), $cacherocket_counts['revisions'] ),
	'auto_drafts'        => array( __( 'Auto-drafts', 'cacherocket' ), $cacherocket_counts['auto_drafts'] ),
	'trashed_posts'      => array( __( 'Trashed posts', 'cacherocket' ), $cacherocket_counts['trashed_posts'] ),
	'spam_comments'      => array( __( 'Spam comments', 'cacherocket' ), $cacherocket_counts['spam_comments'] ),
	'trashed_comments'   => array( __( 'Trashed comments', 'cacherocket' ), $cacherocket_counts['trashed_comments'] ),
	'expired_transients' => array( __( 'Expired transients', 'cacherocket' ), $cacherocket_counts['expired_transients'] ),
	'all_transients'     => array( __( 'All transients', 'cacherocket' ), $cacherocket_counts['all_transients'] ),
	'optimize_tables'    => array( __( 'Optimize database tables', 'cacherocket' ), $cacherocket_counts['tables'] ),
);
?>
<div class="cr-main__header">
	<div>
		<h1><?php esc_html_e( 'Database', 'cacherocket' ); ?></h1>
		<p><?php esc_html_e( 'A tidy database runs more efficiently. Clean revisions, spam, and transients — or optimize tables — in a few clicks.', 'cacherocket' ); ?></p>
	</div>
</div>

<section class="cr-card">
	<header class="cr-card__header">
		<h2><?php esc_html_e( 'Cleanup', 'cacherocket' ); ?></h2>
		<p><?php esc_html_e( 'Select the items to remove, then run cleanup. This cannot be undone.', 'cacherocket' ); ?></p>
	</header>
	<form method="post">
		<?php wp_nonce_field( 'cacherocket_db_cleanup' ); ?>
		<div class="cr-checklist">
			<label>
				<strong><?php esc_html_e( 'Select all', 'cacherocket' ); ?></strong>
				<input type="checkbox" id="cr-db-select-all" />
			</label>
			<?php foreach ( $cacherocket_items as $cacherocket_key => $cacherocket_item ) : ?>
				<label>
					<span>
						<strong><?php echo esc_html( $cacherocket_item[0] ); ?></strong>
						<span> — <?php echo esc_html( (string) $cacherocket_item[1] ); ?></span>
					</span>
					<input type="checkbox" name="cacherocket_db_actions[]" value="<?php echo esc_attr( $cacherocket_key ); ?>" />
				</label>
			<?php endforeach; ?>
		</div>
		<div style="padding: 0 12px 16px;">
			<button type="submit" name="cacherocket_db_cleanup" value="1" class="cr-btn cr-btn--primary"><?php esc_html_e( 'Run cleanup', 'cacherocket' ); ?></button>
		</div>
	</form>
</section>
