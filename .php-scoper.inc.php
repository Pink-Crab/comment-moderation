<?php
/**
 * PHP-Scoper configuration.
 *
 * Run via `composer build` (see scripts/build.sh). Scopes every PHP class,
 * function, and constant inside `vendor/` (and `src/`'s references to them)
 * to the namespace prefix below, so this plugin's bundled dependencies
 * don't collide with any other plugin's at runtime.
 *
 * Why use `pinkcrab/php-scoper-helper`:
 *   The big tedious piece of a php-scoper config is the `exclude-*` lists —
 *   you must NOT prefix WordPress's own functions/classes/constants, but
 *   listing them by hand drifts from reality and over/under-matches with
 *   regexes. PHP_Scoper_Helper::for_wordpress() reads php-stubs/wordpress-
 *   stubs (the same stubs PHPStan uses) and produces an exact, current
 *   exclude list. See https://github.com/Pink-Crab/PHPScoper-Helper.
 *
 * `first_party_from_composer()` reads this plugin's own PSR-4 autoload
 * prefixes from composer.json and adds them to `exclude-namespaces`, so
 * src/ files keep their original namespace and only their references to
 * vendor classes get rewritten.
 *
 * @package PinkCrab\Comment_Moderation
 */

declare( strict_types = 1 );

use Isolated\Symfony\Component\Finder\Finder;
use PinkCrab\PHP_Scoper_Helper\PHP_Scoper_Helper;

// Finders pick the files scoper processes.
//
// - `vendor/` files get their namespace prefixed (and references rewritten).
// - `src/` files have their REFERENCES to vendor classes rewritten, but
//   their own namespace declaration stays put (because of
//   `first_party_from_composer()`).
// `build/vendor/` is created by scripts/build.sh via `composer config
// vendor-dir build/vendor` + `composer install --no-dev`. The source tree's
// vendor/ stays as the dev install (with php-scoper, phpunit, etc.) and is
// NOT scoped. Run scoper standalone outside `composer build` and you'll need
// to set this up first.
$finders = array(
	Finder::create()
		->files()
		->ignoreVCS( true )
		->name( '*.php' )
		->in( 'build/vendor' ),
	Finder::create()
		->files()
		->name( '*.php' )
		->in( 'src' ),
);

// To also exclude WooCommerce / ACF / etc. globals from scoping, pass their
// short names as arguments to for_wordpress(). Underscores and hyphens are
// both accepted. Available short names:
//
//   wordpress                  php-stubs/wordpress-stubs                (always on)
//   wordpress-tests            php-stubs/wordpress-tests-stubs
//   woocommerce                php-stubs/woocommerce-stubs
//   woocommerce-subscriptions  php-stubs/woocommerce-subscriptions-stubs
//   acf-pro                    php-stubs/acf-pro-stubs
//   gravity-forms              php-stubs/gravity-forms-stubs
//   wp-cli                     php-stubs/wp-cli-stubs
//   facetwp                    php-stubs/facetwp-stubs
//   genesis                    php-stubs/genesis-stubs
//   buddypress                 gin0115/buddypress-stubs
//
// Example — a plugin that integrates with WooCommerce + ACF:
//
//   return PHP_Scoper_Helper::for_wordpress( 'woocommerce', 'acf-pro' )
//       ->prefix( 'PinkCrab\\Comment_Moderation\\Vendor' )
//       ...
//
// Or point at any other stub file directly with `->with( $path )`:
//
//   ->with( __DIR__ . '/stubs/my-third-party.php' )
return PHP_Scoper_Helper::for_wordpress()
	->prefix( 'PinkCrab\\Comment_Moderation\\Vendor' )
	->finders( $finders )
	->first_party_from_composer( __DIR__ . '/composer.json' )
	->with_namespaces( 'PinkCrab\\Comment_Moderation' )
	->config();
