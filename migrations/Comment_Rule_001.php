<?php

/**
 * Comment Rule 001
 *
 * The first migration for the comment moderation rules.
 *
 * Created 29th April 2024
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Migration;

use PinkCrab\Table_Builder\Schema;
use PinkCrab\Perique\Migration\Migration;

/**
 * Comment Rule Migration 001
 */
class Comment_Rule_001 extends Migration {


	/**
	 * Gets the table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'comment_moderation_rules';
	}

	/**
	 * Defines the table schema.
	 *
	 * @param Schema $schema The schema builder.
	 *
	 * @return void
	 */
	public function schema( Schema $schema ): void {
		$schema->column( 'id' )->unsigned_int( 11 )->auto_increment();
		$schema->index( 'id' )->primary();
		$schema->column( 'rule_name' )->text()->nullable();
		$schema->column( 'rule_type' )->text()->nullable();
		$schema->column( 'rule_value' )->text()->nullable();
		$schema->column( 'rule_enabled' )->int()->default( 1 );
		$schema->column( 'fields' )->json();
		$schema->column( 'created' )->timestamp()->default( 'CURRENT_TIMESTAMP' );
		$schema->column( 'updated' )->timestamp()->default( 'CURRENT_TIMESTAMP' );
	}

	/**
	 * Ensure the table is dropped on uninstall.
	 *
	 * @return boolean
	 */
	public function drop_on_uninstall(): bool {
		return true;
	}
}
