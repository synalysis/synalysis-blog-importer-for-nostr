#!/usr/bin/env bash
# Build synalysis-blog-importer-for-nostr-{Version}.zip for WordPress: Plugins → Add New → Upload Plugin,
# or as the artifact to upload when submitting to the WordPress.org plugin directory.
# Requires: zip, composer. Run from anywhere.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="synalysis-blog-importer-for-nostr"
MAIN="${ROOT}/${PLUGIN}/synalysis-blog-importer-for-nostr.php"
DIST_EXCLUDES_FILE="${ROOT}/${PLUGIN}/distribution-exclude.txt"

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

# WordPress.org rejects zips that contain these file types anywhere (including vendor/).
if [[ -d "${ROOT}/${PLUGIN}/vendor" ]]; then
	find "${ROOT}/${PLUGIN}/vendor" -type f \( \
		-name '*.sh' -o -name '*.bash' -o -name '*.phar' -o -name '*.zip' \
		-o -name '*.gz' -o -name '*.tar' -o -name '*.tgz' -o -name '*.rar' -o -name '*.7z' \
	\) -delete 2>/dev/null || true
fi

VERSION="$(grep -m1 '^[[:space:]]*\* Version:' "$MAIN" | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//;s/[[:space:]]*$//;s/\r$//')"
if [[ -z "$VERSION" ]]; then
	VERSION="0.0.0"
fi

OUT="${ROOT}/${PLUGIN}-${VERSION}.zip"
cd "$ROOT"
rm -f "$OUT"

ZIP_EXCLUDES=(
	"-x" "${PLUGIN}/.git/*"
	"-x" "${PLUGIN}/.git"
	"-x" "*.DS_Store"
	"-x" "*__MACOSX*"
	"-x" "${PLUGIN}/*.zip"
)

if [[ -f "$DIST_EXCLUDES_FILE" ]]; then
	while IFS= read -r raw || [[ -n "$raw" ]]; do
		line="${raw#"${raw%%[![:space:]]*}"}"
		line="${line%"${line##*[![:space:]]}"}"
		[[ -z "$line" || "$line" == \#* ]] && continue
		pat="${PLUGIN}/${line}"
		ZIP_EXCLUDES+=( "-x" "${pat}" )
		if [[ "$line" != *'*'* ]]; then
			ZIP_EXCLUDES+=( "-x" "${pat}/*" )
		fi
	done < "$DIST_EXCLUDES_FILE"
fi

zip -r "$OUT" "$PLUGIN" "${ZIP_EXCLUDES[@]}" -q

echo "Created: ${OUT}"
ls -lh "$OUT"
