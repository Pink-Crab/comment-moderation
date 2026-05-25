<?php
/**
 * Perique App_Factory wiring.
 *
 * Required by the main plugin file AFTER composer's autoloader. Builds the
 * Perique application from the three config arrays in `config/` and boots it.
 *
 * Modules are added with `->module( SomeModule::class )` between
 * `registration_classes(...)` and `boot()`. The scaffold ships core-only;
 * see https://perique.info/ and config/registration.php for the catalogue
 * (registerables, route, admin-menu, settings-page, migrations, etc.).
 *
 * @since   ##PLUGIN_VERSION##
 * @package ##NAMESPACE##
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

use PinkCrab\Perique\Application\App_Factory;

( new App_Factory( ##CONSTANT_PREFIX##PATH ) )
	->default_setup()
	->di_rules( require ##CONSTANT_PREFIX##PATH . 'config/di.php' )
	->app_config( require ##CONSTANT_PREFIX##PATH . 'config/settings.php' )
	->registration_classes( require ##CONSTANT_PREFIX##PATH . 'config/registration.php' )
	// Add optional modules here, e.g.:
	// ->module( \PinkCrab\Registerables\Module\Registerable::class )
	// ->module( \PinkCrab\Route\Module\Route::class )
	// ->module(
	//     \PinkCrab\Plugin_Lifecycle\Module\Plugin_Life_Cycle::class,
	//     fn( $m ) => $m->plugin_base_file( ##CONSTANT_PREFIX##PATH . '##PLUGIN_SLUG##.php' )
	// )
	->boot();

// The booted App is intentionally not stored anywhere here. Inside the DI
// tree, classes inject what they need on the constructor (App, App_Config,
// View, DI_Container, Hook_Loader are all pre-registered). Outside the DI
// tree (rare — e.g. an integration callback added by another plugin), use
// the documented static escape hatch: `App::make( Some_Service::class )`.
