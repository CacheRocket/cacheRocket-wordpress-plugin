<?php
/**
 * Database cleanup utilities.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Lean database maintenance tools.
 */
class CacheRocket_Database {

	const CRON_HOOK = 'cacherocket_db_scheduled_cleanup';

	/**
	 * Register schedule hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled' ) );
		self::sync_schedule();
	}

	/**
	 * Create / update / clear the scheduled cleanup event.
	 */
	public static function sync_schedule() {
		$enabled = (bool) CacheRocket_Options::get( 'db_schedule' );
		$freq    = (string) CacheRocket_Options::get( 'db_schedule_frequency', 'weekly' );
		if ( ! in_array( $freq, array( 'daily', 'weekly' ), true ) ) {
			$freq = 'weekly';
		}

		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $enabled ) {
			while ( $next ) {
				wp_unschedule_event( $next, self::CRON_HOOK );
				$next = wp_next_scheduled( self::CRON_HOOK );
			}
			return;
		}

		if ( $next ) {
			$current = wp_get_schedule( self::CRON_HOOK );
			if ( $current === $freq ) {
				return;
			}
			while ( $next ) {
				wp_unschedule_event( $next, self::CRON_HOOK );
				$next = wp_next_scheduled( self::CRON_HOOK );
			}
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, $freq, self::CRON_HOOK );
	}

	/**
	 * Cron callback.
	 */
	public static function run_scheduled() {
		$actions = CacheRocket_Options::lines( 'db_schedule_actions' );
		if ( empty( $actions ) ) {
			return;
		}
		self::cleanup( $actions );
	}

	/**
	 * Count items available for cleanup.
	 *
	 * @return array<string, int>
	 */
	public static function counts() {
		global $wpdb;

		$revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$auto      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$trashed   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$spam      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$trashed_c = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", '_transient_timeout_%', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_trans = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_site_transient_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$tables      = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_count = is_array( $tables ) ? count( $tables ) : 0;

		return array(
			'revisions'          => $revisions,
			'auto_drafts'        => $auto,
			'trashed_posts'      => $trashed,
			'spam_comments'      => $spam,
			'trashed_comments'   => $trashed_c,
			'expired_transients' => $expired,
			'all_transients'     => $all_trans,
			'tables'             => $table_count,
		);
	}

	/**
	 * Run selected cleanup actions.
	 *
	 * @param string[] $actions Action keys.
	 * @return array<string, int> Counts cleaned per action.
	 */
	public static function cleanup( $actions ) {
		global $wpdb;
		$results = array();

		if ( in_array( 'revisions', $actions, true ) ) {
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$n   = 0;
			foreach ( (array) $ids as $id ) {
				wp_delete_post_revision( (int) $id );
				++$n;
			}
			$results['revisions'] = $n;
		}

		if ( in_array( 'auto_drafts', $actions, true ) ) {
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$n   = 0;
			foreach ( (array) $ids as $id ) {
				wp_delete_post( (int) $id, true );
				++$n;
			}
			$results['auto_drafts'] = $n;
		}

		if ( in_array( 'trashed_posts', $actions, true ) ) {
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$n   = 0;
			foreach ( (array) $ids as $id ) {
				wp_delete_post( (int) $id, true );
				++$n;
			}
			$results['trashed_posts'] = $n;
		}

		if ( in_array( 'spam_comments', $actions, true ) ) {
			$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$n   = 0;
			foreach ( (array) $ids as $id ) {
				wp_delete_comment( (int) $id, true );
				++$n;
			}
			$results['spam_comments'] = $n;
		}

		if ( in_array( 'trashed_comments', $actions, true ) ) {
			$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$n   = 0;
			foreach ( (array) $ids as $id ) {
				wp_delete_comment( (int) $id, true );
				++$n;
			}
			$results['trashed_comments'] = $n;
		}

		if ( in_array( 'expired_transients', $actions, true ) ) {
			$results['expired_transients'] = self::delete_expired_transients();
		}

		if ( in_array( 'all_transients', $actions, true ) ) {
			$n = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_site_transient_' ) . '%', $wpdb->esc_like( '_transient_timeout_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$results['all_transients'] = (int) $n;
		}

		if ( in_array( 'optimize_tables', $actions, true ) ) {
			$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$n      = 0;
			foreach ( (array) $tables as $table ) {
				// Table identifiers cannot use value placeholders; names come from SHOW TABLES.
				$safe_table = esc_sql( $table );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "OPTIMIZE TABLE `{$safe_table}`" );
				++$n;
			}
			$results['optimize_tables'] = $n;
		}

		return $results;
	}

	/**
	 * Delete expired transients.
	 *
	 * @return int
	 */
	private static function delete_expired_transients() {
		global $wpdb;
		$timeouts = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$n        = 0;
		foreach ( (array) $timeouts as $timeout_name ) {
			$key = str_replace( '_transient_timeout_', '', $timeout_name );
			delete_option( '_transient_' . $key );
			delete_option( $timeout_name );
			++$n;
		}
		return $n;
	}
}
