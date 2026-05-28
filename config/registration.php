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

	// Loads the plugin's translation files on `init` at priority 10 — the
	// earliest hook WP 6.7+ allows for `load_plugin_textdomain()` without
	// emitting the `_load_textdomain_just_in_time` notice.
	\PinkCrab\Comment_Moderation\Application\I18n\Load_Text_Domain::class,

	// Invisible comment engine — hooks `pre_comment_approved` and diverts
	// matching comments to the rule's outcome (rebuild spec §9).
	\PinkCrab\Comment_Moderation\Application\Engine\Comment_Engine::class,

	// Optional Akismet co-operation (rebuild spec §10). No-op when Akismet
	// is missing or the `pccm_akismet_enabled` filter has been switched off
	// — both checks happen lazily inside the listener.
	\PinkCrab\Comment_Moderation\Application\Integration\Akismet\Akismet_Integration::class,

	// Settings → Comment Moderation admin page (rebuild spec §3). Prints
	// an Elm-mount div and localises REST/nonce data for the Elm app.
	\PinkCrab\Comment_Moderation\Presentation\Page\Admin_Page::class,

	// Admin AJAX endpoints behind the management screen (rebuild spec §3,
	// §6, §7, §8) — create / update / delete / clear / list / get.
	\PinkCrab\Comment_Moderation\Presentation\Ajax\Rule_Ajax_Controller::class,

);
