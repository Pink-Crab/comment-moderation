<?php

/**
 * Registers all classes that are auto-loaded by the plugin.
 *
 * @return array<string>
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

use PinkCrab\Comment_Moderation\Admin\Rules_Page;

return array(
    Rules_Page::class,
);
