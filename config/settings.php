<?php

/**
 * Returns the plugin settings.
 *
 * @return array<string, mixed>
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

$pc_plugin_data = get_plugin_data( dirname( __DIR__, 1 ) . '/comment-moderation.php' );

global $wpdb;

return array(
	'plugin'    => array(
		'version' => esc_attr( $pc_plugin_data['Version'] ),
	),
	'db_tables' => array(
		'rules' => $wpdb->prefix . 'comment_moderation_rules',
	),
);
