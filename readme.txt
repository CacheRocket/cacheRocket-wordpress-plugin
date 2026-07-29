=== CacheRocket ===
Contributors: noobbase
Tags: cache, performance, page cache, cache warming, woocommerce
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Warm your cache from CacheRocket.com and optimize WordPress with page cache, file optimization, LazyLoad, CDN, and database cleanup.

== Description ==

CacheRocket connects your WordPress site to [CacheRocket.com](https://www.cacherocket.com) for cache warming, and includes a full performance suite in wp-admin:

* **Dashboard** — feature status and cache overview
* **Cache** — page caching, lifespan, exclusions, mobile cache, WooCommerce
* **File Optimization** — minify CSS/JS, defer / delay JavaScript, Google Fonts
* **Media** — LazyLoad for images, iframes, and YouTube
* **Preload** — warm on publish, link prefetch, manual warm triggers
* **Cache Warmers** — create, edit, enable, and disable remote warmers (plan limits enforced by API)
* **Advanced** — CDN CNAMEs, browser cache, GZIP, Heartbeat control
* **Database** — clean revisions, spam, transients, optimize tables
* **Account** — API keys, plan entitlements

= Page caching =

* **Free:** home, posts, pages, categories, tags, and archives
* **Paid:** optional WooCommerce shop, product, and taxonomy pages
* **Paid:** optional early delivery via an `advanced-cache.php` drop-in

= Compatibility =

If another page-cache plugin is active (for example WP Rocket, W3 Total Cache, LiteSpeed Cache, or WP Super Cache), CacheRocket **page caching is disabled automatically** so plugins do not conflict. Cache warming and other optimizations can still be used alongside other cache plugins.

= Never cached =

* Logged-in users (unless explicitly enabled), admin screens, AJAX, and cron
* Non-GET requests and preview / Customizer requests
* WooCommerce cart, checkout, and account pages
* Requests with cart or logged-in cookies
* Paths / cookies / user agents you exclude in Cache settings
* Pages when the `DONOTCACHEPAGE` constant is defined

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/cacherocket/`, or install via **Plugins → Add New**.
2. Activate the plugin through the **Plugins** screen.
3. Open **CacheRocket** in the admin menu.
4. Create an account at CacheRocket.com, then enter your API keys under **Account**.
5. Configure Cache, File Optimization, Media, and other tabs as needed.

= Early delivery (paid) =

Early delivery requires this line in `wp-config.php` (above the “That’s all, stop editing!” comment):

`define( 'WP_CACHE', true );`

CacheRocket installs `wp-content/advanced-cache.php` when early mode is enabled. It does **not** modify `wp-config.php` for you.

== Frequently Asked Questions ==

= What is cache warming? =

Cache warming pre-fetches URLs so pages are already cached before visitors arrive.

= Will page caching work with my existing cache plugin? =

No. If another page-cache plugin is active, CacheRocket page caching turns off automatically. You can still use CacheRocket for warming and front-end optimizations.

= Where are cached pages stored? =

Under `wp-content/cache/cacherocket/`. Direct web execution of PHP from that folder is blocked.

= What do Free and Paid unlock? =

Free caches basic WordPress pages with standard PHP delivery. Paid plans can enable WooCommerce catalog caching and early `advanced-cache.php` delivery. Plan status is read from your CacheRocket account via API keys.

= Where can I get support? =

Use the [CacheRocket support forum](https://wordpress.org/support/plugin/cacherocket/) on WordPress.org, or contact us via [CacheRocket.com](https://www.cacherocket.com).

== Changelog ==

= 1.4.6 =
* Exclude hidden files (e.g. `.gitignore`) from the distribution zip for WordPress.org checks.

= 1.4.5 =
* Set Plugin URI to https://www.cacherocket.com/wordpress so it differs from Author URI (Plugin Check).

= 1.4.4 =
* Updated WordPress.org plugin and support URLs to the `cacherocket` slug (replacing legacy `cacherocket-cache-warmers`).

= 1.4.3 =
* Added bundled translations for Dutch, French, German, Spanish, Ukrainian, Russian, and Belarusian (aligned with CacheRocket.com locales).

= 1.4.2 =
* Fixed Plugins-screen Support link to https://wordpress.org/support/plugin/cacherocket-cache-warmers/

= 1.4.1 =
* Renamed main plugin file and install folder slug to `cacherocket` (matches text domain).
* Added Website and Support links on the Plugins screen.

= 1.4.0 =
* Added Cache Warmers admin page: create, edit, enable/disable, start/stop, and delete warmers via the CacheRocket API.
* Plan entitlements (crawler limits and feature flags) sync from getPlan for form gating; caps are enforced server-side.

= 1.3.0 =
* Redesigned multi-page admin (Dashboard, Cache, File Optimization, Media, Preload, Advanced, Database, Account).
* Added minify/defer/delay JS, LazyLoad, CDN rewriting, browser cache & GZIP .htaccess rules, Heartbeat control, and database cleanup.
* Expanded cache controls: TTL, mobile cache, exclusions, query-string policy, auto-purge toggles.

= 1.2.0 =
* Warm-on-publish and plan-aware cache delivery improvements.

= 1.1.0 =
* Added filesystem page caching under `wp-content/cache/cacherocket/`.
* Free: basic WordPress pages; Paid: optional WooCommerce pages and early drop-in delivery.
* Compatibility detection disables page caching when another cache plugin is active.
* Plan sync via CacheRocket getPlan API.
* WordPress.org submission hardening (uninstall, WP_Filesystem for drop-in, no wp-config edits).

= 1.0.0 =
* Initial release with cache warmer API integration.

== Upgrade Notice ==

= 1.4.6 =
Distribution zip no longer includes hidden files rejected by WordPress.org.

= 1.4.5 =
Plugin URI now points at the CacheRocket WordPress product page (separate from Author URI).

= 1.4.4 =
Support and directory links now use the WordPress.org `cacherocket` plugin slug.

= 1.4.3 =
Adds admin UI translations for the same languages as CacheRocket.com.

= 1.4.2 =
Support link on the Plugins screen now points at the WordPress.org forum for `cacherocket-cache-warmers`.

= 1.4.1 =
Main plugin file is now `cacherocket.php` under folder `cacherocket/`. If you installed from an older zip named `cacherocket-cache-warmers`, deactivate/delete the old copy and install this version.

= 1.3.0 =
Major settings UI expansion with file optimization, media LazyLoad, CDN, and database tools. Review each CacheRocket submenu after updating.
