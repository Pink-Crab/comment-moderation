<?php
/**
 * Regex_Rule unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use DateTimeImmutable;
use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use WP_UnitTestCase;

/**
 * Proves Regex_Rule satisfies the Rule contract, reports its type, accepts
 * its pattern + at-least-one comment part, and rejects empty input.
 *
 * @group unit
 */
class Test_Regex_Rule extends WP_UnitTestCase {

	/**
	 * Build a Regex_Rule with sensible defaults; tests override what they care about.
	 *
	 * @param array<string,mixed> $overrides Partial constructor args.
	 */
	private function make_rule( array $overrides = array() ): Regex_Rule {
		$defaults = array(
			'id'            => null,
			'name'          => 'Block casinos',
			'description'   => 'Catches several spellings of "casino".',
			'pattern'       => '/casin[o0]/i',
			'comment_parts' => Comment_Parts::of( Comment_Part::name(), Comment_Part::content() ),
			'response'      => Response::spam(),
			'usage_stats'   => Usage_Stats::fresh( new DateTimeImmutable( '2026-05-01' ) ),
		);
		$args     = array_replace( $defaults, $overrides );

		return new Regex_Rule(
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
	 * @testdox It should be possible to confirm Regex_Rule implements the Rule contract
	 */
	public function test_implements_rule_interface(): void {
		$this->assertInstanceOf( Rule::class, $this->make_rule() );
	}

	/**
	 * @testdox It should be possible to read the regex rule type from a Regex_Rule instance
	 */
	public function test_reports_regex_type(): void {
		$this->assertTrue( $this->make_rule()->type()->equals( Rule_Type::regex() ) );
	}

	/**
	 * @testdox It should be possible to expose every value passed into the Regex_Rule constructor via its accessors
	 */
	public function test_accessors_return_constructor_values(): void {
		$parts = Comment_Parts::of( Comment_Part::email() );
		$stats = Usage_Stats::fresh( new DateTimeImmutable( '2026-05-10' ) );
		$rule  = $this->make_rule(
			array(
				'id'            => 42,
				'name'          => 'Foo',
				'description'   => 'Bar',
				'pattern'       => '/foo/',
				'comment_parts' => $parts,
				'response'      => Response::pending(),
				'usage_stats'   => $stats,
			)
		);

		$this->assertSame( 42, $rule->id() );
		$this->assertSame( 'Foo', $rule->name() );
		$this->assertSame( 'Bar', $rule->description() );
		$this->assertSame( '/foo/', $rule->pattern() );
		$this->assertSame( $parts, $rule->comment_parts() );
		$this->assertTrue( $rule->response()->equals( Response::pending() ) );
		$this->assertSame( $stats, $rule->usage_stats() );
	}

	/**
	 * @testdox It should be possible to allow a Regex_Rule to be created without an id, name, or description
	 */
	public function test_optional_id_name_and_description_default_to_null(): void {
		$rule = $this->make_rule(
			array(
				'id'          => null,
				'name'        => null,
				'description' => null,
			)
		);

		$this->assertNull( $rule->id() );
		$this->assertNull( $rule->name() );
		$this->assertNull( $rule->description() );
	}

	/**
	 * @testdox It should be possible to reject a Regex_Rule with an empty pattern
	 */
	public function test_rejects_empty_pattern(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Regex_Rule: pattern must not be empty.' );

		$this->make_rule( array( 'pattern' => '' ) );
	}

	/**
	 * @testdox It should be possible to reject a Regex_Rule with no comment parts selected
	 */
	public function test_rejects_empty_comment_parts(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Regex_Rule: at least one comment part must be selected.' );

		$this->make_rule( array( 'comment_parts' => Comment_Parts::none() ) );
	}
}
