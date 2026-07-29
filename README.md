# CacheRocket — WordPress Plugin

**Contributors:** NOOBBase  
**Tags:** cache, performance, SEO, speed optimization, cache warming, page cache, WooCommerce  
**Requires at least:** 5.5  
**Requires PHP:** 7.4  
**Tested up to:** 6.7  
**Stable tag:** 1.3.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

CacheRocket connects WordPress to [CacheRocket.com](https://www.cacherocket.com) for cache warming, and includes a WP Rocket–style performance suite: page cache, file optimization, LazyLoad, CDN, preload, and database cleanup.

> WordPress.org uses [`readme.txt`](readme.txt). This `README.md` is the public GitHub documentation.

## Description

### Admin pages

- **Dashboard** — status overview and feature map
- **Cache** — page caching, TTL, mobile cache, exclusions, WooCommerce
- **File Optimization** — minify CSS/JS, defer / delay JavaScript, Google Fonts
- **Media** — LazyLoad for images, iframes, YouTube; image dimensions
- **Preload** — warm on publish, link prefetch, manual warm trigger
- **Advanced** — CDN, browser caching, GZIP, Heartbeat
- **Database** — revisions, spam, transients, table optimize
- **Account** — API keys, plan, remote warmers

### Page caching

- **Free:** home, posts, pages, categories, tags, and archives (standard PHP delivery)
- **Paid:** optional WooCommerce shop, product, and taxonomy pages
- **Paid:** optional early delivery via an `advanced-cache.php` drop-in

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

1. Download or zip this plugin folder.
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Install and activate.

### Method 2: Copy into `wp-content/plugins`

1. Copy this repository into `/wp-content/plugins/cacherocket-cache-warmers/` (or your preferred folder name).
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
   - Choose **Standard (PHP)** or **Early (advanced-cache.php)** delivery (early requires a paid plan).
   - On a paid plan, optionally enable WooCommerce page caching.
4. Cached HTML is stored under `wp-content/cache/cacherocket/`.

### Early delivery (paid)

Early delivery requires this line in `wp-config.php` (above the “That’s all, stop editing!” comment):

```php
define( 'WP_CACHE', true );
```

When early mode is enabled, CacheRocket installs `wp-content/advanced-cache.php`. It does **not** modify `wp-config.php` automatically — add the constant yourself.

## Repository layout

```
cacherocket-cache-warmers.php   # Main plugin bootstrap
readme.txt                      # WordPress.org directory readme
README.md                       # This GitHub documentation
uninstall.php                   # Cleanup on plugin delete
admin/                          # Multi-page settings UI + assets
admin/pages/                    # Dashboard, Cache, File Optimization, …
includes/                       # Cache, optimizer, lazyload, CDN, DB, …
includes/drop-in/advanced-cache.php  # Source template for early delivery
languages/                      # Translation files
assets/                         # Screenshots / assets for directory listing
```

## Changelog

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

### 1.1.0

Adds local page caching. Deactivate other page-cache plugins to use CacheRocket page caching, or keep them and use CacheRocket for warming only.

## Support

- Email: [support@cacheRocket.com](mailto:support@cacheRocket.com)
- Site: [www.CacheRocket.com](https://www.cacherocket.com)
- Terms: https://cacherocket.com/terms-and-conditions
- WordPress support forum (when published): https://wordpress.org/support/plugin/cacheRocket

## License

This plugin is licensed under the GPLv2 (or later): https://www.gnu.org/licenses/gpl-2.0.html
