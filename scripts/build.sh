#!/usr/bin/env bash
#
# Build a scoped --no-dev release of the plugin into dist/.
#
# Prerequisites the agent/dev MUST satisfy before running this:
#   1. scaffold-init has been run       (composer.json has no ## placeholders)
#   2. composer install has been run    (vendor/bin/php-scoper exists)
# build.sh REFUSES to run if either is missing. It does NOT try to recover.
#
# Strategy
# --------
# Source vendor/ stays put (dev install — gives us php-scoper). A SECOND
# vendor tree is built into build/vendor/ via `composer config vendor-dir`
# + `composer install --no-dev`. php-scoper reads src/ + build/vendor/, writes
# scoped copies to build/scoped/. dist/ is assembled from: source files
# rsync'd in (using .gitattributes export-ignore as the exclude list) +
# build/scoped/src/ + build/scoped/vendor/. composer dump-autoload regenerates
# the classmap inside dist/, then build/ is removed.
#
# No source-file snapshot. No git operations except an optional commit-SHA
# read for BUILD_INFO.json. Plugin does not need to be a git repo to build.

set -euo pipefail
cd "$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.."

DIST_DIR="dist"
BUILD_DIR="build"

# Restore vendor-dir on failure so a half-finished build doesn't leave
# composer.json pointing at build/vendor and break the dev workflow.
cleanup_failed() {
	composer config vendor-dir vendor >/dev/null 2>&1 || true
	rm -rf "$BUILD_DIR"
}
trap cleanup_failed EXIT INT TERM

# --- Pre-flight refusals ---------------------------------------------------

if grep -q '##' composer.json; then
	cat >&2 <<'MSG'

ERROR: composer.json still contains scaffold placeholders.

       Run scaffold-init first:
           php scripts/scaffold-init.php --answers=<answers>.json

MSG
	exit 1
fi

if [ ! -x vendor/bin/php-scoper ]; then
	cat >&2 <<'MSG'

ERROR: vendor/bin/php-scoper not found.

       Install dev dependencies first:
           composer install

MSG
	exit 1
fi

# --- 1. --no-dev install into build/vendor --------------------------------

rm -rf "$BUILD_DIR"

echo "→ composer install --no-dev into $BUILD_DIR/vendor (source vendor/ untouched)"
composer config vendor-dir "$BUILD_DIR/vendor"
composer install --no-dev --no-interaction --optimize-autoloader
composer config vendor-dir vendor

# --- 2. Scoper ------------------------------------------------------------
# .php-scoper.inc.php's Finders point at src/ + build/vendor/. Output mirrors
# input paths under build/scoped/, so we get:
#   build/scoped/src/
#   build/scoped/build/vendor/    ← awkward nesting, flattened in step 3.

echo "→ Running php-scoper"
vendor/bin/php-scoper add-prefix \
	--config="$PWD/.php-scoper.inc.php" \
	--output-dir="$BUILD_DIR/scoped" \
	--force --no-interaction

if [ ! -d "$BUILD_DIR/scoped/$BUILD_DIR/vendor" ]; then
	echo "ERROR: scoper produced no $BUILD_DIR/scoped/$BUILD_DIR/vendor/." >&2
	exit 1
fi

# --- 3. Flatten the awkward nested vendor path ----------------------------

mv "$BUILD_DIR/scoped/$BUILD_DIR/vendor" "$BUILD_DIR/scoped/vendor"
rm -rf "$BUILD_DIR/scoped/$BUILD_DIR"

# --- 4. Assemble dist/ ----------------------------------------------------

echo "→ Assembling $DIST_DIR/"
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

# Read .gitattributes' export-ignore list as the canonical "what doesn't ship".
# Awk turns `/some/path  export-ignore` into `--exclude=/some/path`.
EXCLUDES=$( awk '/export-ignore/ {print "--exclude=" $1}' .gitattributes )

# Plus paths we either don't want shipping or will replace with scoped versions.
# shellcheck disable=SC2086  # intentional word-splitting of $EXCLUDES
rsync -a $EXCLUDES \
	--exclude='/src/' \
	--exclude='/vendor/' \
	--exclude="/$BUILD_DIR/" \
	--exclude="/$DIST_DIR/" \
	--exclude='/node_modules/' \
	--exclude='/tmp/' \
	--exclude='/.git/' \
	--exclude='/clover.xml' \
	--exclude='/coverage-report/' \
	./ "$DIST_DIR/"

mv "$BUILD_DIR/scoped/src"    "$DIST_DIR/src"
mv "$BUILD_DIR/scoped/vendor" "$DIST_DIR/vendor"

# --- 5. Regenerate autoloader inside dist/ --------------------------------
# Before dumping: add a classmap entry pointing at vendor/.
#
# Why: php-scoper rewrites namespace declarations INSIDE vendor PHP files
# but does NOT touch each vendor package's composer.json — so their
# autoload.psr-4 still says e.g. "Webmozart\\Assert\\": "src/". When
# composer dump-autoload walks PSR-4 paths, the expected prefix
# (Webmozart\Assert) doesn't match the actual class name in src/Assert.php
# (Example\...\Vendor\Webmozart\Assert\Assert), so those classes get
# silently skipped — leaving an autoloader that knows about the plugin's
# own classes but NOT the scoped vendor (the plugin would fatal on first
# use). Adding a `classmap` entry tells composer to SCAN files and pick
# up the actual class declarations regardless of any PSR-4 mismatch.

echo "→ Adding classmap entry to $DIST_DIR/composer.json for scoped vendor coverage"
php -r '
	$f = $argv[1];
	$c = json_decode( file_get_contents( $f ), true );
	$c["autoload"]["classmap"] = $c["autoload"]["classmap"] ?? array();
	if ( ! in_array( "vendor/", $c["autoload"]["classmap"], true ) ) {
		$c["autoload"]["classmap"][] = "vendor/";
	}
	file_put_contents( $f, json_encode( $c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
' "$DIST_DIR/composer.json"

echo "→ composer dump-autoload (classmap-authoritative, --no-dev) in $DIST_DIR/"
( cd "$DIST_DIR" && composer dump-autoload --classmap-authoritative --no-dev --no-interaction )

# --- 6. BUILD_INFO.json ---------------------------------------------------

BUILD_AT="$( date -u +%Y-%m-%dT%H:%M:%SZ )"
SCOPER_VERSION="$( vendor/bin/php-scoper --version 2>/dev/null | head -1 || echo unknown )"
BUILD_COMMIT="$( git rev-parse HEAD 2>/dev/null || echo unknown )"

cat > "$DIST_DIR/BUILD_INFO.json" <<JSON
{
    "commit":         "$BUILD_COMMIT",
    "built_at":       "$BUILD_AT",
    "scoper_version": "$SCOPER_VERSION"
}
JSON

# --- 7. Verification ------------------------------------------------------

echo "→ Verifying $DIST_DIR/"

verify_fail() { echo "ERROR: $1" >&2; exit 1; }

[ -f "$DIST_DIR/composer.json" ]       || verify_fail "composer.json missing"
[ -d "$DIST_DIR/src" ]                 || verify_fail "src/ missing"
[ -d "$DIST_DIR/vendor" ]              || verify_fail "vendor/ missing"
[ -f "$DIST_DIR/vendor/autoload.php" ] || verify_fail "vendor/autoload.php missing"
[ -f "$DIST_DIR/BUILD_INFO.json" ]     || verify_fail "BUILD_INFO.json missing"

if grep -r '##[A-Z_]\+##' "$DIST_DIR" \
		--include='*.php' --include='*.json' --include='*.xml' --include='*.neon' --include='*.md' \
		--exclude-dir=vendor \
		-l >/dev/null 2>&1; then
	echo "ERROR: unsubstituted scaffold placeholders found in $DIST_DIR:" >&2
	grep -r '##[A-Z_]\+##' "$DIST_DIR" \
		--include='*.php' --include='*.json' --include='*.xml' --include='*.neon' --include='*.md' \
		--exclude-dir=vendor \
		-l >&2
	exit 1
fi

for dev_pkg in phpunit humbug php-stubs wp-phpunit yoast vlucas roots/wordpress a8cteam51 roave pinkcrab/php-scoper-helper; do
	[ ! -d "$DIST_DIR/vendor/$dev_pkg" ] \
		|| verify_fail "dev dep '$dev_pkg' leaked into dist/vendor/"
done

# --- 8. Cleanup -----------------------------------------------------------

rm -rf "$BUILD_DIR"

trap - EXIT INT TERM

echo
echo "✓ Build complete: $DIST_DIR/"
echo "  Commit:  $BUILD_COMMIT"
echo "  Scoper:  $SCOPER_VERSION"
echo "  Built:   $BUILD_AT"
