<?php
/**
 * Comment_Submission value object unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Engine
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Engine;

use PinkCrab\Comment_Moderation\Domain\Engine\Comment_Submission;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use WP_UnitTestCase;

/**
 * Proves Comment_Submission carries the six commenter-facing fields and
 * exposes them both directly and via the Comment_Part lookup the engine
 * uses.
 *
 * @group unit
 */
class Test_Comment_Submission extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to read every field back via its named accessor
	 */
	public function test_accessors_return_constructor_values(): void {
		$submission = new Comment_Submission(
			'Alice',
			'alice@example.com',
			'https://alice.example',
			'203.0.113.5',
			'Mozilla/5.0',
			'Hello, world!'
		);

		$this->assertSame( 'Alice', $submission->name() );
		$this->assertSame( 'alice@example.com', $submission->email() );
		$this->assertSame( 'https://alice.example', $submission->url() );
		$this->assertSame( '203.0.113.5', $submission->ip() );
		$this->assertSame( 'Mozilla/5.0', $submission->user_agent() );
		$this->assertSame( 'Hello, world!', $submission->content() );
	}

	/**
	 * @testdox It should be possible to look up any field by Comment_Part enum value
	 */
	public function test_part_lookup_returns_matching_field(): void {
		$submission = new Comment_Submission(
			'Alice',
			'alice@example.com',
			'https://alice.example',
			'203.0.113.5',
			'Mozilla/5.0',
			'Hello, world!'
		);

		$this->assertSame( 'Alice', $submission->part( Comment_Part::name() ) );
		$this->assertSame( 'alice@example.com', $submission->part( Comment_Part::email() ) );
		$this->assertSame( 'https://alice.example', $submission->part( Comment_Part::url() ) );
		$this->assertSame( '203.0.113.5', $submission->part( Comment_Part::ip() ) );
		$this->assertSame( 'Mozilla/5.0', $submission->part( Comment_Part::user_agent() ) );
		$this->assertSame( 'Hello, world!', $submission->part( Comment_Part::content() ) );
	}

	/**
	 * @testdox It should be possible to construct a Comment_Submission with no arguments and read empty strings for every field
	 */
	public function test_defaults_to_empty_strings(): void {
		$submission = new Comment_Submission();

		foreach ( Comment_Part::all() as $part ) {
			$this->assertSame( '', $submission->part( $part ), "Default for {$part->value()} should be empty" );
		}
	}
}
