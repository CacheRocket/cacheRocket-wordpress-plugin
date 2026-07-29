<?php
/**
 * Instant page / link prefetch + heartbeat control.
 *
 * @package CacheRocket
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Prefetch internal links on hover and tune WP heartbeat.
 */
class CacheRocket_Preload {

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( CacheRocket_Options::get( 'preload_links' ) && ! is_admin() ) {
			add_action( 'wp_footer', array( __CLASS__, 'print_prefetch_script' ), 50 );
		}

		if ( CacheRocket_Options::get( 'heartbeat_control' ) ) {
			add_filter( 'heartbeat_settings', array( __CLASS__, 'heartbeat_settings' ) );
		}
	}

	/**
	 * Adjust heartbeat interval.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function heartbeat_settings( $settings ) {
		$freq = (int) CacheRocket_Options::get( 'heartbeat_frequency', 60 );
		$settings['interval'] = max( 15, $freq );
		return $settings;
	}

	/**
	 * Lightweight hover prefetch for same-origin links.
	 */
	public static function print_prefetch_script() {
		?>
		<script id="cacherocket-preload-links">
		(function(){
			var seen={};
			function prefetch(url){
				if(!url||seen[url])return;
				if(url.indexOf(location.origin)!==0)return;
				seen[url]=1;
				var l=document.createElement('link');
				l.rel='prefetch';
				l.href=url;
				document.head.appendChild(l);
			}
			document.addEventListener('mouseover',function(e){
				var a=e.target&&e.target.closest?e.target.closest('a[href]'):null;
				if(!a)return;
				prefetch(a.href);
			},{passive:true});
		})();
		</script>
		<?php
	}
}
