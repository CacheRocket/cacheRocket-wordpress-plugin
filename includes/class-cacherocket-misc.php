<?php
/**
 * Misc front-end cleanups: emoji, embeds, jQuery Migrate, DNS prefetch.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Lightweight WP feature toggles.
 */
class CacheRocket_Misc {

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( CacheRocket_Options::get( 'remove_emoji' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
			add_filter( 'tiny_mce_plugins', array( __CLASS__, 'disable_emojis_tinymce' ) );
		}

		if ( CacheRocket_Options::get( 'disable_embeds' ) && ! is_admin() ) {
			add_action( 'init', array( __CLASS__, 'disable_embeds' ), 9999 );
		}

		if ( CacheRocket_Options::get( 'remove_jquery_migrate' ) && ! is_admin() ) {
			add_action( 'wp_default_scripts', array( __CLASS__, 'remove_jquery_migrate' ) );
		}

		if ( ! is_admin() && ( CacheRocket_Options::lines( 'dns_prefetch' ) || CacheRocket_Options::lines( 'preload_fonts' ) ) ) {
			add_action( 'wp_head', array( __CLASS__, 'print_resource_hints' ), 1 );
		}
	}

	/**
	 * Remove emoji TinyMCE plugin.
	 *
	 * @param array<int, string> $plugins Plugins.
	 * @return array<int, string>
	 */
	public static function disable_emojis_tinymce( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return array();
		}
		return array_diff( $plugins, array( 'wpemoji' ) );
	}

	/**
	 * Disable oEmbed / embeds.
	 */
	public static function disable_embeds() {
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'embed_oembed_discover', '__return_false' );
		add_filter( 'tiny_mce_plugins', array( __CLASS__, 'disable_embeds_tinymce' ) );
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'disable_embeds_rewrites' ) );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
	}

	/**
	 * @param array<int, string> $plugins Plugins.
	 * @return array<int, string>
	 */
	public static function disable_embeds_tinymce( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, array( 'wpembed' ) ) : array();
	}

	/**
	 * @param array<string, string> $rules Rules.
	 * @return array<string, string>
	 */
	public static function disable_embeds_rewrites( $rules ) {
		foreach ( $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}
		return $rules;
	}

	/**
	 * Dequeue jQuery Migrate on the front end.
	 *
	 * @param WP_Scripts $scripts Scripts.
	 */
	public static function remove_jquery_migrate( $scripts ) {
		if ( isset( $scripts->registered['jquery'] ) ) {
			$script = $scripts->registered['jquery'];
			if ( $script->deps ) {
				$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
			}
		}
	}

	/**
	 * Print dns-prefetch and font preload hints.
	 */
	public static function print_resource_hints() {
		foreach ( CacheRocket_Options::lines( 'dns_prefetch' ) as $host ) {
			$host = preg_replace( '#^https?:#i', '', $host );
			$host = '//' . ltrim( (string) $host, '/' );
			printf( "<link rel=\"dns-prefetch\" href=\"%s\" />\n", esc_url( $host ) );
		}

		foreach ( CacheRocket_Options::lines( 'preload_fonts' ) as $font_url ) {
			if ( ! preg_match( '#\.(woff2?|ttf|otf)(\?|$)#i', $font_url ) ) {
				continue;
			}
			$type = 'font/woff2';
			if ( preg_match( '#\.woff(\?|$)#i', $font_url ) ) {
				$type = 'font/woff';
			} elseif ( preg_match( '#\.ttf(\?|$)#i', $font_url ) ) {
				$type = 'font/ttf';
			} elseif ( preg_match( '#\.otf(\?|$)#i', $font_url ) ) {
				$type = 'font/otf';
			}
			printf(
				"<link rel=\"preload\" href=\"%s\" as=\"font\" type=\"%s\" crossorigin />\n",
				esc_url( $font_url ),
				esc_attr( $type )
			);
		}
	}
}
