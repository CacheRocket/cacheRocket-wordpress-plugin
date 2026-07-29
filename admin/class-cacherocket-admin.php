<?php
/**
 * CacheRocket admin UI — multi-page settings.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin menus, assets, and page rendering.
 */
class CacheRocket_Admin {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon_styles' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . CACHEROCKET_PLUGIN_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Size the custom PNG correctly in the wp-admin menu.
	 */
	public static function menu_icon_styles() {
		?>
		<style>
			#adminmenu #toplevel_page_cacherocket .wp-menu-image img {
				width: 20px;
				height: 20px;
				padding: 7px 0 0;
				opacity: 0.85;
			}
			#adminmenu #toplevel_page_cacherocket:hover .wp-menu-image img,
			#adminmenu #toplevel_page_cacherocket.wp-has-current-submenu .wp-menu-image img,
			#adminmenu #toplevel_page_cacherocket.current .wp-menu-image img {
				opacity: 1;
			}
		</style>
		<?php
	}

	/**
	 * Settings link on Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=cacherocket' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cacherocket' ) . '</a>' );
		return $links;
	}

	/**
	 * Support / docs links under the plugin on the Plugins screen.
	 *
	 * @param string[] $links Meta links.
	 * @param string   $file  Plugin basename.
	 * @return string[]
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( CACHEROCKET_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( 'https://www.cacherocket.com' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'cacherocket' ) . '</a>';
		$links[] = '<a href="' . esc_url( 'https://wordpress.org/support/plugin/cacherocket/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'cacherocket' ) . '</a>';

		return $links;
	}

	/**
	 * Admin menu + subpages.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'CacheRocket', 'cacherocket' ),
			__( 'CacheRocket', 'cacherocket' ),
			'manage_options',
			'cacherocket',
			array( __CLASS__, 'render_page' ),
			plugins_url( 'assets/cacherocket-logo.png', CACHEROCKET_PLUGIN_FILE ),
			58
		);

		foreach ( self::pages() as $slug => $page ) {
			$menu_slug = ( 'dashboard' === $slug ) ? 'cacherocket' : 'cacherocket-' . $slug;
			add_submenu_page(
				'cacherocket',
				$page['title'],
				$page['label'],
				'manage_options',
				$menu_slug,
				array( __CLASS__, 'render_page' )
			);
		}
	}

	/**
	 * Page definitions.
	 *
	 * @return array<string, array{label:string,title:string,icon:string}>
	 */
	public static function pages() {
		return array(
			'dashboard'         => array(
				'label' => __( 'Dashboard', 'cacherocket' ),
				'title' => __( 'Dashboard', 'cacherocket' ),
				'icon'  => 'dashicons-dashboard',
			),
			'cache'             => array(
				'label' => __( 'Cache', 'cacherocket' ),
				'title' => __( 'Cache', 'cacherocket' ),
				'icon'  => 'dashicons-database',
			),
			'file-optimization' => array(
				'label' => __( 'File Optimization', 'cacherocket' ),
				'title' => __( 'File Optimization', 'cacherocket' ),
				'icon'  => 'dashicons-editor-code',
			),
			'media'             => array(
				'label' => __( 'Media', 'cacherocket' ),
				'title' => __( 'Media', 'cacherocket' ),
				'icon'  => 'dashicons-format-image',
			),
			'preload'           => array(
				'label' => __( 'Preload', 'cacherocket' ),
				'title' => __( 'Preload', 'cacherocket' ),
				'icon'  => 'dashicons-update',
			),
			'warmers'           => array(
				'label' => __( 'Cache Warmers', 'cacherocket' ),
				'title' => __( 'Cache Warmers', 'cacherocket' ),
				'icon'  => 'dashicons-admin-site-alt3',
			),
			'advanced'          => array(
				'label' => __( 'Advanced', 'cacherocket' ),
				'title' => __( 'Advanced', 'cacherocket' ),
				'icon'  => 'dashicons-admin-generic',
			),
			'database'          => array(
				'label' => __( 'Database', 'cacherocket' ),
				'title' => __( 'Database', 'cacherocket' ),
				'icon'  => 'dashicons-list-view',
			),
			'account'           => array(
				'label' => __( 'Account', 'cacherocket' ),
				'title' => __( 'Account', 'cacherocket' ),
				'icon'  => 'dashicons-admin-users',
			),
		);
	}

	/**
	 * Current section slug.
	 *
	 * @return string
	 */
	public static function current_section() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'cacherocket'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'cacherocket' === $page ) {
			return 'dashboard';
		}
		if ( 0 === strpos( $page, 'cacherocket-' ) ) {
			$section = substr( $page, strlen( 'cacherocket-' ) );
			$pages   = self::pages();
			if ( isset( $pages[ $section ] ) ) {
				return $section;
			}
		}
		return 'dashboard';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( $hook, 'cacherocket' ) ) {
			return;
		}
		wp_enqueue_style(
			'cacherocket-admin',
			plugins_url( 'admin/assets/admin.css', CACHEROCKET_PLUGIN_FILE ),
			array(),
			CACHEROCKET_VERSION
		);
		wp_enqueue_script(
			'cacherocket-admin',
			plugins_url( 'admin/assets/admin.js', CACHEROCKET_PLUGIN_FILE ),
			array(),
			CACHEROCKET_VERSION,
			true
		);
	}

	/**
	 * Register settings API fields.
	 */
	public static function register_settings() {
		register_setting(
			'cacherocket_settings_group',
			CacheRocket_Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'CacheRocket_Options', 'sanitize' ),
				'default'           => CacheRocket_Options::defaults(),
			)
		);

		register_setting(
			'cacherocket_account_group',
			'cacherocket_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'cacherocket_account_group',
			'cacherocket_api_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Handle clear cache / DB cleanup / preload actions.
	 */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['cacherocket_clear_cache'] ) ) {
			check_admin_referer( 'cacherocket_clear_cache' );
			CacheRocket_Cache::purge_all();
			add_settings_error( 'cacherocket_messages', 'cache_cleared', __( 'Page cache cleared.', 'cacherocket' ), 'success' );
		}

		if ( isset( $_POST['cacherocket_db_cleanup'] ) ) {
			check_admin_referer( 'cacherocket_db_cleanup' );
			$actions = isset( $_POST['cacherocket_db_actions'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['cacherocket_db_actions'] ) ) : array();
			$results = CacheRocket_Database::cleanup( $actions );
			$total   = array_sum( $results );
			add_settings_error(
				'cacherocket_messages',
				'db_cleaned',
				sprintf(
					/* translators: %d: number of items cleaned */
					__( 'Database cleanup finished. Processed %d item(s).', 'cacherocket' ),
					(int) $total
				),
				'success'
			);
		}

		if ( isset( $_POST['cacherocket_trigger_warm'] ) ) {
			check_admin_referer( 'cacherocket_trigger_warm' );
			$urls = array( home_url( '/' ) );
			if ( ! empty( $_POST['cacherocket_warm_url'] ) ) {
				$extra = esc_url_raw( wp_unslash( $_POST['cacherocket_warm_url'] ) );
				if ( $extra ) {
					$urls[] = $extra;
				}
			}
			$result = cacherocket_warm_urls( $urls );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'cacherocket_messages', 'warm_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'cacherocket_messages', 'warm_ok', __( 'Cache warm requested for selected URLs.', 'cacherocket' ), 'success' );
			}
		}

		if ( isset( $_POST['cacherocket_sync_plan'] ) ) {
			check_admin_referer( 'cacherocket_sync_plan' );
			CacheRocket_Plan::clear_cache();
			$plan = CacheRocket_Plan::sync_plan();
			$error = CacheRocket_Plan::get_last_error();
			if ( $error ) {
				add_settings_error(
					'cacherocket_messages',
					'plan_sync_error',
					sprintf(
						/* translators: %s: error message */
						__( 'Plan refresh failed: %s', 'cacherocket' ),
						$error
					),
					'error'
				);
			} else {
				add_settings_error(
					'cacherocket_messages',
					'plan_synced',
					sprintf(
						/* translators: %s: plan name */
						__( 'Plan status refreshed: %s', 'cacherocket' ),
						isset( $plan['planName'] ) ? (string) $plan['planName'] : 'Free'
					),
					'success'
				);
			}
		}

		self::handle_warmer_actions();
	}

	/**
	 * Create / update / delete / start / stop warmers via CacheRocket API.
	 */
	private static function handle_warmer_actions() {
		if ( isset( $_POST['cacherocket_warmer_save'] ) ) {
			check_admin_referer( 'cacherocket_warmer_save' );
			$post      = wp_unslash( $_POST );
			$is_update = ! empty( $post['cacherocket_warmer_id'] );
			$payload   = CacheRocket_Warmers::payload_from_post( $post, $is_update );
			if ( is_wp_error( $payload ) ) {
				add_settings_error( 'cacherocket_messages', 'warmer_payload', $payload->get_error_message(), 'error' );
				return;
			}
			if ( $is_update ) {
				$payload = CacheRocket_Warmers::attach_existing_entry_ids( $payload );
			}
			$result = $is_update ? cacherocket_crawler_update( $payload ) : cacherocket_crawler_create( $payload );
			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				$data    = $result->get_error_data();
				if ( is_array( $data ) && ! empty( $data['unverifiedHostnames'] ) ) {
					$message .= ' ' . sprintf(
						/* translators: %s: hostnames */
						__( 'Unverified hostnames: %s', 'cacherocket' ),
						implode( ', ', array_map( 'sanitize_text_field', (array) $data['unverifiedHostnames'] ) )
					);
				}
				add_settings_error( 'cacherocket_messages', 'warmer_save_error', $message, 'error' );
				return;
			}
			add_settings_error(
				'cacherocket_messages',
				'warmer_saved',
				$is_update ? __( 'Warmer updated.', 'cacherocket' ) : __( 'Warmer created.', 'cacherocket' ),
				'success'
			);
			$crawler_id = '';
			if ( ! empty( $result['crawler']['id'] ) ) {
				$crawler_id = (string) $result['crawler']['id'];
			} elseif ( $is_update && ! empty( $payload['crawlerId'] ) ) {
				$crawler_id = (string) $payload['crawlerId'];
			}
			if ( $crawler_id ) {
				wp_safe_redirect( admin_url( 'admin.php?page=cacherocket-warmers&crawler_id=' . rawurlencode( $crawler_id ) . '&updated=1' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=cacherocket-warmers&updated=1' ) );
			exit;
		}

		if ( isset( $_POST['cacherocket_warmer_delete'] ) ) {
			check_admin_referer( 'cacherocket_warmer_delete' );
			$id = isset( $_POST['cacherocket_warmer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cacherocket_warmer_id'] ) ) : '';
			if ( ! $id ) {
				add_settings_error( 'cacherocket_messages', 'warmer_delete_missing', __( 'Warmer id is missing.', 'cacherocket' ), 'error' );
				return;
			}
			$result = cacherocket_crawler_delete( $id );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'cacherocket_messages', 'warmer_delete_error', $result->get_error_message(), 'error' );
				return;
			}
			add_settings_error( 'cacherocket_messages', 'warmer_deleted', __( 'Warmer deleted.', 'cacherocket' ), 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=cacherocket-warmers&deleted=1' ) );
			exit;
		}

		if ( isset( $_POST['cacherocket_warmer_toggle'] ) ) {
			check_admin_referer( 'cacherocket_warmer_toggle' );
			$id     = isset( $_POST['cacherocket_warmer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cacherocket_warmer_id'] ) ) : '';
			$enable = ! empty( $_POST['cacherocket_warmer_enable'] );
			if ( ! $id ) {
				add_settings_error( 'cacherocket_messages', 'warmer_toggle_missing', __( 'Warmer id is missing.', 'cacherocket' ), 'error' );
				return;
			}
			$result = cacherocket_crawler_update(
				array(
					'crawlerId'   => $id,
					'active'      => $enable,
					'stopRequest' => ! $enable,
				)
			);
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'cacherocket_messages', 'warmer_toggle_error', $result->get_error_message(), 'error' );
				return;
			}
			add_settings_error(
				'cacherocket_messages',
				'warmer_toggled',
				$enable ? __( 'Warmer enabled.', 'cacherocket' ) : __( 'Warmer disabled.', 'cacherocket' ),
				'success'
			);
		}

		if ( isset( $_POST['cacherocket_warmer_start'] ) ) {
			check_admin_referer( 'cacherocket_warmer_lifecycle' );
			$id = isset( $_POST['cacherocket_warmer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cacherocket_warmer_id'] ) ) : '';
			$result = $id ? cacherocket_crawler_start( $id ) : new WP_Error( 'missing', __( 'Warmer id is missing.', 'cacherocket' ) );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'cacherocket_messages', 'warmer_start_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'cacherocket_messages', 'warmer_started', __( 'Start requested.', 'cacherocket' ), 'success' );
			}
		}

		if ( isset( $_POST['cacherocket_warmer_stop'] ) ) {
			check_admin_referer( 'cacherocket_warmer_lifecycle' );
			$id = isset( $_POST['cacherocket_warmer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cacherocket_warmer_id'] ) ) : '';
			$result = $id ? cacherocket_crawler_stop( $id ) : new WP_Error( 'missing', __( 'Warmer id is missing.', 'cacherocket' ) );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'cacherocket_messages', 'warmer_stop_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'cacherocket_messages', 'warmer_stopped', __( 'Stop requested.', 'cacherocket' ), 'success' );
			}
		}
	}

	/**
	 * After settings save: sync drop-in, htaccess, legacy options.
	 */
	public static function after_settings_saved() {
		$settings = CacheRocket_Options::all();

		update_option( CacheRocket_Cache::OPTION_ENABLED, ! empty( $settings['cache_enabled'] ) );
		update_option( CacheRocket_Cache::OPTION_DELIVERY, $settings['cache_delivery'] );
		update_option( CacheRocket_Cache::OPTION_WOOCOMMERCE, ! empty( $settings['cache_woocommerce'] ) );
		update_option( CacheRocket_Cache::OPTION_TTL, (int) $settings['cache_ttl'] );
		update_option( 'cacherocket_warm_on_publish', ! empty( $settings['warm_on_publish'] ) );

		$result = CacheRocket_Dropin::sync();
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'cacherocket_messages', 'dropin_error', $result->get_error_message(), 'error' );
		}

		$ht = CacheRocket_Htaccess::sync();
		if ( is_wp_error( $ht ) ) {
			add_settings_error( 'cacherocket_messages', 'htaccess_error', $ht->get_error_message(), 'error' );
		}

		CacheRocket_Cache::purge_all();
	}

	/**
	 * After API credentials change: refresh plan and drop-in entitlements.
	 */
	public static function after_account_saved() {
		CacheRocket_Plan::clear_cache();
		CacheRocket_Plan::sync_plan();
		$result = CacheRocket_Dropin::sync();
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'cacherocket_messages', 'dropin_error', $result->get_error_message(), 'error' );
		}
	}

	/**
	 * Render the active admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$section = self::current_section();
		$pages   = self::pages();
		$file    = CACHEROCKET_PLUGIN_DIR . 'admin/pages/' . $section . '.php';
		if ( ! is_readable( $file ) ) {
			$file    = CACHEROCKET_PLUGIN_DIR . 'admin/pages/dashboard.php';
			$section = 'dashboard';
		}

		$plan      = CacheRocket_Plan::get_plan();
		$settings  = CacheRocket_Options::all();
		$conflicts = CacheRocket_Compatibility::get_conflicting_plugins();
		$entries   = CacheRocket_Cache::count_entries();

		include CACHEROCKET_PLUGIN_DIR . 'admin/views/layout.php';
	}

	/**
	 * Render a toggle field row.
	 *
	 * @param string               $name        Option key inside settings array.
	 * @param string               $label       Label.
	 * @param string               $description Description.
	 * @param array<string, mixed> $args        Extra args (disabled, badge, checked override).
	 */
	public static function toggle( $name, $label, $description = '', $args = array() ) {
		$settings = CacheRocket_Options::all();
		$checked  = isset( $args['checked'] ) ? (bool) $args['checked'] : ! empty( $settings[ $name ] );
		$disabled = ! empty( $args['disabled'] );
		$badge    = isset( $args['badge'] ) ? (string) $args['badge'] : '';
		?>
		<div class="cr-field cr-field--toggle <?php echo $disabled ? 'is-disabled' : ''; ?>">
			<div class="cr-field__text">
				<div class="cr-field__label">
					<?php echo esc_html( $label ); ?>
					<?php if ( $badge ) : ?>
						<span class="cr-badge"><?php echo esc_html( $badge ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $description ) : ?>
					<p class="cr-field__desc"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
				<?php
				if ( ! empty( $args['extra_html'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller supplies escaped HTML.
					echo $args['extra_html'];
				}
				?>
			</div>
			<label class="cr-switch">
				<input type="hidden" name="<?php echo esc_attr( CacheRocket_Options::OPTION_KEY . '[' . $name . ']' ); ?>" value="0" />
				<input type="checkbox" name="<?php echo esc_attr( CacheRocket_Options::OPTION_KEY . '[' . $name . ']' ); ?>" value="1" <?php checked( $checked ); disabled( $disabled ); ?> />
				<span class="cr-switch__slider"></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Textarea field.
	 *
	 * @param string $name        Key.
	 * @param string $label       Label.
	 * @param string $description Description.
	 * @param string $placeholder Placeholder.
	 */
	public static function textarea( $name, $label, $description = '', $placeholder = '' ) {
		$settings = CacheRocket_Options::all();
		$value    = isset( $settings[ $name ] ) ? (string) $settings[ $name ] : '';
		?>
		<div class="cr-field cr-field--stack">
			<label class="cr-field__label" for="cr-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<?php if ( $description ) : ?>
				<p class="cr-field__desc"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<textarea class="cr-textarea" id="cr-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( CacheRocket_Options::OPTION_KEY . '[' . $name . ']' ); ?>" rows="5" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Number / select / text input row.
	 *
	 * @param string               $name  Key.
	 * @param string               $label Label.
	 * @param string               $desc  Description.
	 * @param array<string, mixed> $args  type, options, min, max, disabled.
	 */
	public static function input( $name, $label, $desc = '', $args = array() ) {
		$settings = CacheRocket_Options::all();
		$value    = isset( $settings[ $name ] ) ? $settings[ $name ] : '';
		$type     = isset( $args['type'] ) ? $args['type'] : 'text';
		$disabled = ! empty( $args['disabled'] );
		?>
		<div class="cr-field cr-field--stack">
			<label class="cr-field__label" for="cr-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<?php if ( $desc ) : ?>
				<p class="cr-field__desc"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
			<?php if ( 'select' === $type && ! empty( $args['options'] ) && is_array( $args['options'] ) ) : ?>
				<select id="cr-<?php echo esc_attr( $name ); ?>" class="cr-select" name="<?php echo esc_attr( CacheRocket_Options::OPTION_KEY . '[' . $name . ']' ); ?>" <?php disabled( $disabled ); ?>>
					<?php foreach ( $args['options'] as $opt_value => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( (string) $value, (string) $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input
					type="<?php echo esc_attr( $type ); ?>"
					id="cr-<?php echo esc_attr( $name ); ?>"
					class="cr-input"
					name="<?php echo esc_attr( CacheRocket_Options::OPTION_KEY . '[' . $name . ']' ); ?>"
					value="<?php echo esc_attr( (string) $value ); ?>"
					<?php
					if ( isset( $args['min'] ) ) {
						echo ' min="' . esc_attr( (string) $args['min'] ) . '"';
					}
					if ( isset( $args['max'] ) ) {
						echo ' max="' . esc_attr( (string) $args['max'] ) . '"';
					}
					disabled( $disabled );
					?>
				/>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Section card opener.
	 *
	 * @param string $title Title.
	 * @param string $desc  Description.
	 */
	public static function section_start( $title, $desc = '' ) {
		echo '<section class="cr-card">';
		echo '<header class="cr-card__header"><h2>' . esc_html( $title ) . '</h2>';
		if ( $desc ) {
			echo '<p>' . esc_html( $desc ) . '</p>';
		}
		echo '</header><div class="cr-card__body">';
	}

	/**
	 * Section card closer.
	 */
	public static function section_end() {
		echo '</div></section>';
	}
}

add_action( 'update_option_' . CacheRocket_Options::OPTION_KEY, array( 'CacheRocket_Admin', 'after_settings_saved' ), 10, 0 );
add_action( 'update_option_cacherocket_api_key', array( 'CacheRocket_Admin', 'after_account_saved' ), 10, 0 );
add_action( 'update_option_cacherocket_api_secret', array( 'CacheRocket_Admin', 'after_account_saved' ), 10, 0 );
