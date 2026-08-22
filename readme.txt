=== Cache Rocket ===
Contributors: justadev, cacherocket
Donate link: https://cacherocket.com/donate
Tags: cache, performance, page cache, cache warming, woocommerce
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The most advanced free WordPress cache plugin. Local suite unlocked. Connect an account for cloud CDN and Media.

== Description ==

**The most advanced free WordPress cache plugin.**

Local optimizations are unlocked on activation. Connect the plugin to [Cache Rocket](https://cacherocket.com/wordpress) when you want cloud CDN and Media jobs.

Cache Rocket ships a complete local performance suite — page cache, Critical Images, delay JavaScript, LazyLoad, custom CDN rewriting, database cleanup, and remote cache warming — **free forever**.

= Local suite is free =

Page cache, file optimization, LazyLoad, custom CDN rewriting, database cleanup, and warmer management in wp-admin are unlocked on activation.

= What is free vs cloud =

The local performance suite ships unlocked. Cloud optimization jobs and Managed CDN need a connected Cache Rocket account.

* Full page caching with early `advanced-cache.php` delivery *(free)*
* Optimize Critical Images (LCP beacon) and automatic Lazy Rendering *(free)*
* Delay JavaScript, minify CSS/JS, self-host fonts *(free)*
* LazyLoad, mobile/WebP cache variants, WooCommerce caching *(free)*
* Custom CDN CNAME rewriting, browser cache and GZIP rules *(free)*
* Remote cache warmers from wp-admin *(free account limits apply)*
* Managed CDN delivery on `assets.cacherocket.com` *(connected account)*
* Cloud image optimization with WebP/AVIF *(connected account)*
* LQIP placeholders and Critical CSS generation *(connected account)*
* PageSpeed Insights audits from Media *(connected account)*

One plugin. Free local tools. Connect an account when you want cloud CDN and Media.

Learn more at [cacherocket.com/wordpress](https://cacherocket.com/wordpress).

= What you get =

* Remote cache warmers managed from wp-admin
* Local page cache with optional early advanced-cache delivery
* File optimization, LazyLoad, custom CDN rewriting, and database cleanup
* Managed CDN, cloud image optimization, Critical CSS, LQIP, and PageSpeed with a connected account
* API-key auth against your Cache Rocket account

= Page cache =

Caches home, posts, pages, categories, tags, archives, and optional WooCommerce shop/product/taxonomy pages. Choose standard PHP delivery or early `advanced-cache.php` delivery.

Cached HTML is stored under `wp-content/cache/cacherocket/`.

These requests are never cached:

* Logged-in users (unless you explicitly allow it), admin screens, AJAX, and cron
* Non-GET requests and preview / Customizer requests
* WooCommerce cart, checkout, and account pages
* Requests with cart or logged-in cookies
* Paths, cookies, or user agents you exclude in Cache settings
* Pages when `DONOTCACHEPAGE` is defined

= Cloud optimization =

With API keys connected, Cache Rocket can optimize assets in the cloud and serve them from **Cache Rocket CDN** automatically (no hostname to configure):

* **Image optimization** — WebP/AVIF on `img.cacherocket.com`
* **LQIP** — low-quality image placeholders
* **Critical CSS** — above-the-fold CSS on `assets.cacherocket.com`
* **PageSpeed Insights** — Lighthouse audits from the Media page

Quotas are enforced by the Cache Rocket API, not by locking local plugin code. Optional custom CDN rewriting (your own hostnames) lives under **Advanced** and is fully available in this plugin.

= Compatibility =

If another page-cache plugin is active (WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache, and similar), Cache Rocket **page caching turns off automatically**. Cache warming and the rest of the suite still work alongside those plugins.

Never run two page caches at once.

= Open source =

GPLv2. Full source on [GitHub](https://github.com/CacheRocket/cacheRocket-wordpress-plugin). Product page and changelog: [cacherocket.com/wordpress](https://cacherocket.com/wordpress).

== External services ==

This plugin connects to external services to provide remote cache warming and optional cloud optimizations.

= Cache Rocket API =

* **What it is / used for:** Account authentication, plan/usage sync, remote cache warmers, and cloud optimization jobs (image optimization, LQIP, Critical CSS, PageSpeed). Managed CDN delivery for optimized assets uses Cache Rocket edge hostnames (`img.cacherocket.com`, `assets.cacherocket.com`).
* **When / what data is sent:** When you save API keys, sync plan, warm URLs, manage warmers, queue cloud jobs, clear cloud assets, or uninstall the plugin. Typical payloads include your site URL, API credentials you configured, warmer settings, and selected page/media URLs needed for the requested job. A lightweight install heartbeat / disconnect notice may be sent when connected.
* **Service:** [Cache Rocket](https://cacherocket.com)
* **Terms of Service:** https://cacherocket.com/terms-and-conditions
* **Privacy Policy:** https://cacherocket.com/privacy-policy

= Google Fonts (optional) =

* **What it is / used for:** When **Self-host Google Fonts** is enabled under File Optimization, the plugin downloads Google Fonts CSS and font files so they can be served from your site instead of Google’s servers.
* **When / what data is sent:** On front-end page loads that include Google Fonts stylesheet links (until the files are cached locally). The request is a standard HTTP fetch of the font CSS/font files; no WordPress user account data is sent.
* **Service:** Google Fonts — https://fonts.google.com/
* **Terms of Service:** https://policies.google.com/terms
* **Privacy Policy:** https://policies.google.com/privacy

== Installation ==

1. Install **Cache Rocket** from **Plugins → Add New**, or upload the plugin folder to `/wp-content/plugins/cache-rocket/`.
2. Activate it. Local optimization features are available immediately.
3. Create a free account at [Cache Rocket](https://cacherocket.com/auth/register) and paste your API keys under **Cache Rocket → Account**. Keys live under [Account → Profile](https://cacherocket.com/account/account-profile).
4. Connect API keys if you want remote warmers, Managed CDN, and cloud Media jobs.

= Early delivery =

Early delivery needs this line in `wp-config.php` (above “That’s all, stop editing!”):

`define( 'WP_CACHE', true );`

Cache Rocket installs `wp-content/advanced-cache.php` when early mode is enabled. It does **not** edit `wp-config.php` for you.

== Frequently Asked Questions ==

= What is cache warming? =

Cache warming visits your URLs like a real visitor so pages are already cached before people arrive. Warmers are created and edited in wp-admin.

= Will page caching work with my existing cache plugin? =

No. If another page-cache plugin is active, Cache Rocket page caching turns off automatically. You can still use Cache Rocket for warming and front-end optimizations.

= Where are cached pages stored? =

Under `wp-content/cache/cacherocket/`. Direct web execution of PHP from that folder is blocked.

= What is free vs what needs a Cache Rocket account? =

The local suite (page cache, file optimization, LazyLoad, custom CDN rewriting, database cleanup, warmer management) is free forever.

A connected Cache Rocket account adds remote warming quotas, Managed CDN, cloud image optimization, Critical CSS, LQIP, and PageSpeed. Details: [cacherocket.com/wordpress](https://cacherocket.com/wordpress).

= How does cloud image optimization work? =

When enabled under **Media**, image uploads are queued to Cache Rocket. Optimized WebP/AVIF variants are served from Cache Rocket Image CDN (`img.cacherocket.com`) automatically — you do not add that hostname yourself. This uses your monthly image-optimization quota.

= Can I use a nulled or cracked copy? =

No. Nulled plugins are often modified with malware. There is nothing to null — the full plugin is already free. Download only from WordPress.org or [cacherocket.com/wordpress](https://cacherocket.com/wordpress).

= Where can I get support? =

Use the [Cache Rocket support forum](https://wordpress.org/support/plugin/cache-rocket/) on WordPress.org, or [Cache Rocket](https://cacherocket.com).

== Changelog ==

= 1.6.6 =
* Fix "Invalid JSON response from the API." when running Warm from sitemap now. Sitemap warming is now queued as a background job on CacheRocket instead of being sent in synchronous batches that outlasted the API request timeout.
* Show live sitemap warm progress on the Preload screen; you can leave the page while the warm runs.

= 1.6.5 =
* Handle Google Fonts through the WordPress styles API: enqueued font stylesheets are self-hosted or given `display=swap` via `style_loader_src`, so the plugin no longer prints its own `<link rel="stylesheet">` tag. Font stylesheets hard-coded in a theme template now have their `href` rewritten in place.
* Google Fonts, custom DNS prefetch, and font preload hints now go through the `wp_resource_hints` and `wp_preload_resources` filters instead of being injected into `<head>`.
* Keep a theme's Google Fonts stylesheet intact when self-hosting fails, instead of dropping it.
* Add `justadev` to the contributors list.
* Point all plugin, author, terms, and privacy URLs at https://cacherocket.com (the canonical host; the `www` hostname only redirected).

= 1.6.4 =
* WordPress.org review compliance: unlock custom CDN rewriting (local feature), document external services, enqueue front-end/admin assets, pair output buffers with shutdown flush, escape CDN content URLs, add LCP beacon nonce, remove directory screenshot and bundled translation binaries from the package.

= 1.6.3 =
* Add public REST ping endpoint (`/wp-json/cacherocket/v1/ping`) so CacheRocket can verify the plugin is still installed.
* Notify CacheRocket on uninstall so connected installs are marked disconnected.

= 1.6.2 =
* Document Image CDN split: optimized images and LQIP are served from img.cacherocket.com; Critical CSS stays on assets.cacherocket.com.
* Update Media and Advanced admin copy for the new image vs assets CDN hostnames.
* Rename WordPress.org install slug and text domain to `cache-rocket` (main file `cache-rocket.php`, display name Cache Rocket). If you installed an older zip under `cacherocket/`, deactivate/delete that copy and install this version.

= 1.6.1 =
* Fix 403 Forbidden on minified CSS/JS under wp-content/cache/cacherocket/min/ (parent page-cache .htaccess was denying all HTTP access; /min/ now gets a public-access override).
* Fix mixed-content self-hosted Google Fonts CSS (force HTTPS scheme for uploads/cacherocket-fonts URLs and rewrite http links in cached CSS/HTML).

= 1.6.0 =
* Cloud image optimization (WebP/AVIF), LQIP, and Critical CSS served from CacheRocket CDN — no hostname to configure.
* Cloud image optimization covers existing media library, not only new uploads; drop original srcset/sizes so browsers use CDN AVIF/WebP.
* PageSpeed Insights action on the Media page (plan + daily quota).
* Custom CDN rewriting (optional; separate from CacheRocket CDN); Media cloud toggles sync via Account / getPlan.
* New API helpers: createOptimizationJob, getOptimizationJob, listOptimizationJobs.
* Critical CSS regenerates after Clear cache; jobs picked up within seconds on traffic; page cache purges when Critical CSS lands.
* Clear cache also deletes Critical CSS / image / LQIP objects from CacheRocket CDN storage; keeps OVH assets in sync on uninstall, attachment delete, and feature disable.
* Expired page-cache HTML is deleted from disk (empty folders pruned); Clear cache uses direct filesystem deletes.
* Fix warmer admin toggles (Active / Options checkboxes visible again).
* Clear cache CDN purge: reset stale team workspace to personal to avoid "Not a member of this organization".
* Gzip .htaccess rules skip wp-admin, AJAX, and JSON; front-end CDN/cloud rewrites and page-cache serving disabled in wp-admin / admin-ajax.
* Early advanced-cache.php skips wp-admin and system endpoints; auto-purge hooks register in admin; early cache keys match PHP writes for HTTPS proxies, mobile, and WebP.

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
* Plan: WooCommerce catalog caching and early advanced-cache.php delivery unlocked on Free.

= 1.4.7 =
* Removed manual `load_plugin_textdomain()` call; WordPress.org loads translations automatically for the plugin slug.

= 1.4.6 =
* Exclude hidden files (e.g. `.gitignore`) from the distribution zip for WordPress.org checks.

= 1.4.5 =
* Set Plugin URI to https://cacherocket.com/wordpress so it differs from Author URI (Plugin Check).

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

= 1.6.5 =
Google Fonts optimization now runs through the WordPress styles and resource hints APIs instead of writing tags into the page. Clear your cache after updating.

= 1.6.4 =
WordPress.org review fixes: custom CDN unlocked, external services documented, asset enqueue and security hardening.

= 1.6.2 =
Documents that cloud-optimized images are served from img.cacherocket.com while Critical CSS stays on assets.cacherocket.com.

= 1.6.1 =
Fixes 403 errors on minified CSS/JS and mixed-content self-hosted font CSS on HTTPS. Purge page cache after updating.

= 1.6.0 =
Adds cloud image optimization, LQIP, Critical CSS, and PageSpeed tools (paid CacheRocket plans). Connect API keys under Account, then review the new Media toggles.

= 1.5.0 =
Major performance update: Critical Images, Lazy Rendering, self-host fonts, YouTube facade, external minify, sitemap warm, and more. Review new toggles after updating. Preload auto-creates a site warmer so activity appears in CacheRocket Warmers.

= 1.4.7 =
Translations are loaded by WordPress for the `cache-rocket` text domain (no manual textdomain bootstrap).

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
