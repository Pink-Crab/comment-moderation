<?php

/**
 * Plugin Name: Comment Moderation
 * Description: Advanced comment moderation for auto-approving, blacklisting, and more.
 * Version: 0.1.0
 * Author: PinkCrab, Glynn Quelch<glynn@pinkcrab.co.uk>
 * Author URI: https://github.com/gin0115
 * Plugin URI: https://github.com/Pink-Crab/comment-moderation
 * Text Domain: pc-cm
 * Domain Path: /languages
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

use PinkCrab\Perique\Application\App_Factory;
use PinkCrab\Plugin_Lifecycle\Plugin_Life_Cycle;
use PinkCrab\Perique_Admin_Menu\Module\Admin_Menu;
use PinkCrab\Perique\Migration\Module\Perique_Migrations;
use PinkCrab\Comment_Moderation\Migration\Comment_Rule_001;

require_once __DIR__ . '/vendor/autoload.php';

// return;
( new App_Factory( __DIR__ ) )
->default_setup()
->module( Plugin_Life_Cycle::class )
->module(
	Perique_Migrations::class,
	function ( Perique_Migrations $module ) {
		// The Migrations.
		$module->add_migration( Comment_Rule_001::class );
		return $module;
	}
)
->module( Admin_Menu::class )
->di_rules( require __DIR__ . '/config/dependencies.php' )
->app_config( require __DIR__ . '/config/settings.php' )
->registration_classes( require __DIR__ . '/config/registration.php' )
->boot();
