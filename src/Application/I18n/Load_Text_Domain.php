<?php
/**
 * Load_Text_Domain — loads the plugin's translation files.
 *
 * WordPress 6.7 changed the load timing rules for plugin text domains: any
 * translation call (`__()`, `_e()`, etc.) made before `init` triggers a
 * `_load_textdomain_just_in_time` doing-it-wrong notice. Loading the domain
 * on `init` at priority 10 keeps the plugin compliant with that contract
 * while still happening before the admin / front-end routes run their
 * translatable strings.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Application\I18n
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Application\I18n;

use PinkCrab\Loader\Hook_Loader;
use PinkCrab\Perique\Interfaces\Hookable;

/**
 * Hookable that registers `load_plugin_textdomain()` on `init`.
 */
final class Load_Text_Domain implements Hookable {

	/**
	 * Register the loader on `init` at priority 10. WP 6.7+ requires that
	 * `load_plugin_textdomain()` is called no earlier than `init` to avoid
	 * the `_load_textdomain_just_in_time` notice.
	 *
	 * @param Hook_Loader $loader Perique hook loader.
	 *
	 * @return void
	 */
	public function register( Hook_Loader $loader ): void {
		// Positional order follows Hook_Loader::action — handle, callback, args, priority.
		$loader->action( 'init', array( $this, 'load' ), 0, 10 );
	}

	/**
	 * Load the plugin's translation files from `languages/`.
	 *
	 * @return void
	 */
	public function load(): void {
		load_plugin_textdomain(
			'pinkcrab-comment-moderation',
			false,
			dirname( PINKCRAB_COMMENT_MODERATION_BASENAME ) . '/languages'
		);
	}
}
