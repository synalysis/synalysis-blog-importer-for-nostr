# Nostr WP Blog

WordPress plugin that syncs [NIP-23](https://github.com/nostr-protocol/nips/blob/master/23.md) long-form articles from Nostr relays into your site, with an article archive, single-article templates, optional NIP-05 `/.well-known/nostr.json`, and optional Nostr extension login (NIP-07 / NIP-42).

**Repository:** [github.com/synalysis/nostr-wp-blog](https://github.com/synalysis/nostr-wp-blog)

### Push this tree to GitHub

If [the GitHub repo](https://github.com/synalysis/nostr-wp-blog) is empty, from this directory run:

```bash
git remote add origin https://github.com/synalysis/nostr-wp-blog.git
git push -u origin main
```

Use SSH if you prefer: `git@github.com:synalysis/nostr-wp-blog.git`.

## Requirements

- WordPress **6.0+**
- PHP **8.1+**
- [Composer](https://getcomposer.org/) (for a working copy from Git — not needed if you install from a release `.zip` that already includes `vendor/`)

## Install from GitHub

1. Clone this repository (or download a source archive).
2. If you use the **development tree** (without `vendor/`):  
   `cd nostr-wp-blog && composer install --no-dev --optimize-autoloader`
3. Copy the **`nostr-wp-blog`** folder into `wp-content/plugins/`.
4. In **Plugins**, activate **Nostr WP Blog**.
5. Open **Settings → Nostr blog** and configure authors, relays, and options.

Release **`.zip`** files built with the packaging script include `vendor/` so end users can upload the zip in **Plugins → Add New → Upload** without running Composer.

## Development (wp-env)

This repo includes [`.wp-env.json`](.wp-env.json) so you can run WordPress locally with the plugin mounted:

```bash
npx @wordpress/env start
```

Then finish Composer inside the plugin directory as above (from the host path `nostr-wp-blog/`), since `vendor/` is not committed.

## Build a release zip

From the repository root:

```bash
./scripts/package-nostr-wp-blog.sh
```

This runs `composer install --no-dev --optimize-autoloader` in `nostr-wp-blog/` (requires `composer` and `zip` on your PATH), then creates `nostr-wp-blog-{Version}.zip` at the repo root (version is read from the plugin header).

## License

The plugin is licensed under the **GNU General Public License v2.0 or later**. See [LICENSE](LICENSE).

Copyright notice for your distribution: *Nostr WP Blog — Copyright (C) Synalysis and contributors.*
