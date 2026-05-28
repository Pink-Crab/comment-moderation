<?php
/**
 * Rule_Type value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use WP_UnitTestCase;

/**
 * Proves Rule_Type enumerates the four implemented rule types (CIDR is
 * intentionally omitted at this stage) and recognises which of them only
 * inspects the commenter's IP.
 *
 * @group unit
 */
class Test_Rule_Type extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to construct each of the four rule types by name
	 */
	public function test_named_constructors(): void {
		$this->assertSame( Rule_Type::REGEX, Rule_Type::regex()->value() );
		$this->assertSame( Rule_Type::WILDCARD, Rule_Type::wildcard()->value() );
		$this->assertSame( Rule_Type::IP_RANGE, Rule_Type::ip_range()->value() );
		$this->assertSame( Rule_Type::CONDITIONAL, Rule_Type::conditional()->value() );
	}

	/**
	 * @testdox It should be possible to rehydrate any of the four rule types from their stored string value
	 */
	public function test_from_accepts_each_known_value(): void {
		foreach ( array( 'regex', 'wildcard', 'ip_range', 'conditional' ) as $value ) {
			$this->assertSame( $value, Rule_Type::from( $value )->value() );
		}
	}

	/**
	 * @testdox It should be possible to reject an unknown rule type value with a clear error
	 */
	public function test_from_rejects_unknown_value(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unknown rule type: "cidr".' );

		Rule_Type::from( 'cidr' );
	}

	/**
	 * @testdox It should be possible to compare two rule types for equality via the equals method
	 */
	public function test_equals_compares_by_stored_value(): void {
		$this->assertTrue( Rule_Type::regex()->equals( Rule_Type::from( 'regex' ) ) );
		$this->assertFalse( Rule_Type::regex()->equals( Rule_Type::wildcard() ) );
	}

	/**
	 * @testdox It should be possible to identify the IP Range rule type as the IP-only type that does not show the comment-part tick-boxes
	 */
	public function test_is_ip_only_only_for_ip_range(): void {
		$this->assertTrue( Rule_Type::ip_range()->is_ip_only() );
		$this->assertFalse( Rule_Type::regex()->is_ip_only() );
		$this->assertFalse( Rule_Type::wildcard()->is_ip_only() );
		$this->assertFalse( Rule_Type::conditional()->is_ip_only() );
	}

	/**
	 * @testdox It should be possible to enumerate the four rule types in the order the admin dropdown offers them
	 */
	public function test_all_returns_four_types_in_admin_order(): void {
		$values = array_map(
			static fn( Rule_Type $type ): string => $type->value(),
			Rule_Type::all()
		);

		$this->assertSame(
			array( 'regex', 'wildcard', 'ip_range', 'conditional' ),
			$values
		);
	}
}
