<?php
/**
 * Conditional_Rule unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use DateTimeImmutable;
use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Conditional_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use WP_UnitTestCase;

/**
 * Proves Conditional_Rule satisfies the Rule contract, requires at least one
 * condition, and reports the union of its conditions' comment parts.
 *
 * @group unit
 */
class Test_Conditional_Rule extends WP_UnitTestCase {

	/**
	 * @param array<string,mixed> $overrides Partial constructor args.
	 */
	private function make_rule( array $overrides = array() ): Conditional_Rule {
		$defaults = array(
			'id'          => null,
			'name'        => 'Crypto Gmail trap',
			'description' => null,
			'conditions'  => array(
				new Condition( Comment_Part::email(), '*@gmail.com' ),
				new Condition( Comment_Part::content(), '*crypto*' ),
			),
			'response'    => Response::pending(),
			'usage_stats' => Usage_Stats::fresh( new DateTimeImmutable( '2026-05-01' ) ),
		);
		$args     = array_replace( $defaults, $overrides );

		return new Conditional_Rule(
			$args['id'],
			$args['name'],
			$args['description'],
			$args['conditions'],
			$args['response'],
			$args['usage_stats']
		);
	}

	/**
	 * @testdox It should be possible to confirm Conditional_Rule implements the Rule contract
	 */
	public function test_implements_rule_interface(): void {
		$this->assertInstanceOf( Rule::class, $this->make_rule() );
	}

	/**
	 * @testdox It should be possible to read the conditional rule type from a Conditional_Rule instance
	 */
	public function test_reports_conditional_type(): void {
		$this->assertTrue( $this->make_rule()->type()->equals( Rule_Type::conditional() ) );
	}

	/**
	 * @testdox It should be possible to read the conditions back from a Conditional_Rule in the order they were supplied
	 */
	public function test_conditions_are_exposed_in_supplied_order(): void {
		$first  = new Condition( Comment_Part::email(), '*@gmail.com' );
		$second = new Condition( Comment_Part::content(), '*crypto*' );

		$rule = $this->make_rule( array( 'conditions' => array( $first, $second ) ) );

		$this->assertSame( array( $first, $second ), $rule->conditions() );
	}

	/**
	 * @testdox It should be possible to confirm a Conditional_Rule reports the union of its conditions' comment parts in canonical order
	 */
	public function test_comment_parts_is_union_of_conditions(): void {
		$rule = $this->make_rule(
			array(
				'conditions' => array(
					new Condition( Comment_Part::content(), '*crypto*' ),
					new Condition( Comment_Part::email(), '*@gmail.com' ),
				),
			)
		);

		$this->assertSame( array( 'email', 'content' ), $rule->comment_parts()->values() );
	}

	/**
	 * @testdox It should be possible to confirm duplicate condition parts produce a single entry in the rule's comment parts
	 */
	public function test_comment_parts_deduplicates_repeated_conditions(): void {
		$rule = $this->make_rule(
			array(
				'conditions' => array(
					new Condition( Comment_Part::name(), '*bob*' ),
					new Condition( Comment_Part::name(), '*robert*' ),
				),
			)
		);

		$this->assertSame( array( 'name' ), $rule->comment_parts()->values() );
	}

	/**
	 * @testdox It should be possible to reject a Conditional_Rule built with zero conditions
	 */
	public function test_rejects_empty_conditions(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Conditional_Rule: at least one condition is required.' );

		$this->make_rule( array( 'conditions' => array() ) );
	}
}
