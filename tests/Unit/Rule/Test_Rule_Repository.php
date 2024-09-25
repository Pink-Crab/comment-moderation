<?php

/**
 * Unit tests for the Rule Repository
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Tests\Unit\Rule;

use DateTimeImmutable;
use DateTimeInterface;
use PinkCrab\Comment_Moderation\Rule\Rule;
use PinkCrab\Perique\Application\App_Config;
use PinkCrab\Comment_Moderation\Rule\Condition\Group;
use PinkCrab\Comment_Moderation\Rule\Rule_Repository;
use PinkCrab\Comment_Moderation\Tests\Tools\wpdb_logger;

/**
 * Test_Rule tests the Rule Repository.
 *
 * @group unit
 * @group rule
 */
class Test_Rule_Repository extends \WP_UnitTestCase {

	use wpdb_logger;

	private $logging_wpdb;
	private $app_config;

	public function set_up(): void {
		parent::set_up();
		$this->logging_wpdb = $this->get_wpdb_with_logger();
		$this->app_config   = new App_Config(
			array(
				'db_version' => '0.1.0',
				'db_tables'  => array(
					'rules' => 'test_table_name',
				),
			)
		);
	}

	public function tear_down(): void {
		self::clear_prepared_query_log();
		self::clear_query_log();
		self::clear_result();
		self::clear_insert_update_log();
		$this->logging_wpdb->last_error = '';

		parent::tear_down();
	}

	/**
	 * @testdox It should be possible to find a rule based on its ID. If we find a rule, it should be mapped to a Rule object.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::get
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::map_to_rule
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::table_name
	 */
	public function test_can_find_rule_by_id(): void {
		self::set_result(
			array(
				'id'           => 1,
				'name'    => 'name',
				'rule_enabled' => 1,
				'conditions'   => '{"conditions": [], "relationship": "any"}',
				'outcome'      => 'spam',
				'created'      => '2021-01-01 00:00:00',
				'updated'      => '2021-02-01 00:00:00',
			)
		);

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$rule = $repo->get( 1 );

		// Check the query was run against the correct table.
		$this->assertContains( 'SELECT * FROM test_table_name WHERE id = 1', self::get_query_log() );

		// Check the query was prepared correctly.
		$this->assertEquals( 'SELECT * FROM test_table_name WHERE id = %d', self::get_prepared_query_log()[0]->query );
		$this->assertEquals( 1, $this->get_prepared_query_log()[0]->args[0] );

		// Check we have a valid Rule object.
		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertEquals( 1, $rule->get_id() );
		$this->assertEquals( 'name', $rule->get_rule_name() );
		$this->assertTrue( $rule->get_rule_enabled() );
		$this->assertEquals( 'spam', $rule->get_outcome() );
		$this->assertEquals( '2021-01-01', $rule->get_created()->format( 'Y-m-d' ) );
		$this->assertEquals( '2021-02-01', $rule->get_updated()->format( 'Y-m-d' ) );

		$conditions = $rule->get_rule_conditions();

		$this->assertIsArray( $conditions );
		$this->assertCount( 1, $conditions );
		$group = $conditions[0];
		$this->assertInstanceOf( Group::class, $group );
		$this->assertTrue( $group->is_match_all() );
		$this->assertIsArray( $group->get_conditions() );
		$this->assertCount( 0, $group->get_conditions() );
	}

	/**
	 * @testdox When attempting to find a rule by its id, if its not in the database, null should be returned.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::get
	 */
	public function test_returns_null_if_rule_not_found(): void {
		self::set_result( null );

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$rule = $repo->get( 1 );

		// Check the query was run against the correct table.
		$this->assertContains( 'SELECT * FROM test_table_name WHERE id = 1', self::get_query_log() );

		// Check the query was prepared correctly.
		$this->assertEquals( 'SELECT * FROM test_table_name WHERE id = %d', self::get_prepared_query_log()[0]->query );
		$this->assertEquals( 1, $this->get_prepared_query_log()[0]->args[0] );

		// Check we have a valid Rule object.
		$this->assertNull( $rule );
	}

	/**
	 * @testdox It should be possible to get all the rules.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::get_all_rules
	 */
	public function test_can_get_all_rules(): void {
		self::set_result(
			array(
				array(
					'id'           => 1,
					'name'    => 'name',
					'rule_enabled' => 1,
					'conditions'       => '[{"conditions":[],"match_all":true}]',
					'outcome'      => 'spam',
					'created'      => '2021-01-01 00:00:00',
					'updated'      => '2021-02-01 00:00:00',
				),
				array(
					'id'           => 2,
					'name'    => 'name2',
					'rule_enabled' => 1,
					'conditions'       => '[{"conditions":[],"match_all":true}]',
					'outcome'      => 'spam',
					'created'      => '2021-01-01 00:00:00',
					'updated'      => '2021-02-01 00:00:00',
				),
			)
		);

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$rules = $repo->get_all_rules();

		// Check the query was run against the correct table.
		$this->assertContains( 'SELECT * FROM test_table_name', self::get_query_log() );

		// Check we have a valid Rule object.
		$this->assertCount( 2, $rules );
		$this->assertInstanceOf( Rule::class, $rules[0] );
		$this->assertInstanceOf( Rule::class, $rules[1] );

		$this->assertEquals( 1, $rules[0]->get_id() );
		$this->assertEquals( 2, $rules[1]->get_id() );
	}

	/**
	 * @testdox It should be possible to get a count of all the rules.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::count_all_rules
	 */
	public function test_can_count_all_rules(): void {
		self::set_result( 5 );

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$count = $repo->count_all_rules();

		// Check the query was run against the correct table.
		$this->assertContains( 'SELECT COUNT(*) FROM test_table_name', self::get_query_log() );

		// Check we have a valid Rule object.
		$this->assertEquals( 5, $count );
	}

	/**
	 * @testdox It should be possible to insert a rule to the database by calling upsert with a rule without an ID.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::upsert
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::insert_rule
	 */
	public function test_can_insert_rule_using_upsert(): void {
		// Returns the
		self::set_result( 1 );

		$rule = new Rule(
			null,
			'name',
			true,
			array( new Group( array(), true ) ),
			'spam',
		);

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$created_rule = $repo->upsert( $rule );
		
		// Check the fileds are correctly set.
		$this->assertEquals( 1, $created_rule->get_id() );
		$this->assertEquals( 'name', $created_rule->get_rule_name() );
		$this->assertTrue( $created_rule->get_rule_enabled() );
		$this->assertEquals( 'spam', $created_rule->get_outcome() );
		$this->assertInstanceOf(Group::class, $created_rule->get_rule_conditions()[0]);
		$this->assertInstanceOf( \DateTimeImmutable::class, $created_rule->get_created() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $created_rule->get_updated() );

		$log = self::get_insert_update_log();

		// Check the rule was run through the wpdb->insert method.
		$this->assertTrue( $log[0]->insert );
		$this->assertEquals( 'test_table_name', $log[0]->table );
		$this->assertEquals( 'name', $log[0]->rows['name'] );
		$this->assertEquals( 1, $log[0]->rows['rule_enabled'] );
		$this->assertEquals( '[{"conditions":[],"match_all":true}]', $log[0]->rows['conditions'] );
		$this->assertEquals( 'spam', $log[0]->rows['outcome'] );
		$this->assertInstanceOf( \DateTimeInterface::class, \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $log[0]->rows['created'] ) );
		$this->assertInstanceOf( \DateTimeInterface::class, \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $log[0]->rows['updated'] ) );

		// Check the value formats.
		$this->assertEquals( '%s', $log[0]->format[0] );
		$this->assertEquals( '%d', $log[0]->format[1] );
		$this->assertEquals( '%s', $log[0]->format[2] );
		$this->assertEquals( '%s', $log[0]->format[3] );
		$this->assertEquals( '%s', $log[0]->format[4] );
		$this->assertEquals( '%s', $log[0]->format[5] );

		// where and where_format should be empty as insert
		$this->assertEmpty( $log[0]->where );
		$this->assertEmpty( $log[0]->where_format );
	}

	/**
	 * @testdox When inserting a rule, if there is an issue an exception should be thrown with a meaningful message.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::upsert
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::insert_rule
	 */
	public function test_throws_exception_on_insert_error(): void {
		// Return false to simulate an error.
		self::set_result( false );

		$rule = new Rule(
			null,
			'name',
			true, 
			[],
			'spam',
		);

		$this->logging_wpdb->last_error = 'Error Message';

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Error Message/' );

		$repo->upsert( $rule );
	}

	/**
	 * @testdox It should be possible to update a rule to the database by calling upsert with a rule with an ID.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::upsert
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::update_rule
	 */
	public function test_can_update_rule_using_upsert(): void {
		// Returns the
		self::set_result( 10 );

		$rule = new Rule(
			10,
			'name',
			true,
			[new Group([], false)],
			'spam',
		);

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$created_rule = $repo->upsert( $rule );

		// Check the fileds are correctly set.
		$this->assertEquals( 10, $created_rule->get_id() );
		$this->assertEquals( 'name', $created_rule->get_rule_name() );
		$this->assertTrue( $created_rule->get_rule_enabled() );
		$this->assertEquals( 'spam', $created_rule->get_outcome() );
		$this->assertInstanceOf(Group::class, $created_rule->get_rule_conditions()[0]);
		// $this->assertFalse( $created_rule->get_rule_conditions()[0]->is_match_all() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $created_rule->get_created() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $created_rule->get_updated() );

		$log = self::get_insert_update_log();

		// Check the rule was run through the wpdb->insert method.
		$this->assertFalse( $log[0]->insert );
		$this->assertEquals( 'test_table_name', $log[0]->table );
		$this->assertEquals( 'name', $log[0]->rows['name'] );
		$this->assertEquals( 1, $log[0]->rows['rule_enabled'] );
		$this->assertEquals( '[{"conditions":[],"match_all":false}]', $log[0]->rows['conditions'] );
		$this->assertEquals( 'spam', $log[0]->rows['outcome'] );
		$this->assertInstanceOf( \DateTimeInterface::class, \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $log[0]->rows['updated'] ) );

		// Check the value formats.
		$this->assertEquals( '%s', $log[0]->format[0] );
		$this->assertEquals( '%d', $log[0]->format[1] );
		$this->assertEquals( '%s', $log[0]->format[2] );
		$this->assertEquals( '%s', $log[0]->format[3] );
		$this->assertEquals( '%s', $log[0]->format[4] );
		
		// where and where_format should be empty as insert
		$this->assertEquals( 10, $log[0]->where['id'] );
		$this->assertEquals( '%d', $log[0]->where_format[0] );
	}

	/**
	 * @testdox When updating a rule, if there is an issue an exception should be thrown with a meaningful message.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::upsert
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule_Repository::update_rule
	 */
	public function test_throws_exception_on_update_error(): void {
		// Return false to simulate an error.
		self::set_result( false );

		$rule = new Rule(
			10,
			'name',
			true,
			array(),
			'spam',
		);

		$this->logging_wpdb->last_error = 'Error Message';

		$repo = new Rule_Repository( $this->logging_wpdb, $this->app_config );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Error Message/' );

		$repo->upsert( $rule );
	}
}
