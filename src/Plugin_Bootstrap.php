<?php
/**
 * Plugin_Bootstrap — assembles and boots the Perique application.
 *
 * Invoked once, from `pinkcrab-comment-moderation.php`, after the composer
 * autoloader is available. Replaces the procedural perique-bootstrap.php in
 * the scaffold so the entry sequence has a single, testable seam.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation;

use PinkCrab\Perique\Application\App_Factory;

/**
 * Boots the Perique application using the three config arrays in `config/`.
 *
 * The booted App is intentionally not stored on the instance. Inside the DI
 * tree, classes inject what they need on the constructor (App, App_Config,
 * View, DI_Container, Hook_Loader are all pre-registered). Outside the DI
 * tree (rare — e.g. an integration callback added by another plugin), use
 * the documented static escape hatch: `App::make( Some_Service::class )`.
 */
final class Plugin_Bootstrap {

	/**
	 * Absolute plugin directory path (trailing slash), used to resolve the
	 * config files and seed Perique's path resolution.
	 */
	private string $plugin_path;

	/**
	 * Construct the bootstrap with the resolved plugin path.
	 *
	 * @param string $plugin_path Absolute plugin directory path (trailing slash).
	 */
	public function __construct( string $plugin_path ) {
		$this->plugin_path = $plugin_path;
	}

	/**
	 * Build the Perique App_Factory chain and boot it.
	 *
	 * @return void
	 */
	public function boot(): void {
		( new App_Factory( $this->plugin_path ) )
			->default_setup()
			->di_rules( require $this->plugin_path . 'config/di.php' )
			->app_config( require $this->plugin_path . 'config/settings.php' )
			->registration_classes( require $this->plugin_path . 'config/registration.php' )
			->boot();
	}
}
