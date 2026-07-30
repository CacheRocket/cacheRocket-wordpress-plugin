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

	const TRANSIENT_KEY   = 'cacherocket_plan_data';
	const TRANSIENT_TTL   = HOUR_IN_SECONDS;
	const FREE_PLAN_ID    = '1a3aeba3-6a97-11ef-81c5-6c02e04209b6';
	const LAST_ERROR_KEY  = 'cacherocket_plan_sync_error';

	/**
	 * Default free plan payload (fail closed).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_plan() {
		return array(
			'planId'    => self::FREE_PLAN_ID,
			'planName'  => 'Free',
			'planPrice' => 0,
			'isPaid'    => false,
			'features'  => array(
				'pluginPageCache'  => false,
				'earlyCacheDropin' => false,
				'warmOnPublish'    => true,
				'manageWarmers'    => true,
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
			'planId'    => $plan_id,
			'planName'  => $plan_name,
			'planPrice' => $plan_price,
			'isCustom'  => $is_custom,
			'isPaid'    => $is_paid,
			'features'  => array(
				'pluginPageCache'  => $is_paid || ! empty( $result['features']['pluginPageCache'] ),
				'earlyCacheDropin' => $is_paid || ! empty( $result['features']['earlyCacheDropin'] ),
				'warmOnPublish'    => ! empty( $result['features']['warmOnPublish'] ),
				'manageWarmers'    => ! empty( $result['features']['manageWarmers'] ),
			),
			'entitlements' => isset( $result['entitlements'] ) && is_array( $result['entitlements'] )
				? $result['entitlements']
				: array(),
		);

		if ( ! $is_paid ) {
			$plan['features']['pluginPageCache']  = false;
			$plan['features']['earlyCacheDropin'] = false;
		}

		set_transient( self::TRANSIENT_KEY, $plan, self::TRANSIENT_TTL );
		update_option( 'cacherocket_last_plan', $plan, false );

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
	 * Clear cached plan data.
	 */
	public static function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
