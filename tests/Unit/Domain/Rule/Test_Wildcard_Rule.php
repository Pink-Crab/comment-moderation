<?php
/**
 * Wildcard_Rule unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use DateTimeImmutable;
use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use WP_UnitTestCase;

/**
 * Proves Wildcard_Rule satisfies the Rule contract, reports its type, accepts
 * its pattern + at-least-one comment part, and rejects empty input.
 *
 * @group unit
 */
class Test_Wildcard_Rule extends WP_UnitTestCase {

	/**
	 * @param array<string,mixed> $overrides Partial constructor args.
	 */
	private function make_rule( array $overrides = array() ): Wildcard_Rule {
		$defaults = array(
			'id'            => null,
			'name'          => 'Block spam domain',
			'description'   => null,
			'pattern'       => '*@spam-domain.tld',
			'comment_parts' => Comment_Parts::of( Comment_Part::email() ),
			'response'      => Response::spam(),
			'usage_stats'   => Usage_Stats::fresh( new DateTimeImmutable( '2026-05-01' ) ),
		);
		$args     = array_replace( $defaults, $overrides );

		return new Wildcard_Rule(
			$args['id'],
			$args['name'],
			$args['description'],
			$args['pattern'],
			$args['comment_parts'],
			$args['response'],
			$args['usage_stats']
		);
	}

	/**
	 * @testdox It should be possible to confirm Wildcard_Rule implements the Rule contract
	 */
	public function test_implements_rule_interface(): void {
		$this->assertInstanceOf( Rule::class, $this->make_rule() );
	}

	/**
	 * @testdox It should be possible to read the wildcard rule type from a Wildcard_Rule instance
	 */
	public function test_reports_wildcard_type(): void {
		$this->assertTrue( $this->make_rule()->type()->equals( Rule_Type::wildcard() ) );
	}

	/**
	 * @testdox It should be possible to expose every value passed into the Wildcard_Rule constructor via its accessors
	 */
	public function test_accessors_return_constructor_values(): void {
		$parts = Comment_Parts::of( Comment_Part::email() );
		$stats = Usage_Stats::fresh( new DateTimeImmutable( '2026-05-10' ) );
		$rule  = $this->make_rule(
			array(
				'id'            => 7,
				'name'          => 'Foo',
				'description'   => 'Bar',
				'pattern'       => '*@example.com',
				'comment_parts' => $parts,
				'response'      => Response::trash(),
				'usage_stats'   => $stats,
			)
		);

		$this->assertSame( 7, $rule->id() );
		$this->assertSame( 'Foo', $rule->name() );
		$this->assertSame( 'Bar', $rule->description() );
		$this->assertSame( '*@example.com', $rule->pattern() );
		$this->assertSame( $parts, $rule->comment_parts() );
		$this->assertTrue( $rule->response()->equals( Response::trash() ) );
		$this->assertSame( $stats, $rule->usage_stats() );
	}

	/**
	 * @testdox It should be possible to reject a Wildcard_Rule with an empty pattern
	 */
	public function test_rejects_empty_pattern(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Wildcard_Rule: pattern must not be empty.' );

		$this->make_rule( array( 'pattern' => '' ) );
	}

	/**
	 * @testdox It should be possible to reject a Wildcard_Rule with no comment parts selected
	 */
	public function test_rejects_empty_comment_parts(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Wildcard_Rule: at least one comment part must be selected.' );

		$this->make_rule( array( 'comment_parts' => Comment_Parts::none() ) );
	}
}
