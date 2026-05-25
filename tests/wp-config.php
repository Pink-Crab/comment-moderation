<?php
/**
 * Test-suite wp-config.
 *
 * Loaded by wp-phpunit via the WP_PHPUNIT__TESTS_CONFIG env var (set in
 * phpunit.xml.dist). Defines the constants WordPress expects; never
 * `require wp-settings.php` here — wp-phpunit's bootstrap does that.
 *
 * DB credentials come from env vars (typically populated from tests/.env;
 * see tests/.env_sample for the template).
 *
 * @package ##NAMESPACE##\Tests
 */

declare( strict_types = 1 );

// WordPress lives at <plugin-root>/wordpress/ (installed by roots/wordpress
// via the roots/wordpress-core-installer composer plugin).
define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );

// WP_PLUGIN_DIR points at the directory that CONTAINS our plugin folder, so
// activate_plugin('##PLUGIN_SLUG##/##PLUGIN_SLUG##.php') in tests/bootstrap.php
// resolves to this plugin's real on-disk location — not the wp-phpunit
// install's empty plugins folder.
define( 'WP_PLUGIN_DIR', dirname( __DIR__, 2 ) );

// Database. CI usually injects env vars directly; locally a tests/.env supplies
// them via vlucas/phpdotenv (loaded in tests/bootstrap.php).
define( 'DB_NAME',     getenv( 'WP_DB_NAME' )     ?: 'wordpress_test' );
define( 'DB_USER',     getenv( 'WP_DB_USER' )     ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASS' )     ?: '' );
define( 'DB_HOST',     getenv( 'WP_DB_HOST' )     ?: '127.0.0.1' );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );

$table_prefix = getenv( 'WP_TESTS_TABLE_PREFIX' ) ?: 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL',  'admin@example.org' );
define( 'WP_TESTS_TITLE',  '##PLUGIN_NAME## Tests' );

define( 'WP_PHP_BINARY', 'php' );

// Salts — irrelevant for tests, WP demands them.
define( 'AUTH_KEY',         'phpunit-key' );
define( 'SECURE_AUTH_KEY',  'phpunit-key' );
define( 'LOGGED_IN_KEY',    'phpunit-key' );
define( 'NONCE_KEY',        'phpunit-key' );
define( 'AUTH_SALT',        'phpunit-salt' );
define( 'SECURE_AUTH_SALT', 'phpunit-salt' );
define( 'LOGGED_IN_SALT',   'phpunit-salt' );
define( 'NONCE_SALT',       'phpunit-salt' );

define( 'WPLANG',   '' );
define( 'WP_DEBUG', true );
