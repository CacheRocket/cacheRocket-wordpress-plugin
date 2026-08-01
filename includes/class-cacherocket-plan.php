<?php
/**
 * Sync and expose CacheRocket subscription plan for WordPress features.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plan / entitlement helpers.
 */
class CacheRocket_Plan {

	const TRANSIENT_KEY      = 'cacherocket_plan_data';
	const TRANSIENT_TTL      = HOUR_IN_SECONDS;
	const FREE_PLAN_ID = '1a3aeba3-6a97-11ef-81c5-6c02e04209b6';
	/** WordPress Starter €1 (billing WORDPRESS_STARTER_PLAN_ID). */
	const WORDPRESS_STARTER_PLAN_ID = '5e7e2fe7-6a97-11ef-81c5-6c02e04209b6';
	/** @deprecated alias for WORDPRESS_STARTER_PLAN_ID */
	const WORDPRESS_PLAN_ID = '5e7e2fe7-6a97-11ef-81c5-6c02e04209b6';
	/** WordPress Grow €5 (billing WORDPRESS_GROW_PLAN_ID). */
	const WORDPRESS_GROW_PLAN_ID = '6f8f30f8-6a97-11ef-81c5-6c02e04209b6';
	const LAST_ERROR_KEY = 'cacherocket_plan_sync_error';
	const WORDPRESS_PRICING_URL         = 'https://www.cacherocket.com/wordpress#wordpress-plans';
	const WORDPRESS_STARTER_UPGRADE_URL = 'https://www.cacherocket.com/account/account-subscription/upgrade?planId=5e7e2fe7-6a97-11ef-81c5-6c02e04209b6';
	const WORDPRESS_GROW_UPGRADE_URL    = 'https://www.cacherocket.com/account/account-subscription/upgrade?planId=6f8f30f8-6a97-11ef-81c5-6c02e04209b6';
	/** @deprecated alias — points at Starter checkout */
	const WORDPRESS_UPGRADE_URL = 'https://www.cacherocket.com/account/account-subscription/upgrade?planId=5e7e2fe7-6a97-11ef-81c5-6c02e04209b6';

	/**
	 * Default free plan payload (fail closed).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_plan() {
		return array(
			'planId'                   => self::FREE_PLAN_ID,
			'planName'                 => 'Free',
			'planPrice'                => 0,
			'isPaid'                   => false,
			'requiresPluginConnection' => false,
			'pluginConnected'          => false,
			'features'                 => array(
				'pluginPageCache'    => false,
				'earlyCacheDropin'   => false,
				'warmOnPublish'      => true,
				'manageWarmers'      => true,
				'cdn'                => false,
				'imageOptimization'  => false,
				'criticalCss'        => false,
				'unusedCss'          => false,
				'lqip'               => false,
				'pageSpeedScores'    => false,
			),
			'entitlements' => array(),
		);
	}

	/**
	 * Get cached or freshly synced plan data.
	 *
	 * @param bool $force Force refresh from API.
	 * @return array<string, mixed>
	 */
	public static function get_plan( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) && isset( $cached['isPaid'] ) ) {
				return $cached;
			}
		}

		return self::sync_plan();
	}

	/**
	 * Last sync error message, if any.
	 *
	 * @return string
	 */
	public static function get_last_error() {
		$error = get_option( self::LAST_ERROR_KEY, '' );
		return is_string( $error ) ? $error : '';
	}

	/**
	 * Fetch plan from CacheRocket API and store transient.
	 *
	 * @return array<string, mixed>
	 */
	public static function sync_plan() {
		$result = cacherocket_fetch_plan();

		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			$message = is_wp_error( $result )
				? $result->get_error_message()
				: __( 'Invalid plan response from CacheRocket.', 'cacherocket' );
			update_option( self::LAST_ERROR_KEY, $message, false );

			// Keep the last known good plan instead of silently flipping to Free.
			$last = get_option( 'cacherocket_last_plan', null );
			if ( is_array( $last ) && isset( $last['planName'] ) ) {
				set_transient( self::TRANSIENT_KEY, $last, self::TRANSIENT_TTL );
				return $last;
			}

			$plan = self::get_default_plan();
			set_transient( self::TRANSIENT_KEY, $plan, self::TRANSIENT_TTL );
			return $plan;
		}

		delete_option( self::LAST_ERROR_KEY );

		$plan_id    = isset( $result['planId'] ) ? (string) $result['planId'] : self::FREE_PLAN_ID;
		$plan_name  = isset( $result['planName'] ) ? (string) $result['planName'] : 'Free';
		$plan_price = isset( $result['planPrice'] ) ? (float) $result['planPrice'] : 0;
		$is_custom  = ! empty( $result['isCustom'] );
		$is_paid    = ! empty( $result['isPaid'] );

		// Any non-Free plan (including custom / private plans) unlocks paid features.
		if ( ! $is_paid && ! self::is_free_plan( $plan_id, $plan_name, $plan_price, $is_custom ) ) {
			$is_paid = true;
		}

		if ( self::is_free_plan( $plan_id, $plan_name, $plan_price, $is_custom ) ) {
			$is_paid    = false;
			$plan_price = 0;
		}

		$plan = array(
			'planId'                   => $plan_id,
			'planName'                 => $plan_name,
			'planPrice'                => $plan_price,
			'isCustom'                 => $is_custom,
			'isPaid'                   => $is_paid,
			'requiresPluginConnection' => ! empty( $result['requiresPluginConnection'] ),
			'pluginConnected'          => ! empty( $result['pluginConnected'] ),
			'features'                 => array(
				'pluginPageCache'   => $is_paid || ! empty( $result['features']['pluginPageCache'] ),
				'earlyCacheDropin'  => $is_paid || ! empty( $result['features']['earlyCacheDropin'] ),
				'warmOnPublish'     => ! empty( $result['features']['warmOnPublish'] ),
				'manageWarmers'     => ! empty( $result['features']['manageWarmers'] ),
				'cdn'               => ! empty( $result['features']['cdn'] ),
				'imageOptimization' => ! empty( $result['features']['imageOptimization'] ),
				'criticalCss'       => ! empty( $result['features']['criticalCss'] ),
				'unusedCss'         => ! empty( $result['features']['unusedCss'] ),
				'lqip'              => ! empty( $result['features']['lqip'] ),
				'pageSpeedScores'   => ! empty( $result['features']['pageSpeedScores'] ),
			),
			'entitlements' => isset( $result['entitlements'] ) && is_array( $result['entitlements'] )
				? $result['entitlements']
				: array(),
			'usage' => isset( $result['usage'] ) && is_array( $result['usage'] )
				? $result['usage']
				: array(),
		);

		if ( ! $is_paid ) {
			$plan['features']['pluginPageCache']  = false;
			$plan['features']['earlyCacheDropin'] = false;
		}

		set_transient( self::TRANSIENT_KEY, $plan, self::TRANSIENT_TTL );
		update_option( 'cacherocket_last_plan', $plan, false );

		// Connected-install heartbeat (API keys already validated by getPlan).
		if ( function_exists( 'cacherocket_send_plugin_heartbeat' ) ) {
			cacherocket_send_plugin_heartbeat( true );
		}

		return $plan;
	}

	/**
	 * Whether the payload is the Free catalog plan.
	 *
	 * @param string $plan_id    Plan id.
	 * @param string $plan_name  Plan name.
	 * @param float  $plan_price Plan price.
	 * @param bool   $is_custom  Whether the plan is marked custom.
	 * @return bool
	 */
	private static function is_free_plan( $plan_id, $plan_name, $plan_price, $is_custom = false ) {
		if ( $plan_id === self::FREE_PLAN_ID ) {
			return true;
		}
		if ( $is_custom ) {
			return false;
		}
		$name = strtolower( trim( $plan_name ) );
		return ( 'free' === $name || 0 === strpos( $name, 'free ' ) ) && $plan_price <= 0;
	}

	/**
	 * Whether the account is on a paid plan.
	 *
	 * @return bool
	 */
	public static function is_paid() {
		$plan = self::get_plan();
		return ! empty( $plan['isPaid'] );
	}

	/**
	 * Whether the synced plan is a WordPress-only catalog SKU (Starter or Grow).
	 *
	 * @return bool
	 */
	public static function is_wordpress_plan() {
		$plan = self::get_plan();
		$plan_id = isset( $plan['planId'] ) ? (string) $plan['planId'] : '';
		if ( $plan_id === self::WORDPRESS_STARTER_PLAN_ID || $plan_id === self::WORDPRESS_GROW_PLAN_ID ) {
			return true;
		}
		$name = isset( $plan['planName'] ) ? strtolower( trim( (string) $plan['planName'] ) ) : '';
		return 'wordpress' === $name || 0 === strpos( $name, 'wordpress ' );
	}

	/**
	 * Whether the account is on WordPress Starter (€1).
	 *
	 * @return bool
	 */
	public static function is_wordpress_starter_plan() {
		$plan = self::get_plan();
		$plan_id = isset( $plan['planId'] ) ? (string) $plan['planId'] : '';
		if ( $plan_id === self::WORDPRESS_STARTER_PLAN_ID ) {
			return true;
		}
		$name = isset( $plan['planName'] ) ? strtolower( trim( (string) $plan['planName'] ) ) : '';
		return 'wordpress starter' === $name || ( 0 === strpos( $name, 'wordpress' ) && false !== strpos( $name, 'starter' ) );
	}

	/**
	 * Whether the account is on WordPress Grow (€5).
	 *
	 * @return bool
	 */
	public static function is_wordpress_grow_plan() {
		$plan = self::get_plan();
		$plan_id = isset( $plan['planId'] ) ? (string) $plan['planId'] : '';
		if ( $plan_id === self::WORDPRESS_GROW_PLAN_ID ) {
			return true;
		}
		$name = isset( $plan['planName'] ) ? strtolower( trim( (string) $plan['planName'] ) ) : '';
		return 'wordpress grow' === $name || ( 0 === strpos( $name, 'wordpress' ) && false !== strpos( $name, 'grow' ) );
	}

	/**
	 * Marketing comparison for WordPress Starter / Grow.
	 *
	 * @return string
	 */
	public static function wordpress_pricing_url() {
		return self::WORDPRESS_PRICING_URL;
	}

	/**
	 * Checkout URL for WordPress Starter (€1).
	 *
	 * @return string
	 */
	public static function wordpress_upgrade_url() {
		return self::WORDPRESS_STARTER_UPGRADE_URL;
	}

	/**
	 * Checkout URL for WordPress Grow (€5).
	 *
	 * @return string
	 */
	public static function wordpress_grow_upgrade_url() {
		return self::WORDPRESS_GROW_UPGRADE_URL;
	}

	/**
	 * Whether WooCommerce / plugin page caching is allowed.
	 *
	 * Always available on Free — early drop-in remains paid.
	 *
	 * @return bool
	 */
	public static function can_cache_plugin_pages() {
		return true;
	}

	/**
	 * Whether early advanced-cache.php delivery is allowed.
	 *
	 * Always available on Free.
	 *
	 * @return bool
	 */
	public static function can_use_early_cache() {
		return true;
	}

	/**
	 * Whether warmer manage UI may call remote CRUD (always true when keys work;
	 * plan caps are enforced by the CacheRocket API).
	 *
	 * @return bool
	 */
	public static function can_manage_warmers() {
		$plan = self::get_plan();
		if ( isset( $plan['features']['manageWarmers'] ) ) {
			return ! empty( $plan['features']['manageWarmers'] );
		}
		return true;
	}

	/**
	 * Whether a named product feature is unlocked on the synced plan.
	 *
	 * @param string $feature Feature key under plan features.
	 * @return bool
	 */
	public static function can_use_feature( $feature ) {
		$plan = self::get_plan();
		return ! empty( $plan['features'][ $feature ] );
	}

	/**
	 * @return bool
	 */
	public static function can_use_cdn() {
		$ents = CacheRocket_Warmers::entitlements();
		return ! empty( $ents['allowCdn'] ) || self::can_use_feature( 'cdn' );
	}

	/**
	 * Whether managed CDN bandwidth remains for the current billing month.
	 *
	 * Uses usage from getPlan (Bunny edge egress). When exhausted or delivery
	 * is blocked, cloud-opt must stop rewriting to assets.cacherocket.com.
	 *
	 * @return bool
	 */
	public static function has_cdn_bandwidth_remaining() {
		if ( ! self::can_use_cdn() ) {
			return false;
		}

		$plan = self::get_plan();
		$usage = isset( $plan['usage'] ) && is_array( $plan['usage'] ) ? $plan['usage'] : array();
		$cdn   = isset( $usage['cdn'] ) && is_array( $usage['cdn'] ) ? $usage['cdn'] : array();

		if ( ! empty( $cdn['deliveryBlocked'] ) ) {
			return false;
		}

		if ( isset( $cdn['bandwidthGbMonth'] ) && is_array( $cdn['bandwidthGbMonth'] ) ) {
			$remaining = isset( $cdn['bandwidthGbMonth']['remaining'] )
				? (float) $cdn['bandwidthGbMonth']['remaining']
				: null;
			if ( null !== $remaining ) {
				return $remaining > 0;
			}
		}

		// No usage payload yet — allow rewrite; API gates new jobs.
		return true;
	}

	/**
	 * @return bool
	 */
	public static function can_use_image_optimization() {
		$ents = CacheRocket_Warmers::entitlements();
		return ! empty( $ents['allowImageOptimization'] ) || self::can_use_feature( 'imageOptimization' );
	}

	/**
	 * @return bool
	 */
	public static function can_use_critical_css() {
		$ents = CacheRocket_Warmers::entitlements();
		return ! empty( $ents['allowCriticalCss'] ) || self::can_use_feature( 'criticalCss' );
	}

	/**
	 * @return bool
	 */
	public static function can_use_lqip() {
		$ents = CacheRocket_Warmers::entitlements();
		return ! empty( $ents['allowLqip'] ) || self::can_use_feature( 'lqip' );
	}

	/**
	 * @return bool
	 */
	public static function can_use_page_speed_scores() {
		$ents = CacheRocket_Warmers::entitlements();
		return ! empty( $ents['allowPageSpeedScores'] ) || self::can_use_feature( 'pageSpeedScores' );
	}

	/**
	 * Clear cached plan data.
	 */
	public static function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
