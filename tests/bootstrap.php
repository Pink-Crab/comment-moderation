<?php
/**
 * PHPUnit bootstrap.
 *
 * Boots wp-phpunit, activates this plugin (so Perique's bootstrap runs and
 * its registration classes get instantiated on `init`), and exposes a
 * tests/.env (gitignored) for the DB credentials.
 *
 * Mirrors the wayback-machine plugin's tests/bootstrap.php convention:
 *   - `WP_PHPUNIT__DIR` (set by wp-phpunit's composer install) locates the
 *     framework. No hard-coded vendor path.
 *   - `tests/.env` (read via vlucas/phpdotenv) supplies DB credentials.
 *     A `tests/.env_sample` template ships in the repo; copy to .env and
 *     edit. The .env itself is gitignored.
 *   - The plugin is activated through `activate_plugin()` inside
 *     `muplugins_loaded` — the same path WordPress would take in production.
 *
 * @package PinkCrab\Comment_Moderation\Tests
 */

declare( strict_types = 1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load tests/.env if present (silently — CI overrides via env vars).
try {
	\Dotenv\Dotenv::createUnsafeImmutable( __DIR__ )->safeLoad();
} catch ( \Throwable $e ) {
	// .env optional — CI / containerised dev set env vars directly.
}

// Locate wp-phpunit. wp-phpunit's composer install sets WP_PHPUNIT__DIR for us;
// fall back to the conventional path so a clean checkout still works.
$_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! is_string( $_phpunit_dir ) || ! is_dir( $_phpunit_dir ) ) {
	$_phpunit_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}
if ( ! is_dir( $_phpunit_dir ) ) {
	fwrite(
		STDERR,
		"ERROR: wp-phpunit not found at {$_phpunit_dir}.\n" .
		"       Run `composer install` first.\n"
	);
	exit( 1 );
}

require_once $_phpunit_dir . '/includes/functions.php';

// Activate the plugin during WP's load sequence so Perique boots and registers
// before any test method runs. The plugin directory is discovered at runtime
// — the same checkout works whether it's cloned to `pinkcrab-comment-moderation/`
// locally or `issue-<N>/` under the CI/routine layout.
//
// Activation runs on `after_setup_theme` rather than the earlier `muplugins_loaded`
// because WordPress 6.7 routes `get_plugin_data()` — invoked by
// `validate_plugin_requirements()` inside `activate_plugin()` — through
// `_load_textdomain_just_in_time()`, which now emits a doing-it-wrong notice
// whenever it runs before `after_setup_theme` has fired. Deferring activation
// to that hook (still well before any test method runs) keeps the activation
// lifecycle intact while staying inside the new WP 6.7+ load-timing contract.
tests_add_filter(
	'after_setup_theme',
	static function (): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		activate_plugin( basename( dirname( __DIR__ ) ) . '/pinkcrab-comment-moderation.php' );
	},
	0
);

// Boot WordPress + the test framework.
require $_phpunit_dir . '/includes/bootstrap.php';
