<?php
/**
 * Comment_Part value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use WP_UnitTestCase;

/**
 * Proves Comment_Part enumerates the six inspectable slices of a comment and
 * rejects unknown values.
 *
 * @group unit
 */
class Test_Comment_Part extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to construct each of the six comment parts by name
	 */
	public function test_named_constructors(): void {
		$this->assertSame( Comment_Part::NAME, Comment_Part::name()->value() );
		$this->assertSame( Comment_Part::EMAIL, Comment_Part::email()->value() );
		$this->assertSame( Comment_Part::URL, Comment_Part::url()->value() );
		$this->assertSame( Comment_Part::IP, Comment_Part::ip()->value() );
		$this->assertSame( Comment_Part::USER_AGENT, Comment_Part::user_agent()->value() );
		$this->assertSame( Comment_Part::CONTENT, Comment_Part::content()->value() );
	}

	/**
	 * @testdox It should be possible to rehydrate any of the six comment parts from their stored string value
	 */
	public function test_from_accepts_each_known_value(): void {
		foreach ( array( 'name', 'email', 'url', 'ip', 'user_agent', 'content' ) as $value ) {
			$this->assertSame( $value, Comment_Part::from( $value )->value() );
		}
	}

	/**
	 * @testdox It should be possible to reject an unknown comment part value with a clear error
	 */
	public function test_from_rejects_unknown_value(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unknown comment part: "subject".' );

		Comment_Part::from( 'subject' );
	}

	/**
	 * @testdox It should be possible to compare two comment parts for equality via the equals method
	 */
	public function test_equals_compares_by_stored_value(): void {
		$this->assertTrue( Comment_Part::email()->equals( Comment_Part::from( 'email' ) ) );
		$this->assertFalse( Comment_Part::email()->equals( Comment_Part::name() ) );
	}

	/**
	 * @testdox It should be possible to enumerate all six comment parts in canonical order
	 */
	public function test_all_returns_six_parts_in_canonical_order(): void {
		$values = array_map(
			static fn( Comment_Part $part ): string => $part->value(),
			Comment_Part::all()
		);

		$this->assertSame(
			array( 'name', 'email', 'url', 'ip', 'user_agent', 'content' ),
			$values
		);
	}
}
