<?php
/**
 * Perique App_Config values.
 *
 * Returns an associative array consumed by `App_Factory::app_config()`.
 * Reachable from any class via `PinkCrab\Perique\Application\App_Config` DI.
 * See the Perique docs for the full list of supported top-level keys
 * (path, url, post_types, taxonomies, meta, db_tables, namespaces, plugin).
 *
 * @since   ##PLUGIN_VERSION##
 * @package ##NAMESPACE##
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

return array(

	'path' => array(
		'plugin' => ##CONSTANT_PREFIX##PATH,
		'assets' => ##CONSTANT_PREFIX##PATH . 'assets/build',
		'view'   => ##CONSTANT_PREFIX##PATH . 'views',
	),

	'url' => array(
		'plugin' => ##CONSTANT_PREFIX##URL,
		'assets' => ##CONSTANT_PREFIX##URL . 'assets/build',
		'view'   => ##CONSTANT_PREFIX##URL . 'views',
	),

	'plugin' => array(
		'version' => ##CONSTANT_PREFIX##VERSION,
	),

	'namespaces' => array(
		'rest'  => '##REST_NAMESPACE##',
		'cache' => '##PLUGIN_SLUG##',
	),

	// Aliases for post / user / term meta keys. Use App_Config::POST_META etc.
	// as the inner keys. Inject `App_Config` then read with
	// `$config->post_meta( 'alias' )`.
	'meta' => array(
		// App_Config::POST_META => array( 'my_alias' => '##FUNCTION_PREFIX##my_meta_key' ),
	),

	// Custom database table aliases. Read with `$config->db_tables( 'alias' )`.
	'db_tables' => array(
		// 'my_table' => $GLOBALS['wpdb']->prefix . '##FUNCTION_PREFIX##my_table',
	),

	// Custom post type aliases. Read with `$config->post_types( 'alias' )`.
	'post_types' => array(),

	// Custom taxonomy aliases. Read with `$config->taxonomies( 'alias' )`.
	'taxonomies' => array(),
);
