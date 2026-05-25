<?php
/**
 * End-to-end activation test for the initial rules-table migration.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Integration\Migration
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Integration\Migration;

use WP_UnitTestCase;

/**
 * Proves that, by the time the test suite is running, the plugin's
 * activation lifecycle has already created the `{$wpdb->prefix}pccm_rules`
 * table through the Perique migration runner, with the columns the
 * rebuild spec requires.
 *
 * `tests/bootstrap.php` runs `activate_plugin()` inside `muplugins_loaded`,
 * so any migration registered on `Perique_Migrations` should have run
 * before this test executes.
 *
 * @group integration
 */
class Test_Comment_Rule_001_Activation extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to find the pccm_rules custom table in the database after the plugin activates
	 */
	public function test_table_exists_after_activation(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'pccm_rules';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- live integration check.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $found, 'Expected pccm_rules table to be created on plugin activation.' );
	}

	/**
	 * @testdox It should be possible to confirm the pccm_rules table holds every column the rebuild spec requires
	 */
	public function test_table_has_expected_columns(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'pccm_rules';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- live integration check; table name comes from a trusted alias.
		$rows = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );

		$this->assertNotEmpty( $rows, 'DESCRIBE returned no rows — table missing.' );

		$columns = array_column( $rows, 'Field' );

		foreach ( array(
			'id',
			'type',
			'name',
			'description',
			'payload',
			'comment_parts',
			'response',
			'times_used',
			'last_used',
			'last_updated',
		) as $expected ) {
			$this->assertContains( $expected, $columns, "Missing column: {$expected}" );
		}
	}
}
