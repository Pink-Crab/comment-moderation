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
use WP_UnitTestCase;

/**
 * Proves a Condition carries a (comment part + non-empty wildcard pattern)
 * pair and rejects an empty pattern.
 *
 * @group unit
 */
class Test_Condition extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to construct a condition with a comment part and a non-empty wildcard pattern
	 */
	public function test_constructor_exposes_pair(): void {
		$condition = new Condition( Comment_Part::email(), '*@gmail.com' );

		$this->assertTrue( $condition->part()->equals( Comment_Part::email() ) );
		$this->assertSame( '*@gmail.com', $condition->pattern() );
	}

	/**
	 * @testdox It should be possible to reject an empty wildcard pattern when constructing a condition
	 */
	public function test_constructor_rejects_empty_pattern(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Condition: pattern must not be empty.' );

		new Condition( Comment_Part::email(), '' );
	}
}
