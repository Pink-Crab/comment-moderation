<?php
/**
 * Perique App_Config values.
 *
 * Returns an associative array consumed by `App_Factory::app_config()`.
 * Reachable from any class via `PinkCrab\Perique\Application\App_Config` DI.
 * See the Perique docs for the full list of supported top-level keys
 * (path, url, post_types, taxonomies, meta, db_tables, namespaces, plugin).
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

return array(

	'path'       => array(
		'plugin' => PINKCRAB_COMMENT_MODERATION_PATH,
		'assets' => PINKCRAB_COMMENT_MODERATION_PATH . 'assets/build',
		'view'   => PINKCRAB_COMMENT_MODERATION_PATH . 'views',
	),

	'url'        => array(
		'plugin' => PINKCRAB_COMMENT_MODERATION_URL,
		'assets' => PINKCRAB_COMMENT_MODERATION_URL . 'assets/build',
		'view'   => PINKCRAB_COMMENT_MODERATION_URL . 'views',
	),

	'plugin'     => array(
		'version' => PINKCRAB_COMMENT_MODERATION_VERSION,
	),

	'namespaces' => array(
		'rest'  => 'pinkcrab-comment-moderation/v1',
		'cache' => 'pinkcrab-comment-moderation',
	),

	// Aliases for post / user / term meta keys. Use App_Config::POST_META etc.
	// as the inner keys. Inject `App_Config` then read with
	// `$config->post_meta( 'alias' )`.
	'meta'       => array(
		// App_Config::POST_META => array( 'my_alias' => 'pinkcrab_comment_moderation_my_meta_key' ),
	),

	// Custom database table aliases. Read with `$config->db_tables( 'alias' )`.
	'db_tables'  => array(
		// 'my_table' => $GLOBALS['wpdb']->prefix . 'pinkcrab_comment_moderation_my_table',
	),

	// Custom post type aliases. Read with `$config->post_types( 'alias' )`.
	'post_types' => array(),

	// Custom taxonomy aliases. Read with `$config->taxonomies( 'alias' )`.
	'taxonomies' => array(),
);
