=== CacheRocket ===
Contributors: noobbase
Tags: cache, performance, page cache, cache warming, woocommerce
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Warm caches from CacheRocket.com with page cache, file optimization, LazyLoad, CDN, cloud images, and database cleanup.

== Description ==

CacheRocket connects your WordPress site to [CacheRocket.com](https://www.cacherocket.com) for cache warming, and includes a full performance suite in wp-admin:

* **Dashboard** — feature status and cache overview
* **Cache** — page caching, lifespan, exclusions, mobile/WebP cache, WooCommerce
* **File Optimization** — minify CSS/JS (local files), defer / delay JavaScript, self-host Google Fonts, DNS prefetch
* **Media** — LazyLoad images/iframes/YouTube facade/CSS backgrounds, Critical Images, Lazy Rendering, cloud WebP/AVIF, LQIP, Critical CSS, PageSpeed
* **Preload** — warm on publish, link prefetch, sitemap warmUrls
* **Cache Warmers** — create, edit, enable, and disable remote warmers (plan limits enforced by API)
* **Advanced** — CacheRocket CDN info, optional custom CDN hostnames, browser cache, GZIP, Heartbeat, import/export
* **Database** — clean revisions, spam, transients, scheduled cleanup
* **Account** — API keys, plan entitlements, usage quotas

= Page caching =

* **Free:** home, posts, pages, categories, tags, archives, optional WooCommerce shop/product/taxonomy pages, and optional early `advanced-cache.php` delivery

= Cloud optimization (paid plans) =

With API keys connected, CacheRocket.com can optimize assets in the cloud and serve them from **CacheRocket CDN** automatically (no hostname to configure):

* **Image optimization** — convert new uploads to WebP/AVIF (plan-dependent) and rewrite front-end image URLs to `img.cacherocket.com`
* **LQIP** — low-quality image placeholders for faster perceived load
* **Critical CSS** — generate above-the-fold CSS per page and load it from `assets.cacherocket.com`
* **PageSpeed Insights** — queue Lighthouse audits from the Media page (daily quota)
* Quotas and feature flags sync from your CacheRocket plan; exhausted quotas hard-stop new jobs

Optional custom CDN rewriting (your own hostnames) is under **Advanced** and is separate from CacheRocket CDN.

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

= Early delivery =

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

Free includes WordPress page caching (standard or early delivery), optional WooCommerce catalog caching, file optimization, and more. Paid CacheRocket.com plans unlock higher warmer limits, remote crawling, managed CDN, cloud image optimization (WebP/AVIF), LQIP, Critical CSS, and PageSpeed audits — subject to plan quotas. Plan status is read from your CacheRocket account via API keys.

= How does cloud image optimization work? =

When enabled under **Media**, new image uploads are queued to CacheRocket.com. Optimized WebP/AVIF variants are served from CacheRocket Image CDN (`img.cacherocket.com`) automatically — you do not add that hostname yourself. Once a job completes, the plugin rewrites front-end image URLs. This consumes your monthly image-optimization quota.

= Where can I get support? =

Use the [CacheRocket support forum](https://wordpress.org/support/plugin/cacherocket/) on WordPress.org, or contact us via [CacheRocket.com](https://www.cacherocket.com).

== Changelog ==

= 1.6.2 =
* Document Image CDN split: optimized images and LQIP are served from img.cacherocket.com; Critical CSS stays on assets.cacherocket.com.
* Update Media and Advanced admin copy for the new image vs assets CDN hostnames.

= 1.6.1 =
* Fix 403 Forbidden on minified CSS/JS under wp-content/cache/cacherocket/min/ (parent page-cache .htaccess was denying all HTTP access).
* Fix mixed-content self-hosted Google Fonts CSS (force HTTPS scheme for uploads/cacherocket-fonts URLs).

= 1.6.0 =
* Cloud image optimization: queue WebP/AVIF conversion for new uploads via CacheRocket API; rewrite front-end src when ready.
* LQIP placeholders for new uploads; Critical CSS generation and wp_head injection.
* PageSpeed Insights action on the Media page (plan + daily quota).
* Plan-gated CDN rewrite and Media cloud toggles; sync usage via Account / getPlan.
* New API helpers: createOptimizationJob, getOptimizationJob, listOptimizationJobs.

= 1.5.0 =
* Disable emoji / embeds / jQuery Migrate, DNS prefetch, and font preload hints.
* YouTube click-to-play facade; LazyLoad for picture images and inline CSS backgrounds.
* Scheduled database cleanup; settings import/export; auto backup settings on plugin update.
* Self-host Google Fonts; LazyLoad CSS background images; sitemap to warmUrls (manual + daily cron).
* Separate WebP cache; Delay JS one-click exclusion packs (analytics, ads, chat, maps).
* Optimize Critical Images (LCP beacon); Automatic Lazy Rendering (content-visibility).
* External CSS/JS minify (no combine); WooCommerce empty-cart fragments cache.
* Auto-create a site warmer (if missing) so preload / warm-on-publish results appear under Warmers in CacheRocket.
* Manual warm notices show warmed / failed / skipped counts.

= 1.4.7 =
* Removed manual `load_plugin_textdomain()` call; WordPress.org loads translations automatically for the plugin slug.

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

= 1.6.2 =
Documents that cloud-optimized images are served from img.cacherocket.com while Critical CSS stays on assets.cacherocket.com.

= 1.6.1 =
Fixes 403 errors on minified CSS/JS and mixed-content self-hosted font CSS on HTTPS. Purge page cache after updating.

= 1.6.0 =
Adds cloud image optimization, LQIP, Critical CSS, and PageSpeed tools (paid CacheRocket plans). Connect API keys under Account, then review the new Media toggles.

= 1.5.0 =
Major performance update: Critical Images, Lazy Rendering, self-host fonts, YouTube facade, external minify, sitemap warm, and more. Review new toggles after updating. Preload auto-creates a site warmer so activity appears in CacheRocket Warmers.

= 1.4.7 =
Translations are loaded by WordPress for the `cacherocket` text domain (no manual textdomain bootstrap).

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
