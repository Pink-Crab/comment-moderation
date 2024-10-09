<?php

/**
 * Unit tests for the Rule model
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Tests\Unit\Rule;

use PinkCrab\Comment_Moderation\Rule\Rule;
use PinkCrab\Comment_Moderation\Rule\Condition\Group;

/**
 * Test_Rule tests the Rule model.
 *
 * @group unit
 * @group rule
 */
class Test_Rule extends \WP_UnitTestCase {

	/**
	 * @testdox It should be possible to create a new Rule with a defined id and retrieve it.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_id
	 */
	public function test_can_create_rule_with_id(): void {
		$rule = new Rule(
			1,
			'name',
			true,
			new Group(),
			'spam',
		);
		$this->assertEquals( 1, $rule->get_id() );
	}

	/**
	 * @testdox It should be possible to create a new Rule without a defined id and retrieve it.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_id
	 */
	public function test_can_create_rule_without_id(): void {
		$rule = new Rule(
			null,
			'name',
			true,
			new Group(),
			'spam',
		);
		$this->assertNull( $rule->get_id() );
	}

	/**
	 * @testdox It should be possible to create a new Rule with a defined name and retrieve it.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_name
	 */
	public function test_can_create_rule_with_name(): void {
		$rule = new Rule(
			1,
			'name',
			true,
			new Group(),
			'spam',
		);
		$this->assertEquals( 'name', $rule->get_rule_name() );
	}
	/**
	 * @testdox It should be possible to create a new Rule with a defined rule enabled and retrieve it.[TRUE]
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_enabled
	 */
	public function test_can_create_rule_with_rule_enabled(): void {
		$rule = new Rule(
			1,
			'name',
			true,
			new Group(),
			'spam',
		);
		$this->assertTrue( $rule->get_rule_enabled() );
	}

	/**
	 * @testdox It should be possible to create a new Rule with a defined rule enabled and retrieve it.[FALSE]
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_enabled
	 */
	public function test_can_create_rule_with_rule_disabled(): void {
		$rule = new Rule(
			1,
			'name',
			false,
			new Group(),
			'spam',
		);
		$this->assertFalse( $rule->get_rule_enabled() );
	}

	/**
	 * @testdox It should be possible to create a new Rule with a defined outcome and retrieve it.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_outcome
	 */
	public function test_can_create_rule_with_outcome(): void {
		$rule = new Rule(
			1,
			'name',
			false,
			new Group(),
			'spam',
		);
		$this->assertEquals( 'spam', $rule->get_outcome() );
	}

	/**
	 * @testdox It should be possible to create a new Rule with a defined created date and retrieve it.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_created
	 */
	public function test_can_create_rule_with_created_date(): void {
		$rule = new Rule(
			1,
			'name',
			false,
			new Group(),
			'spam',
			new \DateTimeImmutable( '2021-01-01 00:00:00' )
		);

		$this->assertEquals( '2021-01-01', $rule->get_created()->format( 'Y-m-d' ) );
	}

	/**
	 * @testdox It should be possible to create a new Rule without a defined created date and it be set using now and retrieved.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_created
	 */
	public function test_can_create_rule_without_created_date(): void {
		$rule = new Rule(
			1,
			'name',
			false,
			new Group(),
			'spam',
		);

		$this->assertEquals( date( 'Y-m-d' ), $rule->get_created()->format( 'Y-m-d' ) );
	}

	/**
	 * @testdox It should be possible to create a new Rule with a defined updated date and retrieve it.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_updated
	 */
	public function test_can_create_rule_with_updated_date(): void {
		$rule = new Rule(
			1,
			'name',
			false,
			new Group(),
			'spam',
			new \DateTimeImmutable( '2021-01-01 00:00:00' ),
			new \DateTimeImmutable( '2021-01-02 00:00:00' )
		);

		$this->assertEquals( '2021-01-02', $rule->get_updated()->format( 'Y-m-d' ) );
	}

	/**
	 * @testdox It should be possible to create a new Rule without a defined updated date and it be set using now and retrieved.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_updated
	 */
	public function test_can_create_rule_without_updated_date(): void {
		$rule = new Rule(
			1,
			'name',
			false,
			new Group(),
			'spam',
		);

		$this->assertEquals( date( 'Y-m-d' ), $rule->get_updated()->format( 'Y-m-d' ) );
	}
}
