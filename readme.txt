=== ETH Simple Shortlinks ===
Contributors: ethitter
Donate link: https://ethitter.com/donate/
Tags: seo, meta tags
Requires at least: 4.4
Tested up to: 4.6
Stable tag: 0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Convert WordPress's query-based shortlinks to pretty permalinks that are cacheable. http://example.com/?p=123 becomes http://example.com/p/123/.

== Description ==

WordPress 3.0 introduced a native shortlinks feature, which builds URLs using query strings. This plugin converts those URLs to ones using pretty permalinks, which makes for a nicer-looking URL and improves shortlinks' ability to be cached.

Posts and pages are supported by default, while filters make it possible to support other post types.

For example, http://example.com/?p=123 becomes http://example.com/p/123/.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/eth-simple-shortlinks` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Flush your site's rewrite rules by visiting Settings > Permalinks.

== Frequently Asked Questions ==

= Can I customize a shortlink? =

No, shortlinks use the posts' IDs, so aren't available for modification.

== Changelog ==

= 0.5 =
* Admin notices when permalinks won't support the plugin
* Disable plugin functionality when permalink structure is incompatible
* Translation support

= 0.4 =
* Initial release
