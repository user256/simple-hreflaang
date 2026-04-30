# CannyForge Hreflang

Contributors: User256  
Tags: hreflang, sitemap, multilingual, translation groups  
Requires at least: 5.8  
Tested up to: 6.9  
Requires PHP: 7.4  
Stable tag: 0.1.0  
License: MIT  
License URI: https://opensource.org/licenses/MIT

Group equivalent pages and publish hreflang relationships in a dedicated XML sitemap.

## Description

For small sites with a handful of international pages creating an entire multisite is often excessive. Enter CannyForge Hreflang. This plugin lets you organize related pages into translation groups with language and optional region settings. It generates a standalone sitemap at `/hreflang-sitemap.xml` so search engines can discover alternate versions of your content.

## Installation

1. Upload the `cannyforge-hreflang` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > CannyForge Hreflang**.
4. Optionally configure the enabled post types and minimum group size.
5. If the sitemap URL is not immediately accessible, visit **Settings > Permalinks** and save changes to refresh rewrite rules.
6. Manage page hreflang values from the plugin settings page or the page edit screen.

## Frequently Asked Questions

### How do I add a page to a hreflang group?

Use the plugin settings page or the page edit screen to assign a Translation Group, language, and optional region.

### Why is my sitemap not generating?

Only groups with enough published pages and valid hreflang values are included. If needed, flush rewrite rules using the button on Settings > CannyForge Hreflang.

### What does x-default do?

Mark one page in each group as x-default so search engines know the default alternate for users who do not match a specific locale.

## Screenshots

1. Settings page with translation group and hreflang controls.
2. Add to group modal.
3. Generated hreflang sitemap URL.

## Support

For support, report issues in the source repo or contact the plugin author. This plugin is intended for small sites managing manual hreflang groupings and does not provide automatic translation or URL rewriting beyond sitemap output.

## Changelog

### 0.1.0

- Initial release with translation group management, x-default support, and hreflang sitemap generation.
