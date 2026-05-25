<?php
/**
 * Operator value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use WP_UnitTestCase;

/**
 * Proves Operator enumerates the twelve documented operators, treats `HAS`
 * as a write-time alias of `CONTAINS`, and pairs negations correctly.
 *
 * @group unit
 */
class Test_Operator extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to read the canonical stored value back from each named constructor
	 */
	public function test_named_constructors_expose_canonical_values(): void {
		$this->assertSame( 'is', Operator::is()->value() );
		$this->assertSame( 'is_not', Operator::is_not()->value() );
		$this->assertSame( 'contains', Operator::contains()->value() );
		$this->assertSame( 'does_not_contain', Operator::does_not_contain()->value() );
		$this->assertSame( 'starts_with', Operator::starts_with()->value() );
		$this->assertSame( 'ends_with', Operator::ends_with()->value() );
		$this->assertSame( 'in', Operator::in()->value() );
		$this->assertSame( 'not_in', Operator::not_in()->value() );
		$this->assertSame( 'matches', Operator::matches()->value() );
		$this->assertSame( 'does_not_match', Operator::does_not_match()->value() );
		$this->assertSame( 'wildcard', Operator::wildcard()->value() );
		$this->assertSame( 'not_wildcard', Operator::not_wildcard()->value() );
	}

	/**
	 * @testdox It should be possible to enumerate all twelve operators via Operator::all()
	 */
	public function test_all_returns_twelve_operators(): void {
		$this->assertCount( 12, Operator::all() );
	}

	/**
	 * @testdox It should be possible to rehydrate an Operator from its stored value
	 */
	public function test_from_accepts_canonical_value(): void {
		$this->assertTrue( Operator::from( 'starts_with' )->equals( Operator::starts_with() ) );
	}

	/**
	 * @testdox It should be possible to rehydrate an Operator from a spaced or cased label
	 */
	public function test_from_normalises_spaces_and_case(): void {
		$this->assertTrue( Operator::from( 'DOES NOT CONTAIN' )->equals( Operator::does_not_contain() ) );
		$this->assertTrue( Operator::from( 'Starts With' )->equals( Operator::starts_with() ) );
	}

	/**
	 * @testdox It should be possible to confirm HAS is accepted as an alias of CONTAINS
	 */
	public function test_from_treats_has_as_contains(): void {
		$this->assertTrue( Operator::from( 'HAS' )->equals( Operator::contains() ) );
		$this->assertTrue( Operator::from( 'has' )->equals( Operator::contains() ) );
		$this->assertSame( 'contains', Operator::from( 'has' )->value() );
	}

	/**
	 * @testdox It should be possible to reject an unknown operator token
	 */
	public function test_from_rejects_unknown_value(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unknown condition operator: "between".' );

		Operator::from( 'between' );
	}

	/**
	 * @testdox It should be possible to identify each operator as either a negation or its affirmative form
	 */
	public function test_is_negation_flags_only_the_inverting_forms(): void {
		$this->assertTrue( Operator::is_not()->is_negation() );
		$this->assertTrue( Operator::does_not_contain()->is_negation() );
		$this->assertTrue( Operator::not_in()->is_negation() );
		$this->assertTrue( Operator::does_not_match()->is_negation() );
		$this->assertTrue( Operator::not_wildcard()->is_negation() );

		$this->assertFalse( Operator::is()->is_negation() );
		$this->assertFalse( Operator::contains()->is_negation() );
		$this->assertFalse( Operator::in()->is_negation() );
		$this->assertFalse( Operator::matches()->is_negation() );
		$this->assertFalse( Operator::wildcard()->is_negation() );
		$this->assertFalse( Operator::starts_with()->is_negation() );
		$this->assertFalse( Operator::ends_with()->is_negation() );
	}

	/**
	 * @testdox It should be possible to flip each paired operator to its opposite via negation()
	 */
	public function test_negation_flips_paired_operators(): void {
		$pairs = array(
			array( Operator::is(), Operator::is_not() ),
			array( Operator::contains(), Operator::does_not_contain() ),
			array( Operator::in(), Operator::not_in() ),
			array( Operator::matches(), Operator::does_not_match() ),
			array( Operator::wildcard(), Operator::not_wildcard() ),
		);

		foreach ( $pairs as [$affirmative, $negation] ) {
			$this->assertTrue( $affirmative->negation()->equals( $negation ) );
			$this->assertTrue( $negation->negation()->equals( $affirmative ) );
		}
	}

	/**
	 * @testdox It should be possible to confirm operators without a paired negation return themselves from negation()
	 */
	public function test_negation_is_identity_for_unpaired_operators(): void {
		$this->assertTrue( Operator::starts_with()->negation()->equals( Operator::starts_with() ) );
		$this->assertTrue( Operator::ends_with()->negation()->equals( Operator::ends_with() ) );
	}

	/**
	 * @testdox It should be possible to compare two Operators for equality via equals()
	 */
	public function test_equals_compares_by_stored_value(): void {
		$this->assertTrue( Operator::contains()->equals( Operator::from( 'has' ) ) );
		$this->assertFalse( Operator::contains()->equals( Operator::does_not_contain() ) );
	}
}
