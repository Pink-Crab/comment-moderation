<?php
/**
 * Combinator value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Combinator;
use WP_UnitTestCase;

/**
 * Proves Combinator carries the AND/OR distinction, rehydrates from the
 * documented tokens and shorthand, and rejects anything else.
 *
 * @group unit
 */
class Test_Combinator extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to read the AND value back from the all() factory
	 */
	public function test_all_returns_and_combinator(): void {
		$combinator = Combinator::all();

		$this->assertSame( 'and', $combinator->value() );
		$this->assertTrue( $combinator->is_and() );
		$this->assertFalse( $combinator->is_or() );
	}

	/**
	 * @testdox It should be possible to read the OR value back from the any() factory
	 */
	public function test_any_returns_or_combinator(): void {
		$combinator = Combinator::any();

		$this->assertSame( 'or', $combinator->value() );
		$this->assertTrue( $combinator->is_or() );
		$this->assertFalse( $combinator->is_and() );
	}

	/**
	 * @testdox It should be possible to rehydrate a Combinator from its stored token
	 */
	public function test_from_accepts_canonical_tokens(): void {
		$this->assertTrue( Combinator::from( 'and' )->equals( Combinator::all() ) );
		$this->assertTrue( Combinator::from( 'OR' )->equals( Combinator::any() ) );
	}

	/**
	 * @testdox It should be possible to rehydrate a Combinator from && or || shorthand
	 */
	public function test_from_accepts_symbolic_shorthand(): void {
		$this->assertTrue( Combinator::from( '&&' )->equals( Combinator::all() ) );
		$this->assertTrue( Combinator::from( '||' )->equals( Combinator::any() ) );
	}

	/**
	 * @testdox It should be possible to reject an unknown combinator token
	 */
	public function test_from_rejects_unknown_value(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unknown combinator: "xor".' );

		Combinator::from( 'xor' );
	}

	/**
	 * @testdox It should be possible to compare two Combinators for equality via equals()
	 */
	public function test_equals_compares_by_stored_value(): void {
		$this->assertTrue( Combinator::all()->equals( Combinator::from( '&&' ) ) );
		$this->assertFalse( Combinator::all()->equals( Combinator::any() ) );
	}
}
