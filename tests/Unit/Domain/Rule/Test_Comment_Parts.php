<?php
/**
 * Comment_Parts collection unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use WP_UnitTestCase;

/**
 * Proves the Comment_Parts collection deduplicates, canonicalises order, and
 * supports the with/without/has/count operations the admin filters need.
 *
 * @group unit
 */
class Test_Comment_Parts extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to build an empty Comment_Parts collection
	 */
	public function test_none_is_empty(): void {
		$parts = Comment_Parts::none();

		$this->assertTrue( $parts->is_empty() );
		$this->assertCount( 0, $parts );
		$this->assertSame( array(), $parts->all_parts() );
		$this->assertSame( array(), $parts->values() );
	}

	/**
	 * @testdox It should be possible to build a Comment_Parts collection containing all six recognised parts
	 */
	public function test_all_contains_six_parts(): void {
		$parts = Comment_Parts::all();

		$this->assertFalse( $parts->is_empty() );
		$this->assertCount( 6, $parts );
		$this->assertSame(
			array( 'name', 'email', 'url', 'ip', 'user_agent', 'content' ),
			$parts->values()
		);
	}

	/**
	 * @testdox It should be possible to deduplicate parts when constructing the collection
	 */
	public function test_constructor_deduplicates(): void {
		$parts = Comment_Parts::of(
			Comment_Part::email(),
			Comment_Part::email(),
			Comment_Part::name()
		);

		$this->assertCount( 2, $parts );
		$this->assertSame( array( 'name', 'email' ), $parts->values() );
	}

	/**
	 * @testdox It should be possible to normalise constructor input into canonical Comment_Part order
	 */
	public function test_constructor_normalises_order(): void {
		$parts = Comment_Parts::of(
			Comment_Part::content(),
			Comment_Part::name(),
			Comment_Part::url()
		);

		$this->assertSame( array( 'name', 'url', 'content' ), $parts->values() );
	}

	/**
	 * @testdox It should be possible to build a Comment_Parts collection from an array of stored string values
	 */
	public function test_from_values_rehydrates(): void {
		$parts = Comment_Parts::from_values( array( 'ip', 'email', 'email' ) );

		$this->assertCount( 2, $parts );
		$this->assertSame( array( 'email', 'ip' ), $parts->values() );
	}

	/**
	 * @testdox It should be possible to ask a Comment_Parts collection whether it contains a given part
	 */
	public function test_has_compares_by_value(): void {
		$parts = Comment_Parts::of( Comment_Part::email(), Comment_Part::ip() );

		$this->assertTrue( $parts->has( Comment_Part::email() ) );
		$this->assertTrue( $parts->has( Comment_Part::ip() ) );
		$this->assertFalse( $parts->has( Comment_Part::name() ) );
	}

	/**
	 * @testdox It should be possible to add a part to a Comment_Parts collection and get a new collection back without mutating the original
	 */
	public function test_with_returns_new_collection(): void {
		$original = Comment_Parts::of( Comment_Part::email() );
		$updated  = $original->with( Comment_Part::name() );

		$this->assertNotSame( $original, $updated );
		$this->assertSame( array( 'email' ), $original->values() );
		$this->assertSame( array( 'name', 'email' ), $updated->values() );
	}

	/**
	 * @testdox It should be possible to add an already-present part and get back the same collection unchanged
	 */
	public function test_with_is_noop_when_part_already_present(): void {
		$original = Comment_Parts::of( Comment_Part::email() );
		$updated  = $original->with( Comment_Part::email() );

		$this->assertSame( $original, $updated );
	}

	/**
	 * @testdox It should be possible to remove a part from a Comment_Parts collection and get a new collection back without mutating the original
	 */
	public function test_without_returns_new_collection(): void {
		$original = Comment_Parts::of( Comment_Part::name(), Comment_Part::email() );
		$updated  = $original->without( Comment_Part::email() );

		$this->assertNotSame( $original, $updated );
		$this->assertSame( array( 'name', 'email' ), $original->values() );
		$this->assertSame( array( 'name' ), $updated->values() );
	}

	/**
	 * @testdox It should be possible to remove a part that is not present and get back the same collection unchanged
	 */
	public function test_without_is_noop_when_part_absent(): void {
		$original = Comment_Parts::of( Comment_Part::name() );
		$updated  = $original->without( Comment_Part::email() );

		$this->assertSame( $original, $updated );
	}

	/**
	 * @testdox It should be possible to iterate over a Comment_Parts collection in canonical order
	 */
	public function test_is_iterable(): void {
		$parts = Comment_Parts::of(
			Comment_Part::content(),
			Comment_Part::name()
		);

		$values = array();
		foreach ( $parts as $part ) {
			$values[] = $part->value();
		}

		$this->assertSame( array( 'name', 'content' ), $values );
	}
}
