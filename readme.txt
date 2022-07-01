=== ETH Simple Shortlinks ===
Contributors: ethitter
Donate link: https://ethitter.com/donate/
Tags: shortlink, shortlinks
Requires at least: 4.4
Tested up to: 6.0
Stable tag: 0.6.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Convert WordPress's query-based shortlinks to pretty permalinks that are cacheable. https://example.com/?p=123 becomes https://example.com/p/123/.

== Description ==

WordPress 3.0 introduced a native shortlinks feature, which builds URLs using query strings. This plugin converts those URLs to ones using pretty permalinks, which makes for a nicer-looking URL and improves shortlinks' ability to be cached.

Posts and pages are supported by default, while filters make it possible to support other post types.

For example, https://example.com/?p=123 becomes https://example.com/p/123/.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/eth-simple-shortlinks` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Flush your site's rewrite rules by visiting Settings > Permalinks.

== Frequently Asked Questions ==

= Can I customize a shortlink? =

No, shortlinks use the posts' IDs, so aren't available for modification.

= Why aren't redirects validated? =

Sites may use plugins that allow a post object's permalink to be set to an external URL, and this plugin is designed to respect those plugins.

If you wish to validate the redirects issued by this plugin, you can use the `eth_simple_shortlinks_redirect_url` filter to apply `wp_validate_redirect()` to the destination URL.

= After upgrading to 0.6, redirects stopped working =

Beginning with release 0.6, before performing a redirect, the plugin checks that the post type and post status are supported. Previously, these checks were only applied when overriding an object's shortlink.

If, after upgrading, redirects stop working, use the `eth_simple_shortlinks_allowed_post_types` and `eth_simple_shortlinks_allowed_post_statuses` filters to permit additional types and statuses, or use the `eth_simple_shortlinks_verify_requested_post_support` filter to disable the supports checks.

== Changelog ==

= 0.6.1 =
* Fix translation support.

= 0.6 =
* Introduce filters in redirection handling.
* Apply supported post-type and post-status checks before redirecting.
* Conform to WordPress VIP's Coding Standards.

= 0.5 =
* Admin notices when permalinks won't support the plugin
* Disable plugin functionality when permalink structure is incompatible
* Translation support

= 0.4 =
* Initial release

== Upgrade Notice ==

= 0.6.1 =
Fixes translation support.

= 0.6 =
Applies supported post-type and post-status checks before performing redirect. If, after upgrading, redirects stop working, see the "After upgrading to 0.6, redirects stopped working" section of the FAQ.
