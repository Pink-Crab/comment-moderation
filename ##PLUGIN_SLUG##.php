<?php
/**
 * Plugin bootstrap file.
 *
 * @since   ##PLUGIN_VERSION##
 * @author  ##AUTHOR_NAME##
 * @license ##LICENSE##
 *
 * @wordpress-plugin
 * Plugin Name:       ##PLUGIN_NAME##
 * Plugin URI:        ##PLUGIN_URI##
 * Description:       ##PLUGIN_DESCRIPTION##
 * Version:           ##PLUGIN_VERSION##
 * Requires at least: ##MIN_WP_VERSION##
 * Requires PHP:      ##MIN_PHP_VERSION##
 * Author:            ##AUTHOR_NAME##
 * Author URI:        ##AUTHOR_URI##
 * License:           ##LICENSE##
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ##TEXT_DOMAIN##
 * Domain Path:       /languages
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Plugin constants.
// ---------------------------------------------------------------------------

define( '##CONSTANT_PREFIX##BASENAME', plugin_basename( __FILE__ ) );
define( '##CONSTANT_PREFIX##PATH',     plugin_dir_path( __FILE__ ) );
define( '##CONSTANT_PREFIX##URL',      plugin_dir_url( __FILE__ ) );
define( '##CONSTANT_PREFIX##VERSION',  '##PLUGIN_VERSION##' );
define(
	'##CONSTANT_PREFIX##MINIMUM_VERSIONS',
	array(
		'wp'  => '##MIN_WP_VERSION##',
		'php' => '##MIN_PHP_VERSION##',
	)
);

// ---------------------------------------------------------------------------
// Pre-autoload requirement checks. Inline rather than in a separate
// functions-bootstrap.php: these run BEFORE composer's autoloader is required,
// so they cannot rely on any class in src/.
// ---------------------------------------------------------------------------

// PHP version.
if ( version_compare( PHP_VERSION, '##MIN_PHP_VERSION##', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: required PHP version, 3: current PHP version */
						__( '%1$s requires PHP %2$s or higher. You are running %3$s.', '##TEXT_DOMAIN##' ),
						'##PLUGIN_NAME##',
						'##MIN_PHP_VERSION##',
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

// WordPress version.
global $wp_version;
if ( isset( $wp_version ) && version_compare( $wp_version, '##MIN_WP_VERSION##', '<' ) ) {
	add_action(
		'admin_notices',
		static function () use ( $wp_version ): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: required WP version, 3: current WP version */
						__( '%1$s requires WordPress %2$s or higher. You are running %3$s.', '##TEXT_DOMAIN##' ),
						'##PLUGIN_NAME##',
						'##MIN_WP_VERSION##',
						$wp_version
					)
				)
			);
		}
	);
	return;
}

// Composer autoloader.
if ( ! is_file( ##CONSTANT_PREFIX##PATH . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: plugin name */
						__( '%s is missing its composer dependencies. Run `composer install` from the plugin directory.', '##TEXT_DOMAIN##' ),
						'##PLUGIN_NAME##'
					)
				)
			);
		}
	);
	return;
}

// ---------------------------------------------------------------------------
// Boot.
// ---------------------------------------------------------------------------

require_once ##CONSTANT_PREFIX##PATH . 'vendor/autoload.php';
require_once ##CONSTANT_PREFIX##PATH . 'functions.php';
require_once ##CONSTANT_PREFIX##PATH . 'perique-bootstrap.php';
