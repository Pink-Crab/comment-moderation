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

require_once __DIR__ . '/vendor/autoload.php';
( new App_Factory( __DIR__ ) )
	->default_setup()
	->di_rules( require __DIR__ . '/config/dependencies.php' )
	->app_config( require __DIR__ . '/config/settings.php' )
	->registration_classes( require __DIR__ . '/config/registration.php' )
	->boot();
