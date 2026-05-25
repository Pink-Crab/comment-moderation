<?php
/**
 * Validation_Result unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Application\Services
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Application\Services;

use PinkCrab\Comment_Moderation\Application\Services\Validation_Result;
use WP_UnitTestCase;

/**
 * Proves Validation_Result expresses success / failure outcomes and exposes
 * the collected error list in discovery order.
 *
 * @group unit
 */
class Test_Validation_Result extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to construct a passed result with no errors
	 */
	public function test_passed_result_is_valid(): void {
		$result = Validation_Result::passed();

		$this->assertTrue( $result->is_valid() );
		$this->assertSame( array(), $result->errors() );
		$this->assertNull( $result->first_error() );
	}

	/**
	 * @testdox It should be possible to construct a failed result carrying the provided errors in order
	 */
	public function test_failed_result_carries_errors_in_order(): void {
		$result = Validation_Result::failed( array( 'first', 'second', 'third' ) );

		$this->assertFalse( $result->is_valid() );
		$this->assertSame( array( 'first', 'second', 'third' ), $result->errors() );
		$this->assertSame( 'first', $result->first_error() );
	}

	/**
	 * @testdox It should be possible to reindex non-sequential error arrays so positional access stays predictable
	 */
	public function test_failed_result_reindexes_errors(): void {
		$result = Validation_Result::failed( array( 7 => 'a', 11 => 'b' ) );

		$this->assertSame( array( 0, 1 ), array_keys( $result->errors() ) );
		$this->assertSame( 'a', $result->first_error() );
	}
}
