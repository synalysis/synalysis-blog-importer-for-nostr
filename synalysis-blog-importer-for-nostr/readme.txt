=== Synalysis Blog Importer for Nostr ===
Contributors: synalysis
Tags: blog, import, markdown, authors, feed, nostr, relay
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.1.2
Requires PHP: 8.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Imports NIP-23 long-form articles from configured relays into WordPress. Not affiliated with the Nostr protocol, WordPress, or relay operators.

== Description ==

This plugin fetches [NIP-23](https://github.com/nostr-protocol/nips/blob/master/23.md) long-form articles from relays you configure and stores them as a dedicated content type (`nostr_article`), separate from default Posts. It provides an article archive, single-article templates, optional `/.well-known/nostr.json` for NIP-05, optional browser extension sign-in (NIP-07 / NIP-42), and basic SEO integration (including sitemap support).

PHP dependencies are bundled in `vendor/` in official release zips, so site owners do not need to run Composer on the server.

== Installation ==

1. Upload the `synalysis-blog-importer-for-nostr` folder to `/wp-content/plugins/` (or install the release `.zip` via **Plugins → Add New → Upload Plugin**).
2. Activate **Synalysis Blog Importer for Nostr** through the **Plugins** menu.
3. Go to **Settings → Synalysis Blog Importer** and add author npubs/pubkeys, relay URLs, and other options.
4. Run a sync from the settings page or wait for the scheduled task to import articles.

== Frequently Asked Questions ==

= Are imported articles mixed with my normal blog posts? =

No. Imported articles use the `nostr_article` custom post type. They appear under **Relay articles** in the admin and use the plugin’s archive and single templates. They do not replace or merge with default **Posts** unless your theme or another plugin explicitly queries that post type.

= Do I need Composer on my WordPress server? =

Not if you install from a release zip that includes `vendor/`. Composer is only required when working from a development copy without vendored dependencies.

= Where do I report bugs or request features? =

Use the [GitHub issue tracker](https://github.com/synalysis/nostr-wp-blog/issues).

== Changelog ==

= 1.1.2 =
* Renamed plugin for WordPress.org trademark guidelines; text domain matches slug. Removed redundant load_plugin_textdomain (WP 4.6+). Documented public REST permission callbacks for extension sign-in.
* Admin notices limited to this plugin’s settings screen and (for a missing vendor tree) the Plugins list, in line with directory guidance on dashboard notices.

= 1.1.1 =
* Release zip: strip disallowed file types from bundled Composer dependencies (WordPress.org policy).

= 1.1.0 =
* Packaging and readme updates; coding standards, security sniffs, and i18n fixes.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.2 =
Renamed plugin and slug for directory compliance. Re-activate after upload if WordPress does not prompt automatically.
