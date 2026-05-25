<?php
/**
 * Plugin bootstrap file.
 *
 * @since   0.1.0
 * @author  PinkCrab
 * @license GPLv3
 *
 * @wordpress-plugin
 * Plugin Name:       PinkCrab Comment Moderation
 * Plugin URI:        https://github.com/Pink-Crab/comment-moderation
 * Description:       Rule-based comment moderation engine for WordPress.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            PinkCrab
 * Author URI:        https://github.com/Pink-Crab
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       pinkcrab-comment-moderation
 * Domain Path:       /languages
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Plugin constants.
// ---------------------------------------------------------------------------

define( 'PINKCRAB_COMMENT_MODERATION_BASENAME', plugin_basename( __FILE__ ) );
define( 'PINKCRAB_COMMENT_MODERATION_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PINKCRAB_COMMENT_MODERATION_URL',      plugin_dir_url( __FILE__ ) );
define( 'PINKCRAB_COMMENT_MODERATION_VERSION',  '0.1.0' );
define(
	'PINKCRAB_COMMENT_MODERATION_MINIMUM_VERSIONS',
	array(
		'wp'  => '6.0',
		'php' => '8.0',
	)
);

// ---------------------------------------------------------------------------
// Pre-autoload requirement checks. Inline rather than in a separate
// functions-bootstrap.php: these run BEFORE composer's autoloader is required,
// so they cannot rely on any class in src/.
// ---------------------------------------------------------------------------

// PHP version.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: required PHP version, 3: current PHP version */
						__( '%1$s requires PHP %2$s or higher. You are running %3$s.', 'pinkcrab-comment-moderation' ),
						'PinkCrab Comment Moderation',
						'8.0',
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
if ( isset( $wp_version ) && version_compare( $wp_version, '6.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function () use ( $wp_version ): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: required WP version, 3: current WP version */
						__( '%1$s requires WordPress %2$s or higher. You are running %3$s.', 'pinkcrab-comment-moderation' ),
						'PinkCrab Comment Moderation',
						'6.0',
						$wp_version
					)
				)
			);
		}
	);
	return;
}

// Composer autoloader.
if ( ! is_file( PINKCRAB_COMMENT_MODERATION_PATH . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: plugin name */
						__( '%s is missing its composer dependencies. Run `composer install` from the plugin directory.', 'pinkcrab-comment-moderation' ),
						'PinkCrab Comment Moderation'
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

require_once PINKCRAB_COMMENT_MODERATION_PATH . 'vendor/autoload.php';
require_once PINKCRAB_COMMENT_MODERATION_PATH . 'functions.php';

( new \PinkCrab\Comment_Moderation\Plugin_Bootstrap( PINKCRAB_COMMENT_MODERATION_PATH ) )->boot();
