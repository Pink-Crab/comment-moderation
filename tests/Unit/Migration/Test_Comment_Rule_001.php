<?php
/**
 * Comment_Rule_001 migration unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Migration
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Migration;

use PinkCrab\Comment_Moderation\Migration\Comment_Rule_001;
use PinkCrab\Perique\Application\App_Config;
use PinkCrab\Perique\Migration\Migration;
use PinkCrab\Table_Builder\Column;
use PinkCrab\Table_Builder\Schema;
use WP_UnitTestCase;

/**
 * Proves the initial migration creates the `pccm_rules` table with the
 * columns the rebuild spec requires, declares itself drop-on-uninstall,
 * and is plugged into the Perique migration runner from Plugin_Bootstrap.
 *
 * Constructs the migration directly with a real App_Config — App_Config is
 * final in Perique ^2.1, so a real instance is the documented way to assert
 * its behaviour without mocks.
 *
 * @group unit
 */
class Test_Comment_Rule_001 extends WP_UnitTestCase {

	/**
	 * Build a real App_Config that resolves the `rules` db_tables alias to
	 * a deterministic table name, independent of the live $wpdb prefix.
	 */
	private function make_migration( string $prefix = 'wp_' ): Comment_Rule_001 {
		$app_config = new App_Config(
			array(
				'db_tables' => array(
					'rules' => $prefix . 'pccm_rules',
				),
			)
		);
		return new Comment_Rule_001( $app_config );
	}

	/**
	 * @testdox It should be possible to confirm Comment_Rule_001 extends the Perique Migration base class
	 */
	public function test_extends_perique_migration(): void {
		$this->assertInstanceOf( Migration::class, $this->make_migration() );
	}

	/**
	 * @testdox It should be possible to confirm the migration targets the prefixed pccm_rules table
	 */
	public function test_table_name_uses_db_tables_alias(): void {
		$this->assertSame( 'wp_pccm_rules', $this->make_migration()->get_table_name() );
		$this->assertSame( 'foo_pccm_rules', $this->make_migration( 'foo_' )->get_table_name() );
	}

	/**
	 * @testdox It should be possible to confirm the table is dropped on plugin uninstall
	 */
	public function test_drops_on_uninstall(): void {
		$this->assertTrue( $this->make_migration()->drop_on_uninstall() );
	}

	/**
	 * @testdox It should be possible to confirm the schema declares every column the rebuild spec requires
	 */
	public function test_schema_defines_expected_columns(): void {
		$schema = $this->make_migration()->get_schema();
		$names  = array_map(
			static fn( Column $c ): string => $c->get_name(),
			$schema->get_columns()
		);

		$this->assertSame(
			array(
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
			),
			array_values( $names )
		);
	}

	/**
	 * @testdox It should be possible to confirm id is an unsigned bigint primary auto-increment column
	 */
	public function test_id_column_shape(): void {
		$id = $this->column( 'id' );

		$this->assertSame( 'bigint', $id->get_type() );
		$this->assertTrue( $id->is_unsigned() );
		$this->assertTrue( $id->is_auto_increment() );
		$this->assertFalse( $id->is_nullable() );

		$indexes = $this->make_migration()->get_schema()->get_indexes();
		$this->assertCount( 1, $indexes );
		$this->assertSame( 'id', $indexes[0]->get_column() );
		$this->assertTrue( $indexes[0]->is_primary() );
	}

	/**
	 * @testdox It should be possible to confirm type is a non-null varchar(32)
	 */
	public function test_type_column_shape(): void {
		$col = $this->column( 'type' );

		$this->assertSame( 'varchar', $col->get_type() );
		$this->assertSame( 32, $col->get_length() );
		$this->assertFalse( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm name is a nullable varchar(190)
	 */
	public function test_name_column_shape(): void {
		$col = $this->column( 'name' );

		$this->assertSame( 'varchar', $col->get_type() );
		$this->assertSame( 190, $col->get_length() );
		$this->assertTrue( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm description is a nullable text column
	 */
	public function test_description_column_shape(): void {
		$col = $this->column( 'description' );

		$this->assertSame( 'text', $col->get_type() );
		$this->assertTrue( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm payload is a non-null longtext column
	 */
	public function test_payload_column_shape(): void {
		$col = $this->column( 'payload' );

		$this->assertSame( 'longtext', $col->get_type() );
		$this->assertFalse( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm comment_parts is a non-null varchar(190)
	 */
	public function test_comment_parts_column_shape(): void {
		$col = $this->column( 'comment_parts' );

		$this->assertSame( 'varchar', $col->get_type() );
		$this->assertSame( 190, $col->get_length() );
		$this->assertFalse( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm response is a non-null varchar(16)
	 */
	public function test_response_column_shape(): void {
		$col = $this->column( 'response' );

		$this->assertSame( 'varchar', $col->get_type() );
		$this->assertSame( 16, $col->get_length() );
		$this->assertFalse( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm times_used is an unsigned bigint defaulting to zero
	 */
	public function test_times_used_column_shape(): void {
		$col = $this->column( 'times_used' );

		$this->assertSame( 'bigint', $col->get_type() );
		$this->assertTrue( $col->is_unsigned() );
		$this->assertFalse( $col->is_nullable() );
		$this->assertSame( '0', (string) $col->get_default() );
	}

	/**
	 * @testdox It should be possible to confirm last_used is a nullable datetime
	 */
	public function test_last_used_column_shape(): void {
		$col = $this->column( 'last_used' );

		$this->assertSame( 'datetime', $col->get_type() );
		$this->assertTrue( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm last_updated is a non-null datetime
	 */
	public function test_last_updated_column_shape(): void {
		$col = $this->column( 'last_updated' );

		$this->assertSame( 'datetime', $col->get_type() );
		$this->assertFalse( $col->is_nullable() );
	}

	/**
	 * @testdox It should be possible to confirm the migration is registered with the Perique migration runner from Plugin_Bootstrap
	 */
	public function test_bootstrap_wires_migration_runner(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Plugin_Bootstrap.php'
		);

		$this->assertStringContainsString( 'Perique_Migrations::class', $bootstrap );
		$this->assertStringContainsString( 'Plugin_Life_Cycle::class', $bootstrap );
		$this->assertStringContainsString( 'Comment_Rule_001::class', $bootstrap );
		$this->assertStringContainsString( 'pinkcrab_comment_moderation_migrations', $bootstrap );
	}

	/**
	 * Helper — fetch a column from the migration's schema by name.
	 */
	private function column( string $name ): Column {
		$schema  = $this->make_migration()->get_schema();
		$columns = $schema->get_columns();

		$this->assertArrayHasKey( $name, $columns, "Column {$name} missing from schema." );

		return $columns[ $name ];
	}
}
