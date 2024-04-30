<?php

/**
 * Application Migration Test_Migrations
 *
 * @group Application
 * @group Migration
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Tests\Application\Migration;

/**
 * Test_Migrations tests the migrations for the application.
 */
class Test_Migrations extends \WP_UnitTestCase {

	/**
	 * Deactivate the plugin after each test.
	 */
	public function tear_down(): void {
		deactivate_plugins( 'comment-moderation/comment-moderation.php' );
		uninstall_plugin( 'comment-moderation/comment-moderation.php' );

		// Clear the migration option key.
		delete_option( 'pinkcrab_migration_log' );
		parent::tear_down();
	}

	/**
	 * Get the rules table name.
	 *
	 * @return string
	 */
	protected function get_rules_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'comment_moderation_rules';
	}

	/**
	 * Check if the current database is MariaDB.
	 *
	 * @return boolean
	 */
	protected function is_mariadb(): bool {
		$result = $GLOBALS['wpdb']->get_row( 'SELECT VERSION() AS version', ARRAY_A );
        return stripos( $result['version'], 'MariaDB' ) !== false;
	}

	/**
	 * @testdox When the plugin is activated, the rules table should be created.
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rules_table_is_created_on_activation() {
		// Activate the plugin.
		activate_plugin( 'comment-moderation/comment-moderation.php' );

		$table_name = $GLOBALS['wpdb']->prefix . 'comment_moderation_rules';
		$columns    = $GLOBALS['wpdb']->get_results( "SHOW COLUMNS FROM {$table_name};" );

		// If we have any columns, then table has been created.
		$this->assertNotEmpty( $columns );
	}

	/**
	 * @testdox When the plugin is uninstalled, the rules table should be dropped.
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rules_table_is_dropped_on_uninstall() {

		// Suppress wpdb error.
		$GLOBALS['wpdb']->suppress_errors();

		$table_name = $GLOBALS['wpdb']->prefix . 'comment_moderation_rules';

		// Activate & Deactivate the plugin.
		activate_plugin( 'comment-moderation/comment-moderation.php' );
		deactivate_plugins( 'comment-moderation/comment-moderation.php' );

		// Uninstall the plugin.
		uninstall_plugin( 'comment-moderation/comment-moderation.php' );

		// If we have no columns, then table has been dropped.
		$columns = $GLOBALS['wpdb']->get_results( "SHOW COLUMNS FROM {$table_name};" );
		$this->assertEmpty( $columns );
	}

	/**
	 * @testdox [MIGRATION 001] When the migration is run, the columns and there properties should be correct.
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_migration_001() {
		// Suppress wpdb error.
		$GLOBALS['wpdb']->suppress_errors();

		// Activate the plugin.
		activate_plugin( 'comment-moderation/comment-moderation.php' );

		// Get the columns.
		$table_name = $this->get_rules_table_name();
		$columns    = $GLOBALS['wpdb']->get_results( "SHOW COLUMNS FROM {$table_name};" );

		$expected = array(
			'id'           => array(
				'Field'   => 'id',
				'Type'    => $this->is_mariadb() ? 'int(11) unsigned' : 'int unsigned',
				'Null'    => 'NO',
				'Key'     => 'PRI',
				'Default' => null,
				'Extra'   => 'auto_increment',
			),
			'rule_name'    => array(
				'Field'   => 'rule_name',
				'Type'    => 'text',
				'Null'    => 'YES',
				'Key'     => '',
				'Default' => null,
				'Extra'   => '',
			),
			'rule_type'    => array(
				'Field'   => 'rule_type',
				'Type'    => 'text',
				'Null'    => 'YES',
				'Key'     => '',
				'Default' => null,
				'Extra'   => '',
			),
			'rule_value'   => array(
				'Field'   => 'rule_value',
				'Type'    => 'text',
				'Null'    => 'YES',
				'Key'     => '',
				'Default' => null,
				'Extra'   => '',
			),
			'rule_enabled' => array(
				'Field'   => 'rule_enabled',
				'Type'    => $this->is_mariadb() ? 'int(11)' : 'int',
				'Null'    => 'NO',
				'Key'     => '',
				'Default' => '1',
				'Extra'   => '',
			),
			'fields'       => array(
				'Field'   => 'fields',
				'Type'    => 'longtext',
				'Null'    => 'NO',
				'Key'     => '',
				'Extra'   => '',
			),
			'created'      => array(
				'Field' => 'created',
				'Type'  => 'timestamp',
				'Null'  => 'NO',
				'Key'   => '',
				'Extra' => '',
			),
			'updated'      => array(
				'Field' => 'updated',
				'Type'  => 'timestamp',
				'Null'  => 'NO',
				'Key'   => '',
				'Extra' => '',
			),
		);

		// Iterate over the columns and check the properties.
		foreach ( $columns as $column ) {
			$this->assertArrayHasKey( $column->Field, $expected );
			$this->assertEquals( $expected[ $column->Field ]['Field'], $column->Field );
			$this->assertEquals( $expected[ $column->Field ]['Type'], $column->Type );
		}
	}
}
