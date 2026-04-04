=== Nostr WP Blog ===
Contributors: synalysis
Tags: blog, import, markdown, authors, feed
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.1.1
Requires PHP: 8.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Syncs NIP-23 long-form articles from Nostr relays into WordPress with archive, templates, and optional NIP-05.

== Description ==

The plugin fetches [NIP-23](https://github.com/nostr-protocol/nips/blob/master/23.md) long-form articles from configured relays and stores them as a dedicated content type (`nostr_article`), separate from normal Posts. It provides an article archive, single-article templates, optional `/.well-known/nostr.json` for NIP-05, optional extension login (NIP-07 / NIP-42), and basic SEO integration (including sitemap support).

PHP dependencies are bundled in `vendor/` in official release zips, so site owners do not need to run Composer on the server.

== Installation ==

1. Upload the `nostr-wp-blog` folder to `/wp-content/plugins/` (or install the release `.zip` via **Plugins → Add New → Upload Plugin**).
2. Activate **Nostr WP Blog** through the **Plugins** menu.
3. Go to **Settings → Nostr blog** and add author npubs/pubkeys, relay URLs, and other options.
4. Run a sync from the settings page or wait for the scheduled task to import articles.

== Frequently Asked Questions ==

= Are Nostr articles mixed with my normal blog posts? =

No. Imported articles use the `nostr_article` custom post type. They appear under **Nostr articles** in the admin and use the plugin’s archive and single templates. They do not replace or merge with default **Posts** unless your theme or another plugin explicitly queries that post type.

= Do I need Composer on my WordPress server? =

Not if you install from a release zip that includes `vendor/`. Composer is only required when working from a development copy without vendored dependencies.

= Where do I report bugs or request features? =

Use the [GitHub issue tracker](https://github.com/synalysis/nostr-wp-blog/issues).

== Changelog ==

= 1.1.1 =
* Release zip: strip disallowed file types from bundled Composer dependencies (WordPress.org policy).

= 1.1.0 =
* Packaging and readme updates; coding standards, security sniffs, and i18n fixes.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.1 =
Fixes plugin zip contents for directory policy (no disallowed archives/scripts under vendor).
