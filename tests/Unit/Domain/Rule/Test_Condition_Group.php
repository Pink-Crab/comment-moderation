<?php
/**
 * Condition_Group value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Combinator;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition_Group;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use WP_UnitTestCase;

/**
 * Proves Condition_Group enforces at least one child, accepts either
 * Conditions or nested Condition_Groups, and recursively unions every leaf's
 * comment parts.
 *
 * @group unit
 */
class Test_Condition_Group extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to read the combinator and ordered children back from a Condition_Group
	 */
	public function test_exposes_combinator_and_children_in_order(): void {
		$first  = new Condition( Comment_Part::email(), Operator::wildcard(), '*@gmail.com' );
		$second = new Condition( Comment_Part::content(), Operator::contains(), 'crypto' );

		$group = new Condition_Group( Combinator::all(), array( $first, $second ) );

		$this->assertTrue( $group->combinator()->equals( Combinator::all() ) );
		$this->assertSame( array( $first, $second ), $group->children() );
	}

	/**
	 * @testdox It should be possible to confirm all_of() builds an AND-combined group
	 */
	public function test_all_of_builds_and_group(): void {
		$group = Condition_Group::all_of(
			new Condition( Comment_Part::name(), Operator::is(), 'bob' ),
		);

		$this->assertTrue( $group->combinator()->is_and() );
	}

	/**
	 * @testdox It should be possible to confirm any_of() builds an OR-combined group
	 */
	public function test_any_of_builds_or_group(): void {
		$group = Condition_Group::any_of(
			new Condition( Comment_Part::name(), Operator::is(), 'bob' ),
		);

		$this->assertTrue( $group->combinator()->is_or() );
	}

	/**
	 * @testdox It should be possible to reject a Condition_Group built with zero children
	 */
	public function test_rejects_empty_children(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Condition_Group: at least one child is required.' );

		new Condition_Group( Combinator::all(), array() );
	}

	/**
	 * @testdox It should be possible to reject a Condition_Group child that is neither a Condition nor a Condition_Group
	 */
	public function test_rejects_invalid_child_type(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Condition_Group: child at index 0 must be a Condition or Condition_Group.' );

		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- deliberate type-violation test.
		new Condition_Group( Combinator::all(), array( 'not a node' ) );
	}

	/**
	 * @testdox It should be possible to nest a Condition_Group inside another Condition_Group
	 */
	public function test_accepts_nested_group_child(): void {
		$inner = Condition_Group::any_of(
			new Condition( Comment_Part::email(), Operator::ends_with(), '@spam.tld' ),
			new Condition( Comment_Part::url(), Operator::ends_with(), '.ru' ),
		);

		$outer = Condition_Group::all_of(
			new Condition( Comment_Part::content(), Operator::contains(), 'crypto' ),
			$inner,
		);

		$this->assertSame( $inner, $outer->children()[1] );
		$this->assertFalse( $outer->is_flat() );
		$this->assertTrue( $inner->is_flat() );
	}

	/**
	 * @testdox It should be possible to count direct children of a Condition_Group without recursing into nested groups
	 */
	public function test_count_returns_number_of_direct_children(): void {
		$group = Condition_Group::all_of(
			new Condition( Comment_Part::name(), Operator::is(), 'bob' ),
			Condition_Group::any_of(
				new Condition( Comment_Part::url(), Operator::is(), 'evil.example' ),
				new Condition( Comment_Part::url(), Operator::is(), 'spam.example' ),
			),
		);

		$this->assertSame( 2, $group->count() );
	}

	/**
	 * @testdox It should be possible to collect every leaf condition reachable from a Condition_Group in tree order
	 */
	public function test_all_conditions_walks_the_tree_in_order(): void {
		$leaf_one   = new Condition( Comment_Part::name(), Operator::is(), 'bob' );
		$leaf_two   = new Condition( Comment_Part::email(), Operator::contains(), 'gmail' );
		$leaf_three = new Condition( Comment_Part::content(), Operator::wildcard(), '*crypto*' );

		$group = Condition_Group::all_of(
			$leaf_one,
			Condition_Group::any_of( $leaf_two, $leaf_three ),
		);

		$this->assertSame( array( $leaf_one, $leaf_two, $leaf_three ), $group->all_conditions() );
	}

	/**
	 * @testdox It should be possible to recursively union every leaf's comment parts via comment_parts()
	 */
	public function test_comment_parts_recursively_unions_leaves(): void {
		$group = Condition_Group::all_of(
			new Condition( Comment_Part::content(), Operator::contains(), 'crypto' ),
			Condition_Group::any_of(
				new Condition( Comment_Part::email(), Operator::wildcard(), '*@gmail.com' ),
				new Condition( Comment_Part::name(), Operator::is(), 'bob' ),
				new Condition( Comment_Part::email(), Operator::ends_with(), '@spam.tld' ),
			),
		);

		$this->assertSame( array( 'name', 'email', 'content' ), $group->comment_parts()->values() );
	}
}
