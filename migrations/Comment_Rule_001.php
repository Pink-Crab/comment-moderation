<?php
/**
 * Initial schema for the comment moderation rules table.
 *
 * Creates `{$wpdb->prefix}pccm_rules`, the single store for every rule the
 * administrator defines on the management screen. The table holds the rule's
 * type, the (type-specific) pattern payload as JSON, which comment parts it
 * inspects, the response to apply on a match, and the auto-maintained usage
 * statistics. Dropped on uninstall so an admin who removes the plugin leaves
 * no orphan tables behind.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Migration
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Migration;

use PinkCrab\Perique\Application\App_Config;
use PinkCrab\Perique\Migration\Migration;
use PinkCrab\Table_Builder\Schema;

/**
 * Initial migration — creates the `pccm_rules` custom table.
 */
final class Comment_Rule_001 extends Migration {

	/**
	 * App config — used to resolve the prefixed table name from the
	 * `rules` alias registered in config/settings.php.
	 *
	 * @var App_Config
	 */
	private App_Config $app_config;

	/**
	 * Construct with the injected App_Config so the table name stays in one
	 * place (config/settings.php) and is reachable to any other class that
	 * needs to query the table later.
	 *
	 * @param App_Config $app_config Perique app config.
	 */
	public function __construct( App_Config $app_config ) {
		$this->app_config = $app_config;
		parent::__construct();
	}

	/**
	 * Fully-qualified table name, e.g. `wp_pccm_rules`.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return $this->app_config->db_tables( 'rules' );
	}

	/**
	 * Column definitions for the rules table.
	 *
	 * @param Schema $schema Table builder schema.
	 *
	 * @return void
	 */
	public function schema( Schema $schema ): void {
		$schema->column( 'id' )->unsigned_big( 20 )->auto_increment();
		$schema->column( 'type' )->varchar( 32 );
		$schema->column( 'name' )->varchar( 190 )->nullable( true );
		$schema->column( 'description' )->type( 'text' )->nullable( true );
		$schema->column( 'payload' )->type( 'longtext' );
		$schema->column( 'comment_parts' )->varchar( 190 );
		$schema->column( 'response' )->varchar( 16 );
		$schema->column( 'times_used' )->unsigned_big( 20 )->default( '0' );
		$schema->column( 'last_used' )->datetime()->nullable( true );
		$schema->column( 'last_updated' )->datetime();

		$schema->index( 'id' )->primary();
	}

	/**
	 * Drop the table when the plugin is uninstalled — rule data is plugin-
	 * specific and meaningless without the engine that evaluates it.
	 *
	 * @return boolean
	 */
	public function drop_on_uninstall(): bool {
		return true;
	}
}
