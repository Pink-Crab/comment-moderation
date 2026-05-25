<?php
/**
 * Rule_Filter unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Filter;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use WP_UnitTestCase;

/**
 * Confirms Rule_Filter exposes every criterion it was constructed with and
 * reports `is_empty()` only when no criterion is set (rebuild spec §7).
 *
 * @group unit
 */
class Test_Rule_Filter extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to build a Rule_Filter with no arguments and have it report itself as empty
	 */
	public function test_default_filter_is_empty(): void {
		$filter = new Rule_Filter();

		$this->assertTrue( $filter->is_empty() );
		$this->assertSame( '', $filter->search_term() );
		$this->assertSame( array(), $filter->types() );
		$this->assertSame( array(), $filter->responses() );
		$this->assertTrue( $filter->parts()->is_empty() );
	}

	/**
	 * @testdox It should be possible to read each criterion back from a fully populated Rule_Filter
	 */
	public function test_populated_filter_exposes_every_criterion(): void {
		$filter = new Rule_Filter(
			'casino',
			array( Rule_Type::regex(), Rule_Type::wildcard() ),
			Comment_Parts::of( Comment_Part::email(), Comment_Part::content() ),
			array( Response::spam() )
		);

		$this->assertFalse( $filter->is_empty() );
		$this->assertSame( 'casino', $filter->search_term() );
		$this->assertCount( 2, $filter->types() );
		$this->assertSame( array( 'email', 'content' ), $filter->parts()->values() );
		$this->assertCount( 1, $filter->responses() );
	}

	/**
	 * @testdox It should be possible to build an explicit empty filter via Rule_Filter::none() that matches the default
	 */
	public function test_none_factory_matches_default_constructor(): void {
		$this->assertTrue( Rule_Filter::none()->is_empty() );
	}

	/**
	 * @testdox It should be possible to flag a filter as non-empty when only the search term is set
	 */
	public function test_filter_with_only_search_term_is_not_empty(): void {
		$this->assertFalse( ( new Rule_Filter( 'gambl' ) )->is_empty() );
	}
}
