# Synalysis Blog Importer for Nostr

WordPress plugin that imports [NIP-23](https://github.com/nostr-protocol/nips/blob/master/23.md) long-form articles from relays into your site, with an article archive, single-article templates, optional NIP-05 `/.well-known/nostr.json`, and optional browser extension sign-in (NIP-07 / NIP-42). This project is not affiliated with the Nostr protocol or WordPress.

**Repository:** [github.com/synalysis/nostr-wp-blog](https://github.com/synalysis/nostr-wp-blog)

## Requirements

- WordPress **6.0+**
- PHP **8.1+**
- [Composer](https://getcomposer.org/) (for a working copy from Git — not needed if you install from a release `.zip` that already includes `vendor/`)

## Install from GitHub

1. Clone this repository (or download a source archive).
2. If you use the **development tree** (without `vendor/`):  
   `cd synalysis-blog-importer-for-nostr && composer install --no-dev --optimize-autoloader`
3. Copy the **`synalysis-blog-importer-for-nostr`** folder into `wp-content/plugins/`.
4. In **Plugins**, activate **Synalysis Blog Importer for Nostr**.
5. Open **Settings → Synalysis Blog Importer** and configure authors, relays, and options.

Release **`.zip`** files built with the packaging script include `vendor/` so end users can upload the zip in **Plugins → Add New → Upload** without running Composer.

## Development (wp-env)

This repo includes [`.wp-env.json`](.wp-env.json) so you can run WordPress locally with the plugin mounted:

```bash
npx @wordpress/env start
```

Then finish Composer inside the plugin directory as above (from the host path `synalysis-blog-importer-for-nostr/`), since `vendor/` is not committed.

## Build a release zip

From the repository root:

```bash
./scripts/package-nostr-wp-blog.sh
```

This runs `composer install --no-dev --optimize-autoloader` in `synalysis-blog-importer-for-nostr/` (requires `composer` and `zip` on your PATH), then creates `synalysis-blog-importer-for-nostr-{Version}.zip` at the repo root (version is read from the plugin header). Exclusions are merged from `synalysis-blog-importer-for-nostr/distribution-exclude.txt` plus common junk patterns.

## License

This project is released under the [MIT License](LICENSE).

Copyright (c) Synalysis and contributors.
