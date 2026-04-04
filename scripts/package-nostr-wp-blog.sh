#!/usr/bin/env bash
# Build nostr-wp-blog.zip for WordPress: Plugins → Add New → Upload Plugin.
# Requires: zip (zip package). Run from anywhere.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="nostr-wp-blog"
MAIN="${ROOT}/${PLUGIN}/nostr-wp-blog.php"

if [[ ! -f "$MAIN" ]]; then
	echo "error: expected ${MAIN}" >&2
	exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
	echo "error: 'zip' not found. Install zip (e.g. apt install zip)." >&2
	exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
	echo "error: 'composer' not found. Install Composer so vendor/ can be bundled in the zip." >&2
	exit 1
fi

echo "Running composer install in ${ROOT}/${PLUGIN} ..."
( cd "${ROOT}/${PLUGIN}" && composer install --no-dev --optimize-autoloader --no-interaction )

VERSION="$(grep -m1 '^[[:space:]]*\* Version:' "$MAIN" | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//;s/[[:space:]]*$//;s/\r$//')"
if [[ -z "$VERSION" ]]; then
	VERSION="0.0.0"
fi

OUT="${ROOT}/${PLUGIN}-${VERSION}.zip"
cd "$ROOT"
rm -f "$OUT"

zip -r "$OUT" "$PLUGIN" \
	-x "${PLUGIN}/.git/*" \
	-x "${PLUGIN}/.git" \
	-x "*.DS_Store" \
	-x "*__MACOSX*" \
	-x "${PLUGIN}/*.zip" \
	-q

echo "Created: ${OUT}"
ls -lh "$OUT"
