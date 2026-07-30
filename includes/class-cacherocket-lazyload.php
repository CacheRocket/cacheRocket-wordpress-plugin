<?php
/**
 * Lazy-load images, iframes, YouTube facade, picture, and CSS backgrounds.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Media lazy-loading for CacheRocket.
 */
class CacheRocket_Lazyload {

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$images  = (bool) CacheRocket_Options::get( 'lazyload' );
		$iframes = (bool) CacheRocket_Options::get( 'lazyload_iframes' );
		$youtube = (bool) CacheRocket_Options::get( 'lazyload_youtube' );
		$dims    = (bool) CacheRocket_Options::get( 'image_dimensions' );
		$css_bg  = (bool) CacheRocket_Options::get( 'lazyload_css_bg' );

		if ( ! $images && ! $iframes && ! $youtube && ! $dims && ! $css_bg ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 2 );

		if ( $youtube || $css_bg ) {
			add_action( 'wp_footer', array( __CLASS__, 'print_loader_script' ), 40 );
			add_action( 'wp_head', array( __CLASS__, 'print_youtube_css' ), 5 );
		}
	}

	/**
	 * YouTube facade styles.
	 */
	public static function print_youtube_css() {
		if ( ! CacheRocket_Options::get( 'lazyload_youtube' ) ) {
			return;
		}
		?>
		<style id="cacherocket-youtube-facade">
		.cacherocket-yt{position:relative;display:block;width:100%;max-width:100%;aspect-ratio:16/9;background:#000;cursor:pointer;overflow:hidden}
		.cacherocket-yt img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
		.cacherocket-yt button{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:68px;height:48px;border:0;background:transparent;cursor:pointer;padding:0}
		.cacherocket-yt button:before{content:"";display:block;width:68px;height:48px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 68 48'%3E%3Cpath fill='%23f00' d='M66.5 7.7c-.8-2.9-2.5-5.4-5.4-6.2C55.8.1 34 0 34 0S12.2.1 6.9 1.5C4 2.3 2.3 4.8 1.5 7.7 0 13.1 0 24 0 24s0 10.9 1.5 16.3c.8 2.9 2.5 5.4 5.4 6.2C12.2 47.9 34 48 34 48s21.8-.1 27.1-1.5c2.9-.8 4.6-3.3 5.4-6.2C68 34.9 68 24 68 24s0-10.9-1.5-16.3z'/%3E%3Cpath fill='%23fff' d='M45 24L27 14v20'/%3E%3C/svg%3E") center/contain no-repeat}
		</style>
		<?php
	}

	/**
	 * Client loader for YouTube facade + CSS background lazy.
	 */
	public static function print_loader_script() {
		$youtube = (bool) CacheRocket_Options::get( 'lazyload_youtube' );
		$css_bg  = (bool) CacheRocket_Options::get( 'lazyload_css_bg' );
		?>
		<script id="cacherocket-lazy-extras">
		(function(){
			<?php if ( $youtube ) : ?>
			document.addEventListener('click',function(e){
				var wrap=e.target&&e.target.closest?e.target.closest('.cacherocket-yt'):null;
				if(!wrap||wrap.getAttribute('data-loaded'))return;
				var id=wrap.getAttribute('data-id');
				if(!id)return;
				wrap.setAttribute('data-loaded','1');
				var src='https://www.youtube.com/embed/'+id+'?autoplay=1';
				var nocookie=wrap.getAttribute('data-nocookie');
				if(nocookie==='1')src='https://www.youtube-nocookie.com/embed/'+id+'?autoplay=1';
				var iframe=document.createElement('iframe');
				iframe.src=src;
				iframe.title=wrap.getAttribute('data-title')||'YouTube';
				iframe.allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
				iframe.allowFullscreen=true;
				iframe.style.cssText='position:absolute;inset:0;width:100%;height:100%;border:0';
				wrap.innerHTML='';
				wrap.appendChild(iframe);
			});
			<?php endif; ?>
			<?php if ( $css_bg ) : ?>
			function loadBg(el){
				var bg=el.getAttribute('data-cacherocket-bg');
				if(!bg)return;
				el.style.backgroundImage=bg;
				el.removeAttribute('data-cacherocket-bg');
			}
			if('IntersectionObserver' in window){
				var io=new IntersectionObserver(function(entries){
					entries.forEach(function(entry){
						if(!entry.isIntersecting)return;
						loadBg(entry.target);
						io.unobserve(entry.target);
					});
				},{rootMargin:'200px 0px'});
				document.querySelectorAll('[data-cacherocket-bg]').forEach(function(el){io.observe(el);});
			}else{
				document.querySelectorAll('[data-cacherocket-bg]').forEach(loadBg);
			}
			<?php endif; ?>
		})();
		</script>
		<?php
	}

	/**
	 * Start HTML buffer.
	 */
	public static function start_buffer() {
		if ( is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( __CLASS__, 'process_html' ) );
	}

	/**
	 * Transform images / iframes / backgrounds in HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		if ( CacheRocket_Options::get( 'lazyload' ) ) {
			$html = preg_replace_callback(
				'/<img\b([^>]*?)>/is',
				array( __CLASS__, 'lazy_img' ),
				$html
			);
		}

		if ( CacheRocket_Options::get( 'lazyload_youtube' ) ) {
			$html = preg_replace_callback(
				'/<iframe\b([^>]*?)>/is',
				array( __CLASS__, 'youtube_facade' ),
				$html
			);
		}

		if ( CacheRocket_Options::get( 'lazyload_iframes' ) ) {
			$html = preg_replace_callback(
				'/<iframe\b([^>]*?)>/is',
				array( __CLASS__, 'lazy_iframe' ),
				$html
			);
		}

		if ( CacheRocket_Options::get( 'lazyload_css_bg' ) ) {
			$html = preg_replace_callback(
				'/\sstyle=(["\'])(.*?)\1/is',
				array( __CLASS__, 'lazy_inline_bg' ),
				$html
			);
		}

		return $html;
	}

	/**
	 * Lazy-load a single img tag (also covers <picture> children).
	 *
	 * @param array<int, string> $m Match.
	 * @return string
	 */
	public static function lazy_img( $m ) {
		$attrs = $m[1];

		if ( false !== stripos( $attrs, 'loading=' ) || false !== stripos( $attrs, 'data-no-lazy' ) || false !== stripos( $attrs, 'data-cacherocket-nolazy' ) || false !== stripos( $attrs, 'data-cacherocket-lcp' ) ) {
			return $m[0];
		}

		if ( false !== stripos( $attrs, 'fetchpriority="high"' ) || false !== stripos( $attrs, "fetchpriority='high'" ) ) {
			return $m[0];
		}

		$attrs .= ' loading="lazy" decoding="async"';

		if ( CacheRocket_Options::get( 'image_dimensions' ) && false === stripos( $attrs, ' width=' ) && preg_match( '/src=(["\'])([^"\']+)\1/i', $attrs, $src ) ) {
			$size = self::local_image_size( $src[2] );
			if ( $size ) {
				$attrs .= ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"';
			}
		}

		return '<img' . $attrs . '>';
	}

	/**
	 * Replace YouTube iframe with click-to-play facade.
	 *
	 * @param array<int, string> $m Match.
	 * @return string
	 */
	public static function youtube_facade( $m ) {
		$attrs = $m[1];

		if ( ! preg_match( '/src=(["\'])([^"\']+)\1/i', $attrs, $src ) ) {
			return $m[0];
		}

		$url = $src[2];
		if ( ! preg_match( '#(?:youtube\.com|youtu\.be|youtube-nocookie\.com)#i', $url ) ) {
			return $m[0];
		}

		$id = '';
		if ( preg_match( '#(?:embed/|v=|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $idm ) ) {
			$id = $idm[1];
		}
		if ( ! $id ) {
			return $m[0];
		}

		$nocookie = false !== stripos( $url, 'youtube-nocookie.com' ) ? '1' : '0';
		$title    = 'YouTube video';
		if ( preg_match( '/title=(["\'])([^"\']*)\1/i', $attrs, $tm ) ) {
			$title = $tm[2];
		}

		$thumb = 'https://i.ytimg.com/vi/' . rawurlencode( $id ) . '/hqdefault.jpg';

		return sprintf(
			'<div class="cacherocket-yt" data-id="%s" data-nocookie="%s" data-title="%s"><img src="%s" alt="%s" loading="lazy" decoding="async" /><button type="button" aria-label="%s"></button></div>',
			esc_attr( $id ),
			esc_attr( $nocookie ),
			esc_attr( $title ),
			esc_url( $thumb ),
			esc_attr( $title ),
			esc_attr__( 'Play YouTube video', 'cacherocket' )
		);
	}

	/**
	 * Lazy-load non-YouTube iframe.
	 *
	 * @param array<int, string> $m Match.
	 * @return string
	 */
	public static function lazy_iframe( $m ) {
		$attrs = $m[1];

		if ( false !== stripos( $attrs, 'loading=' ) || false !== stripos( $attrs, 'data-no-lazy' ) ) {
			return $m[0];
		}

		if ( preg_match( '/youtube\.com|youtu\.be|youtube-nocookie\.com/i', $attrs ) ) {
			// YouTube handled by facade when enabled.
			if ( CacheRocket_Options::get( 'lazyload_youtube' ) ) {
				return $m[0];
			}
		}

		$attrs .= ' loading="lazy"';
		return '<iframe' . $attrs . '>';
	}

	/**
	 * Convert inline background-image styles to lazy data attributes.
	 *
	 * @param array<int, string> $m Match.
	 * @return string
	 */
	public static function lazy_inline_bg( $m ) {
		$q     = $m[1];
		$style = $m[2];

		if ( false === stripos( $style, 'background-image' ) || false !== stripos( $style, 'data-cacherocket-bg' ) ) {
			return $m[0];
		}

		if ( ! preg_match( '/background-image\s*:\s*([^;]+);?/i', $style, $bg ) ) {
			return $m[0];
		}

		$value = trim( $bg[1] );
		if ( '' === $value || false !== stripos( $value, 'gradient' ) || 'none' === strtolower( $value ) ) {
			return $m[0];
		}

		$style = preg_replace( '/background-image\s*:\s*[^;]+;?/i', '', $style );
		$style = trim( $style, " \t;" );

		$out = ' data-cacherocket-bg=' . $q . esc_attr( $value ) . $q;
		if ( '' !== $style ) {
			$out .= ' style=' . $q . esc_attr( $style ) . $q;
		}
		return $out;
	}

	/**
	 * Resolve width/height for a local attachment URL.
	 *
	 * @param string $url Image URL.
	 * @return array{0:int,1:int}|null
	 */
	private static function local_image_size( $url ) {
		$upload = wp_upload_dir();
		if ( empty( $upload['baseurl'] ) || empty( $upload['basedir'] ) ) {
			return null;
		}
		if ( 0 !== strpos( $url, $upload['baseurl'] ) ) {
			return null;
		}
		$path = str_replace( $upload['baseurl'], $upload['basedir'], $url );
		$path = strtok( $path, '?' );
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return null;
		}
		return array( (int) $size[0], (int) $size[1] );
	}
}
