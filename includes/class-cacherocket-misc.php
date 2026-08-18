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

		if ( ! is_admin() && CacheRocket_Options::lines( 'dns_prefetch' ) ) {
			add_filter( 'wp_resource_hints', array( __CLASS__, 'filter_dns_prefetch_hints' ), 10, 2 );
		}

		if ( ! is_admin() && CacheRocket_Options::lines( 'preload_fonts' ) ) {
			// wp_preload_resources was introduced in WordPress 6.1.
			if ( function_exists( 'wp_preload_resources' ) ) {
				add_filter( 'wp_preload_resources', array( __CLASS__, 'filter_preload_fonts' ) );
			} else {
				add_action( 'wp_head', array( __CLASS__, 'print_font_preloads' ), 1 );
			}
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
	 * Add the configured hostnames to core's dns-prefetch hints.
	 *
	 * @param array<int, mixed> $urls          Resource hint URLs.
	 * @param string            $relation_type Hint relation type.
	 * @return array<int, mixed>
	 */
	public static function filter_dns_prefetch_hints( $urls, $relation_type ) {
		if ( ! is_array( $urls ) || 'dns-prefetch' !== $relation_type ) {
			return $urls;
		}

		foreach ( CacheRocket_Options::lines( 'dns_prefetch' ) as $host ) {
			$host   = preg_replace( '#^https?:#i', '', $host );
			$host   = '//' . ltrim( (string) $host, '/' );
			$urls[] = $host;
		}

		return $urls;
	}

	/**
	 * Add the configured fonts to core's preload resources (WordPress 6.1+).
	 *
	 * @param array<int, array<string, string>> $resources Preload resources.
	 * @return array<int, array<string, string>>
	 */
	public static function filter_preload_fonts( $resources ) {
		if ( ! is_array( $resources ) ) {
			return $resources;
		}

		foreach ( self::get_font_preloads() as $font ) {
			$resources[] = array(
				'href'        => $font['url'],
				'as'          => 'font',
				'type'        => $font['type'],
				'crossorigin' => 'anonymous',
			);
		}

		return $resources;
	}

	/**
	 * Print font preload hints on WordPress versions without wp_preload_resources.
	 */
	public static function print_font_preloads() {
		foreach ( self::get_font_preloads() as $font ) {
			printf(
				"<link rel=\"preload\" href=\"%s\" as=\"font\" type=\"%s\" crossorigin />\n",
				esc_url( $font['url'] ),
				esc_attr( $font['type'] )
			);
		}
	}

	/**
	 * Resolve the configured font URLs and their MIME types.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_font_preloads() {
		$fonts = array();

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

			$fonts[] = array(
				'url'  => $font_url,
				'type' => $type,
			);
		}

		return $fonts;
	}
}
