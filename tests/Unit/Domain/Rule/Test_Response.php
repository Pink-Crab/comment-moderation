<?php
/**
 * Response value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use WP_UnitTestCase;

/**
 * Proves the Response VO carries the four rebuild-spec outcomes, recognises
 * which three are user-selectable, and rejects unknown stored values.
 *
 * @group unit
 */
class Test_Response extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to construct each of the three user-selectable responses by name
	 */
	public function test_named_constructors_for_user_selectable_responses(): void {
		$this->assertSame( Response::PENDING, Response::pending()->value() );
		$this->assertSame( Response::SPAM, Response::spam()->value() );
		$this->assertSame( Response::TRASH, Response::trash()->value() );
	}

	/**
	 * @testdox It should be possible to construct the hidden approved response by name even though it is not offered in the admin UI
	 */
	public function test_named_constructor_for_hidden_approved(): void {
		$this->assertSame( Response::APPROVED, Response::approved()->value() );
	}

	/**
	 * @testdox It should be possible to rehydrate any of the four responses from their stored string value
	 */
	public function test_from_accepts_each_known_value(): void {
		foreach ( array( Response::PENDING, Response::SPAM, Response::TRASH, Response::APPROVED ) as $value ) {
			$this->assertSame( $value, Response::from( $value )->value() );
		}
	}

	/**
	 * @testdox It should be possible to reject an unknown response value with a clear error
	 */
	public function test_from_rejects_unknown_value(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unknown rule response value: "ham".' );

		Response::from( 'ham' );
	}

	/**
	 * @testdox It should be possible to compare two responses for equality via the equals method
	 */
	public function test_equals_compares_by_stored_value(): void {
		$this->assertTrue( Response::pending()->equals( Response::from( Response::PENDING ) ) );
		$this->assertFalse( Response::pending()->equals( Response::spam() ) );
	}

	/**
	 * @testdox It should be possible to ask a response which specific outcome it represents
	 */
	public function test_type_predicates_are_mutually_exclusive(): void {
		$pending = Response::pending();
		$this->assertTrue( $pending->is_pending() );
		$this->assertFalse( $pending->is_spam() );
		$this->assertFalse( $pending->is_trash() );
		$this->assertFalse( $pending->is_approved() );

		$approved = Response::approved();
		$this->assertTrue( $approved->is_approved() );
		$this->assertFalse( $approved->is_pending() );
		$this->assertFalse( $approved->is_spam() );
		$this->assertFalse( $approved->is_trash() );
	}

	/**
	 * @testdox It should be possible to distinguish the user-selectable responses from the hidden approved value
	 */
	public function test_is_user_selectable_hides_approved(): void {
		$this->assertTrue( Response::pending()->is_user_selectable() );
		$this->assertTrue( Response::spam()->is_user_selectable() );
		$this->assertTrue( Response::trash()->is_user_selectable() );
		$this->assertFalse( Response::approved()->is_user_selectable() );
	}

	/**
	 * @testdox It should be possible to enumerate the three user-selectable responses in the order shown on the admin screen
	 */
	public function test_user_selectable_returns_three_responses_in_spec_order(): void {
		$selectable = Response::user_selectable();

		$this->assertCount( 3, $selectable );
		$this->assertSame(
			array( Response::PENDING, Response::SPAM, Response::TRASH ),
			array_map( static fn( Response $r ): string => $r->value(), $selectable )
		);
	}
}
