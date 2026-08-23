<?php
/**
 * Per-post “Do not cache” editor control.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Store a per-post cache exclusion and honor it in the page cache.
 */
class CacheRocket_Post_Cache {

	const META_KEY      = '_cacherocket_do_not_cache';
	const OPTION_IDS    = 'cacherocket_no_cache_post_ids';
	const EDITOR_SCRIPT = 'cacherocket-editor';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_classic_metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_classic_metabox' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'sync_post' ), 20, 1 );
		add_action( 'trashed_post', array( __CLASS__, 'sync_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'sync_post' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ) );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 4 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
	}

	/**
	 * Public post types that show the control (posts, pages, products, custom types).
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);
		unset( $types['attachment'] );

		/**
		 * Filter post types that get the “Do not cache” checkbox.
		 *
		 * @param string[] $types Post type names.
		 */
		$types = apply_filters( 'cacherocket_do_not_cache_post_types', array_values( $types ) );

		return is_array( $types ) ? array_values( array_filter( array_map( 'strval', $types ) ) ) : array();
	}

	/**
	 * Register post meta for Gutenberg / REST.
	 */
	public static function register_meta() {
		foreach ( self::post_types() as $post_type ) {
			if ( ! post_type_supports( $post_type, 'custom-fields' ) ) {
				add_post_type_support( $post_type, 'custom-fields' );
			}

			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => static function ( $allowed, $meta_key, $object_id ) {
						unset( $allowed, $meta_key );
						return current_user_can( 'edit_post', (int) $object_id );
					},
				)
			);
		}
	}

	/**
	 * Whether a post is marked as excluded from the page cache.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_post_excluded( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		$value = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_bool( $value ) ) {
			return $value;
		}

		return '1' === (string) $value || 'true' === strtolower( (string) $value );
	}

	/**
	 * Whether the current front-end request is a singular excluded post.
	 *
	 * @return bool
	 */
	public static function is_current_excluded() {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$post_id = get_queried_object_id();
		return $post_id && self::is_post_excluded( $post_id );
	}

	/**
	 * Classic editor metabox (hidden in the block editor).
	 */
	public static function add_classic_metabox() {
		foreach ( self::post_types() as $post_type ) {
			add_meta_box(
				'cacherocket-do-not-cache',
				__( 'Cache Rocket', 'cache-rocket' ),
				array( __CLASS__, 'render_classic_metabox' ),
				$post_type,
				'side',
				'default',
				array(
					'__back_compat_meta_box' => true,
				)
			);
		}
	}

	/**
	 * Render the classic-editor checkbox.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_classic_metabox( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		wp_nonce_field( 'cacherocket_do_not_cache', 'cacherocket_do_not_cache_nonce' );
		$checked = self::is_post_excluded( $post->ID );
		?>
		<label for="cacherocket_do_not_cache">
			<input type="checkbox" id="cacherocket_do_not_cache" name="cacherocket_do_not_cache" value="1" <?php checked( $checked ); ?> />
			<?php esc_html_e( 'Do not cache this page', 'cache-rocket' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Exclude this URL from the Cache Rocket page cache.', 'cache-rocket' ); ?></p>
		<?php
	}

	/**
	 * Persist the classic-editor checkbox. Gutenberg saves via registered meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_classic_metabox( $post_id, $post = null ) {
		unset( $post );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['cacherocket_do_not_cache_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cacherocket_do_not_cache_nonce'] ) ), 'cacherocket_do_not_cache' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST['cacherocket_do_not_cache'] ) ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Rebuild the early-cache exclude list after a post is saved or trashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function sync_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}

		$ids      = self::stored_ids();
		$excluded = self::is_post_excluded( $post_id ) && 'publish' === $post->post_status;

		if ( $excluded ) {
			$ids[] = $post_id;
			if ( class_exists( 'CacheRocket_Cache' ) ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					CacheRocket_Cache::purge_url( $permalink );
				}
			}
		} else {
			$ids = array_diff( $ids, array( $post_id ) );
		}

		self::store_ids( $ids );
		self::write_exclude_file();
	}

	/**
	 * Drop a deleted post from the exclude list.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_deleted_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		self::store_ids( array_diff( self::stored_ids(), array( $post_id ) ) );
		self::write_exclude_file();
	}

	/**
	 * Rebuild the exclude list when our meta key changes (REST / Gutenberg).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		if ( self::META_KEY !== $meta_key ) {
			return;
		}
		self::sync_post( (int) $object_id );
	}

	/**
	 * Block-editor document panel.
	 */
	public static function enqueue_editor() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->post_type ) || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}

		$path = plugin_dir_path( CACHEROCKET_PLUGIN_FILE ) . 'admin/assets/editor.js';
		wp_enqueue_script(
			self::EDITOR_SCRIPT,
			plugins_url( 'admin/assets/editor.js', CACHEROCKET_PLUGIN_FILE ),
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			file_exists( $path ) ? (string) filemtime( $path ) : CACHEROCKET_VERSION,
			true
		);

		wp_localize_script(
			self::EDITOR_SCRIPT,
			'cacherocketEditor',
			array(
				'metaKey'     => self::META_KEY,
				'panelTitle'  => __( 'Cache Rocket', 'cache-rocket' ),
				'toggleLabel' => __( 'Do not cache this page', 'cache-rocket' ),
				'help'        => __( 'Exclude this URL from the Cache Rocket page cache.', 'cache-rocket' ),
			)
		);
	}

	/**
	 * Stored excluded post IDs.
	 *
	 * @return int[]
	 */
	private static function stored_ids() {
		$ids = get_option( self::OPTION_IDS, array() );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Persist excluded post IDs.
	 *
	 * @param int[] $ids Post IDs.
	 */
	private static function store_ids( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		update_option( self::OPTION_IDS, $ids, false );
	}

	/**
	 * Write a path list the early drop-in can read without loading WordPress.
	 */
	public static function write_exclude_file() {
		if ( ! class_exists( 'CacheRocket_Cache' ) || ! class_exists( 'CacheRocket_Filesystem' ) ) {
			return;
		}

		CacheRocket_Cache::ensure_cache_dir();
		$file  = CacheRocket_Cache::exclude_uris_file_path();
		$lines = array( '# CacheRocket per-post exclusions. Do not edit.' );

		foreach ( self::stored_ids() as $post_id ) {
			if ( ! self::is_post_excluded( $post_id ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
				continue;
			}
			$path = self::path_for_post( $post_id );
			if ( '' !== $path ) {
				$lines[] = $path;
			}
		}

		CacheRocket_Filesystem::put_cache_file( $file, implode( "\n", $lines ) . "\n" );
	}

	/**
	 * Permalink path for a post (subdirectory installs included).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function path_for_post( $post_id ) {
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		$path = wp_parse_url( $permalink, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '/';
		}

		return self::normalize_path( $path );
	}

	/**
	 * Normalize a URL path for exact matching.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$path = '/' . ltrim( str_replace( '\\', '/', (string) $path ), '/' );
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}
		return $path;
	}
}
