<?php
/**
 * Condition value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use WP_UnitTestCase;

/**
 * Proves a Condition carries a (comment part + operator + non-empty value)
 * triple and rejects an empty value.
 *
 * @group unit
 */
class Test_Condition extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to construct a condition with a comment part, operator, and non-empty value
	 */
	public function test_constructor_exposes_triple(): void {
		$condition = new Condition( Comment_Part::email(), Operator::contains(), '@gmail.com' );

		$this->assertTrue( $condition->part()->equals( Comment_Part::email() ) );
		$this->assertTrue( $condition->operator()->equals( Operator::contains() ) );
		$this->assertSame( '@gmail.com', $condition->value() );
	}

	/**
	 * @testdox It should be possible to reject an empty value when constructing a condition
	 */
	public function test_constructor_rejects_empty_value(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Condition: value must not be empty.' );

		new Condition( Comment_Part::email(), Operator::is(), '' );
	}

	/**
	 * @testdox It should be possible to carry any of the twelve operators on a condition
	 */
	public function test_accepts_each_operator_kind(): void {
		foreach ( Operator::all() as $operator ) {
			$condition = new Condition( Comment_Part::name(), $operator, 'whatever' );
			$this->assertTrue( $condition->operator()->equals( $operator ) );
		}
	}
}
