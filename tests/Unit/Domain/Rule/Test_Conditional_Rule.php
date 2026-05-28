<?php
/**
 * Conditional_Rule unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use DateTimeImmutable;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Combinator;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition_Group;
use PinkCrab\Comment_Moderation\Domain\Rule\Conditional_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use WP_UnitTestCase;

/**
 * Proves Conditional_Rule satisfies the Rule contract, wraps a root
 * Condition_Group, and reports the union of every reachable leaf's comment
 * parts.
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
			'root'        => Condition_Group::all_of(
				new Condition( Comment_Part::email(), Operator::wildcard(), '*@gmail.com' ),
				new Condition( Comment_Part::content(), Operator::contains(), 'crypto' ),
			),
			'response'    => Response::pending(),
			'usage_stats' => Usage_Stats::fresh( new DateTimeImmutable( '2026-05-01' ) ),
		);
		$args     = array_replace( $defaults, $overrides );

		return new Conditional_Rule(
			$args['id'],
			$args['name'],
			$args['description'],
			$args['root'],
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
	 * @testdox It should be possible to read the root condition group back from a Conditional_Rule
	 */
	public function test_exposes_root_group(): void {
		$root = Condition_Group::any_of(
			new Condition( Comment_Part::name(), Operator::is(), 'spammer' ),
			new Condition( Comment_Part::url(), Operator::ends_with(), '.ru' ),
		);

		$rule = $this->make_rule( array( 'root' => $root ) );

		$this->assertSame( $root, $rule->root() );
	}

	/**
	 * @testdox It should be possible to confirm a Conditional_Rule reports the union of its tree's comment parts in canonical order
	 */
	public function test_comment_parts_is_union_of_tree_leaves(): void {
		$rule = $this->make_rule(
			array(
				'root' => Condition_Group::all_of(
					new Condition( Comment_Part::content(), Operator::contains(), 'crypto' ),
					Condition_Group::any_of(
						new Condition( Comment_Part::email(), Operator::wildcard(), '*@gmail.com' ),
						new Condition( Comment_Part::name(), Operator::is(), 'bob' ),
					),
				),
			)
		);

		$this->assertSame( array( 'name', 'email', 'content' ), $rule->comment_parts()->values() );
	}

	/**
	 * @testdox It should be possible to confirm duplicate condition parts across the tree produce a single entry in the rule's comment parts
	 */
	public function test_comment_parts_deduplicates_repeated_leaves(): void {
		$rule = $this->make_rule(
			array(
				'root' => Condition_Group::all_of(
					new Condition( Comment_Part::name(), Operator::contains(), 'bob' ),
					new Condition( Comment_Part::name(), Operator::contains(), 'robert' ),
				),
			)
		);

		$this->assertSame( array( 'name' ), $rule->comment_parts()->values() );
	}

	/**
	 * @testdox It should be possible to expose every value passed into the Conditional_Rule constructor via its accessors
	 */
	public function test_accessors_return_constructor_values(): void {
		$root  = Condition_Group::any_of(
			new Condition( Comment_Part::url(), Operator::ends_with(), '.ru' ),
		);
		$stats = Usage_Stats::fresh( new DateTimeImmutable( '2026-05-12 09:00:00' ) );
		$rule  = $this->make_rule(
			array(
				'id'          => 99,
				'name'        => 'Russian URL trap',
				'description' => 'Catches URLs ending in .ru.',
				'root'        => $root,
				'response'    => Response::trash(),
				'usage_stats' => $stats,
			)
		);

		$this->assertSame( 99, $rule->id() );
		$this->assertSame( 'Russian URL trap', $rule->name() );
		$this->assertSame( 'Catches URLs ending in .ru.', $rule->description() );
		$this->assertSame( $root, $rule->root() );
		$this->assertTrue( $rule->response()->equals( Response::trash() ) );
		$this->assertSame( $stats, $rule->usage_stats() );
	}

	/**
	 * @testdox It should be possible to surface comment parts from a deeply nested condition tree through Conditional_Rule
	 */
	public function test_comment_parts_walks_deeply_nested_tree(): void {
		$rule = $this->make_rule(
			array(
				'root' => Condition_Group::all_of(
					new Condition( Comment_Part::content(), Operator::contains(), 'crypto' ),
					Condition_Group::any_of(
						new Condition( Comment_Part::email(), Operator::wildcard(), '*@gmail.com' ),
						Condition_Group::all_of(
							new Condition( Comment_Part::url(), Operator::ends_with(), '.ru' ),
							new Condition( Comment_Part::user_agent(), Operator::contains(), 'curl' ),
						),
					),
				),
			)
		);

		$this->assertSame(
			array( 'email', 'url', 'user_agent', 'content' ),
			$rule->comment_parts()->values()
		);
	}
}
