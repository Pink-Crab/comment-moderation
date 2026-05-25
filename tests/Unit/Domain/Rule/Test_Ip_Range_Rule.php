<?php
/**
 * Ip_Range_Rule unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Rule;

use DateTimeImmutable;
use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Ip_Range_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use WP_UnitTestCase;

/**
 * Proves Ip_Range_Rule satisfies the Rule contract, exposes the start/end
 * IPs, restricts inspection to the IP comment part, and enforces the
 * "first-three-blocks-must-match" / valid-IP / ordered-range constraints
 * from rebuild spec §4.3.
 *
 * @group unit
 */
class Test_Ip_Range_Rule extends WP_UnitTestCase {

	/**
	 * @param array<string,mixed> $overrides Partial constructor args.
	 */
	private function make_rule( array $overrides = array() ): Ip_Range_Rule {
		$defaults = array(
			'id'          => null,
			'name'        => 'Block office block',
			'description' => null,
			'start_ip'    => '203.0.113.10',
			'end_ip'      => '203.0.113.40',
			'response'    => Response::trash(),
			'usage_stats' => Usage_Stats::fresh( new DateTimeImmutable( '2026-05-01' ) ),
		);
		$args     = array_replace( $defaults, $overrides );

		return new Ip_Range_Rule(
			$args['id'],
			$args['name'],
			$args['description'],
			$args['start_ip'],
			$args['end_ip'],
			$args['response'],
			$args['usage_stats']
		);
	}

	/**
	 * @testdox It should be possible to confirm Ip_Range_Rule implements the Rule contract
	 */
	public function test_implements_rule_interface(): void {
		$this->assertInstanceOf( Rule::class, $this->make_rule() );
	}

	/**
	 * @testdox It should be possible to read the IP range rule type from an Ip_Range_Rule instance
	 */
	public function test_reports_ip_range_type(): void {
		$this->assertTrue( $this->make_rule()->type()->equals( Rule_Type::ip_range() ) );
	}

	/**
	 * @testdox It should be possible to read the start and end IP addresses back from an Ip_Range_Rule instance
	 */
	public function test_exposes_start_and_end_ip(): void {
		$rule = $this->make_rule(
			array(
				'start_ip' => '198.51.100.5',
				'end_ip'   => '198.51.100.255',
			)
		);

		$this->assertSame( '198.51.100.5', $rule->start_ip() );
		$this->assertSame( '198.51.100.255', $rule->end_ip() );
	}

	/**
	 * @testdox It should be possible to confirm an Ip_Range_Rule always reports the IP comment part as its only inspected slice
	 */
	public function test_comment_parts_is_always_ip_only(): void {
		$parts = $this->make_rule()->comment_parts();

		$this->assertCount( 1, $parts );
		$this->assertTrue( $parts->has( Comment_Part::ip() ) );
		$this->assertSame( array( 'ip' ), $parts->values() );
	}

	/**
	 * @testdox It should be possible to reject an Ip_Range_Rule whose start IP is not a valid IPv4 address
	 */
	public function test_rejects_invalid_start_ip(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Ip_Range_Rule: start IP "not-an-ip" is not a valid IPv4 address.' );

		$this->make_rule( array( 'start_ip' => 'not-an-ip' ) );
	}

	/**
	 * @testdox It should be possible to reject an Ip_Range_Rule whose end IP is not a valid IPv4 address
	 */
	public function test_rejects_invalid_end_ip(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Ip_Range_Rule: end IP "999.0.0.0" is not a valid IPv4 address.' );

		$this->make_rule( array( 'end_ip' => '999.0.0.0' ) );
	}

	/**
	 * @testdox It should be possible to reject an Ip_Range_Rule whose start and end IPs differ in their first three octets
	 */
	public function test_rejects_mismatched_network(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Ip_Range_Rule: the network of the IP address (first 3 parts) must match.' );

		$this->make_rule(
			array(
				'start_ip' => '203.0.113.10',
				'end_ip'   => '203.0.114.10',
			)
		);
	}

	/**
	 * @testdox It should be possible to reject an Ip_Range_Rule whose end IP precedes its start IP
	 */
	public function test_rejects_reversed_range(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Ip_Range_Rule: start IP "203.0.113.40" must be <= end IP "203.0.113.10".' );

		$this->make_rule(
			array(
				'start_ip' => '203.0.113.40',
				'end_ip'   => '203.0.113.10',
			)
		);
	}

	/**
	 * @testdox It should be possible to accept an Ip_Range_Rule whose start and end IPs are identical
	 */
	public function test_accepts_single_address_range(): void {
		$rule = $this->make_rule(
			array(
				'start_ip' => '203.0.113.10',
				'end_ip'   => '203.0.113.10',
			)
		);

		$this->assertSame( '203.0.113.10', $rule->start_ip() );
		$this->assertSame( '203.0.113.10', $rule->end_ip() );
	}
}
