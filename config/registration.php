<?php
/**
 * Perique Registration Class list.
 *
 * Returns an array of FQCNs consumed by `App_Factory::registration_classes()`.
 * Every class listed here is instantiated via the DI container on the `init`
 * hook and passed to the registered registration middleware.
 *
 * The default middleware shipped with `default_setup()` handles classes that
 * implement `PinkCrab\Perique\Interfaces\Hookable` — their `register()` method
 * is called with a `Hook_Loader`, which they use to register actions/filters/
 * shortcodes/ajax handlers.
 *
 * Adding optional modules (Registerables, Route, Admin_Menu, Settings_Page,
 * Migrations) registers additional middleware and lets you list the matching
 * class types here too (Post_Type, Route_Controller, Menu_Page, etc.).
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

return array(

	// Hookables (core middleware — no module required).
	\PinkCrab\Comment_Moderation\Presentation\Hook\Hello_World::class,

);
