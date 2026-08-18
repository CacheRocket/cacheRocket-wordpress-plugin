<?php
/**
 * WP-CLI commands for CacheRocket cloud image optimization.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * Manage CacheRocket cloud image optimization from the command line.
 */
class CacheRocket_CLI {

	/**
	 * Register the command with WP-CLI.
	 */
	public static function register() {
		WP_CLI::add_command( 'cacherocket image', __CLASS__ );
	}

	/**
	 * Queue images for cloud optimization.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Queue every unoptimized image in the media library (in batches).
	 *
	 * [--id=<id>]
	 * : Queue a single attachment by ID.
	 *
	 * [--batch=<number>]
	 * : How many attachments to process per pass when using --all. Default 20.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cacherocket image optimize --all
	 *     wp cacherocket image optimize --id=123
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function optimize( $args, $assoc_args ) {
		if ( ! CacheRocket_Plan::can_use_image_optimization() ) {
			WP_CLI::error( 'Image optimization is not included in your plan.' );
		}

		$id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
		if ( $id > 0 ) {
			delete_transient( 'cacherocket_opt_q_' . (int) get_option( CacheRocket_Cloud_Opt::OPTION_LOCK_GEN, 0 ) . '_' . $id );
			$queued = CacheRocket_Cloud_Opt::ensure_queued( $id );
			if ( $queued ) {
				WP_CLI::success( sprintf( 'Queued attachment %d for optimization.', $id ) );
			} else {
				WP_CLI::log( sprintf( 'Attachment %d is already optimized or was not queued.', $id ) );
			}
			return;
		}

		if ( empty( $assoc_args['all'] ) ) {
			WP_CLI::error( 'Pass --all to queue the whole library or --id=<id> for one attachment.' );
		}

		$batch  = isset( $assoc_args['batch'] ) ? max( 1, (int) $assoc_args['batch'] ) : 20;
		$offset = 0;
		$total_queued = 0;

		while ( true ) {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					'posts_per_page' => $batch,
					'offset'         => $offset,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => CacheRocket_Cloud_Opt::META_IMAGE,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);

			if ( empty( $ids ) || ! is_array( $ids ) ) {
				break;
			}

			foreach ( $ids as $attachment_id ) {
				if ( ! CacheRocket_Plan::has_image_storage_remaining() ) {
					WP_CLI::warning( 'Image storage limit reached for your plan. Stopping.' );
					break 2;
				}
				if ( CacheRocket_Cloud_Opt::ensure_queued( (int) $attachment_id ) ) {
					$total_queued++;
				}
			}

			// Optimized meta is written asynchronously when jobs finish, so paginate by
			// offset rather than relying on the NOT EXISTS filter to shrink the set.
			$offset += count( $ids );
			WP_CLI::log( sprintf( 'Scanned %d image(s), queued %d so far…', $offset, $total_queued ) );

			if ( count( $ids ) < $batch ) {
				break;
			}
		}

		WP_CLI::success( sprintf( 'Queued %d image(s) for optimization. Jobs complete in the background.', $total_queued ) );
	}

	/**
	 * Show library optimization + storage status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cacherocket image status
	 */
	public function status() {
		$stats   = CacheRocket_Cloud_Opt::library_stats();
		$storage = CacheRocket_Plan::image_storage_usage();

		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'metric' => 'Images total', 'value' => (string) $stats['total'] ),
				array( 'metric' => 'Optimized', 'value' => (string) $stats['optimized'] ),
				array( 'metric' => 'Pending jobs', 'value' => (string) $stats['pending'] ),
				array( 'metric' => 'Storage used (GB)', 'value' => number_format( $storage['usedGb'], 2 ) ),
				array( 'metric' => 'Storage limit (GB)', 'value' => number_format( $storage['limitGb'], 0 ) ),
			),
			array( 'metric', 'value' )
		);
	}

	/**
	 * Restore an attachment to its original (drop optimized variants).
	 *
	 * ## OPTIONS
	 *
	 * --id=<id>
	 * : Attachment ID to restore.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cacherocket image restore --id=123
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function restore( $args, $assoc_args ) {
		$id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
		if ( $id <= 0 ) {
			WP_CLI::error( 'Pass --id=<id> for the attachment to restore.' );
		}
		if ( CacheRocket_Cloud_Opt::restore_attachment( $id ) ) {
			WP_CLI::success( sprintf( 'Restored attachment %d to its original.', $id ) );
		} else {
			WP_CLI::error( sprintf( 'Could not restore attachment %d.', $id ) );
		}
	}
}

CacheRocket_CLI::register();
