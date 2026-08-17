# CacheRocket — WordPress Plugin

**Contributors:** cacherocket, noobbase  
**Tags:** cache, performance, page cache, cache warming, woocommerce  
**Requires at least:** 5.5  
**Requires PHP:** 7.4  
**Tested up to:** 7.0  
**Stable tag:** 1.6.4  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

CacheRocket connects WordPress to [CacheRocket.com](https://www.cacherocket.com) for cache warming and cloud optimization, and includes a performance suite: page cache, file optimization, LazyLoad, CDN, image optimization, Critical CSS, preload, cache warmers, and database cleanup.

> WordPress.org uses [`readme.txt`](readme.txt). This `README.md` is the public GitHub documentation.
>
> Plugin slug / install folder: **`cache-rocket`** (text domain: `cache-rocket`). Do not use the legacy slugs `cacherocket` or `cacherocket-cache-warmers`.

## Description

### Admin pages

- **Dashboard** — status overview and feature map
- **Cache** — page caching, TTL, mobile/WebP cache, exclusions, WooCommerce
- **File Optimization** — minify CSS/JS, defer / delay JavaScript, self-host fonts, DNS prefetch
- **Media** — LazyLoad, YouTube facade, Critical Images, Lazy Rendering, cloud image optimization (WebP/AVIF), LQIP, Critical CSS, PageSpeed
- **Preload** — warm on publish, link prefetch, sitemap warm, manual warm trigger
- **Cache Warmers** — create, edit, enable, and disable remote warmers
- **Advanced** — CacheRocket CDN (automatic), optional custom CDN hostnames, browser caching, GZIP, Heartbeat, import/export
- **Database** — revisions, spam, transients, scheduled cleanup
- **Account** — API keys, plan, entitlements, usage quotas

### Page caching

- **Free:** home, posts, pages, categories, tags, and archives (standard PHP delivery), plus optional WooCommerce shop/product/taxonomy pages and early `advanced-cache.php` delivery

### Cloud optimization (CacheRocket.com service)

When API keys are connected, CacheRocket.com can process assets in the cloud and serve them from **CacheRocket CDN** automatically (no hostname to configure):

- **Image optimization** — convert uploads to WebP/AVIF and rewrite front-end image URLs to `img.cacherocket.com`
- **LQIP** — low-quality image placeholders for faster perceived load
- **Critical CSS** — generate above-the-fold CSS per page and load it from `assets.cacherocket.com`
- **PageSpeed Insights** — queue Lighthouse audits from the Media page (daily quota)
- Quotas and entitlements are enforced by the CacheRocket.com API (not by locking local plugin code)

Optional **custom CDN** rewriting (your own hostnames) lives under **Advanced**, is fully available in this plugin, and is separate from CacheRocket CDN.

### External services

This plugin may connect to:

- **CacheRocket.com API** (`api.cacherocket.com`) — plan sync, warmers, cloud optimization jobs. [Terms](https://www.cacherocket.com/terms-and-conditions) · [Privacy](https://www.cacherocket.com/privacy-policy)
- **Google Fonts** (optional, when self-hosting fonts) — [Terms](https://policies.google.com/terms) · [Privacy](https://policies.google.com/privacy)

### Compatibility

If another page-cache plugin is active (for example WP Rocket, W3 Total Cache, LiteSpeed Cache, or WP Super Cache), CacheRocket **page caching is disabled automatically** so plugins do not conflict. Cache warming and front-end optimizations can still be used alongside other cache plugins.

### Never cached

- Logged-in users, admin screens, AJAX, and cron
- Non-GET requests and preview / Customizer requests
- WooCommerce cart, checkout, and account pages
- Requests with cart or logged-in cookies
- Pages when the `DONOTCACHEPAGE` constant is defined

## Installation

### Method 1: Upload via WordPress Admin

1. Download [`cache-rocket.zip`](https://github.com/CacheRocket/cacheRocket-wordpress-plugin/releases) from GitHub Releases (folder root must be `cache-rocket/`).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Install and activate.

### Method 2: Copy into `wp-content/plugins`

1. Copy this repository into `/wp-content/plugins/cache-rocket/`.
2. Activate **CacheRocket** under **Plugins**.

### Method 3: WordPress Plugin Directory (when published)

1. Go to **Plugins → Add New**.
2. Search for `CacheRocket`.
3. Install and activate.

## Usage

1. After activation, open **CacheRocket** in the admin menu.
2. Create a free account at [CacheRocket.com](https://www.cacherocket.com) and add your API keys.
3. Configure **Page Caching**:
   - Enable page caching (on by default when no conflicting plugin is present).
   - Choose **Standard (PHP)** or **Early (advanced-cache.php)** delivery.
   - Optionally enable WooCommerce catalog page caching.
4. Cached HTML is stored under `wp-content/cache/cacherocket/`.

### Early delivery

Early delivery requires this line in `wp-config.php` (above the “That’s all, stop editing!” comment):

```php
define( 'WP_CACHE', true );
```

When early mode is enabled, CacheRocket installs `wp-content/advanced-cache.php`. It does **not** modify `wp-config.php` automatically — add the constant yourself.

## Repository layout

```
cache-rocket.php                # Main plugin bootstrap
readme.txt                      # WordPress.org directory readme
README.md                       # This GitHub documentation
uninstall.php                   # Cleanup on plugin delete
admin/                          # Multi-page settings UI + assets
admin/pages/                    # Dashboard, Cache, File Optimization, …
includes/                       # Cache, optimizer, lazyload, CDN, DB, …
includes/drop-in/advanced-cache.php  # Source template for early delivery
languages/                      # Translation template (.pot); community translations via translate.wordpress.org
assets/                         # Plugin logo (directory banners/screenshots go to SVN assets after approval)
bin/package-plugin.sh           # Builds cache-rocket.zip with correct slug
```

## Changelog

### 1.6.4

- WordPress.org review compliance: unlock custom CDN rewriting (local feature), document external services, enqueue front-end/admin assets, pair output buffers with shutdown flush, escape CDN content URLs, add LCP beacon nonce, remove directory screenshot and bundled translation binaries from the package.

### 1.6.3

- Add public REST ping endpoint (`/wp-json/cacherocket/v1/ping`) so CacheRocket can verify the plugin is still installed.
- Notify CacheRocket on uninstall so connected installs are marked disconnected.

### 1.6.2

- Document Image CDN split: optimized images and LQIP are served from `img.cacherocket.com`; Critical CSS stays on `assets.cacherocket.com`.
- Update Media and Advanced admin copy for the new image vs assets CDN hostnames.
- Renamed WordPress.org install slug and text domain to `cache-rocket` (main file `cache-rocket.php`, display name Cache Rocket).

### 1.6.1

- Fix 403 Forbidden on minified CSS/JS under `wp-content/cache/cacherocket/min/` (parent page-cache `.htaccess` was denying all HTTP access; `/min/` now gets a public-access override).
- Fix mixed-content self-hosted Google Fonts CSS (force HTTPS for `uploads/cacherocket-fonts` URLs and rewrite http links in cached CSS/HTML).

### 1.6.0

- Cloud image optimization (WebP/AVIF), LQIP, and Critical CSS served from CacheRocket CDN — no hostname to configure.
- Cloud image optimization covers existing media library, not only new uploads; drop original `srcset`/`sizes` so browsers use CDN AVIF/WebP.
- PageSpeed Insights action on the Media page (plan + daily quota).
- Custom CDN rewriting (optional; separate from CacheRocket CDN); Media cloud toggles sync via Account / getPlan.
- New API helpers: `createOptimizationJob`, `getOptimizationJob`, `listOptimizationJobs`.
- Critical CSS regenerates after Clear cache; jobs picked up within seconds on traffic; page cache purges when Critical CSS lands.
- Clear cache also deletes Critical CSS / image / LQIP objects from CacheRocket CDN storage; keeps OVH assets in sync on uninstall, attachment delete, and feature disable.
- Expired page-cache HTML is deleted from disk (empty folders pruned); Clear cache uses direct filesystem deletes.
- Fix warmer admin toggles (Active / Options checkboxes visible again).
- Clear cache CDN purge: reset stale team workspace to personal to avoid "Not a member of this organization".
- Gzip `.htaccess` rules skip wp-admin, AJAX, and JSON; front-end CDN/cloud rewrites and page-cache serving disabled in wp-admin / admin-ajax.
- Early `advanced-cache.php` skips wp-admin and system endpoints; auto-purge hooks register in admin; early cache keys match PHP writes for HTTPS proxies, mobile, and WebP.

### 1.5.0

- Disable emoji / embeds / jQuery Migrate, DNS prefetch, font preload hints.
- YouTube click-to-play facade; LazyLoad for images in `<picture>` and inline CSS backgrounds.
- Scheduled database cleanup; settings import/export; backup settings on plugin update.
- Self-host Google Fonts; sitemap → warmUrls (manual + daily cron).
- Separate WebP cache; Delay JS one-click exclusion packs.
- Optimize Critical Images (LCP); Automatic Lazy Rendering.
- External CSS/JS minify (no combine); WooCommerce empty-cart fragments cache.
- Auto-create a site warmer (if missing) so preload / warm-on-publish results appear under Warmers in CacheRocket.
- Manual warm notices show warmed / failed / skipped counts.
- Plan: WooCommerce catalog caching and early `advanced-cache.php` delivery unlocked on Free.

### 1.4.7

- Removed manual `load_plugin_textdomain()`; WordPress.org loads translations for the `cacherocket` slug automatically.

### 1.4.6

- Distribution zip excludes hidden files (e.g. `.gitignore`) required by WordPress.org automated checks.

### 1.4.5

- Plugin URI set to https://www.cacherocket.com/wordpress (distinct from Author URI for Plugin Check).

### 1.4.4

- WordPress.org plugin and support URLs now use the `cacherocket` slug (replacing legacy `cacherocket-cache-warmers`).

### 1.4.3

- Bundled translations: `nl_NL`, `fr_FR`, `de_DE`, `es_ES`, `uk`, `ru_RU`, `bel` (same languages as CacheRocket.com)

### 1.4.2

- Plugins-screen Support link: https://wordpress.org/support/plugin/cacherocket-cache-warmers/

### 1.4.1

- Renamed main plugin file to `cacherocket.php` and install slug to `cacherocket`.
- Plugins screen Website / Support meta links.

### 1.4.0

- Cache Warmers admin page (create, edit, enable/disable, start/stop, delete).
- API base URL `https://api.cacherocket.com/web/v1/wordpress`.
- CacheRocket logo in admin sidebar and wp-admin menu.

### 1.3.0

- Redesigned multi-page admin aligned with modern cache-plugin UX.
- File optimization, LazyLoad, CDN, browser cache/GZIP, Heartbeat, database cleanup.
- Expanded cache controls (TTL, mobile, exclusions, query strings, auto-purge).

### 1.1.0

- Added filesystem page caching under `wp-content/cache/cacherocket/`.
- Free: basic WordPress pages; Paid: optional WooCommerce pages and early drop-in delivery.
- Compatibility detection disables page caching when another cache plugin is active.
- Plan sync via CacheRocket `getPlan` API.
- WordPress.org submission hardening (`uninstall.php`, WP_Filesystem for drop-in, no `wp-config.php` edits).

### 1.0.0

- Initial release with cache warmer API integration.

## Upgrade Notice

### 1.6.4

WordPress.org review fixes: custom CDN unlocked, external services documented, asset enqueue and security hardening.

### 1.6.2

Documents that cloud-optimized images are served from `img.cacherocket.com` while Critical CSS stays on `assets.cacherocket.com`.

### 1.6.1

Fixes 403 errors on minified CSS/JS and mixed-content self-hosted font CSS on HTTPS. Purge page cache after updating.

### 1.6.0

Adds cloud image optimization, LQIP, Critical CSS, and PageSpeed tools (paid CacheRocket plans). Connect API keys under Account, then review the new Media toggles.

### 1.4.1

If you still have a folder named `cacherocket` or `cacherocket-cache-warmers`, remove it and install this package as `cache-rocket/`.

### 1.1.0

Adds local page caching. Deactivate other page-cache plugins to use CacheRocket page caching, or keep them and use CacheRocket for warming only.

## Support

- Email: [support@cacherocket.com](mailto:support@cacherocket.com)
- Site: [www.cacherocket.com](https://www.cacherocket.com)
- Terms: https://www.cacherocket.com/terms-and-conditions
- Privacy: https://www.cacherocket.com/privacy-policy
- WordPress support forum: https://wordpress.org/support/plugin/cache-rocket/

## License

This plugin is licensed under the GPLv2 (or later): https://www.gnu.org/licenses/gpl-2.0.html
