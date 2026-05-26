<?php
/**
 * Plugin helper functions.
 *
 * Loaded BEFORE perique-bootstrap.php so every helper defined here is
 * available to classes in `src/`, the three `config/*.php` files, and any
 * code hooked later.
 *
 * Add your own prefixed helpers here. Prefix EVERY function with
 * `pinkcrab_comment_moderation_` so phpcs's PrefixAllGlobals rule passes.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
