<?php
/**
 * Hello_World end-to-end test.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Integration\Presentation\Hook
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Integration\Presentation\Hook;

use WP_UnitTestCase;

/**
 * Exercises the whole Hookable → Component → View → asset-enqueue pipeline
 * through a real WordPress + Perique boot.
 *
 * If this test passes after `composer build`, you've also implicitly
 * verified that php-scoper rewrote the `use Webmozart\Assert\Assert;` inside
 * `Hello_World::render_shortcode()` to point at the scoped namespace — if
 * scoper missed it, the call would fatal here with
 * "Class \Webmozart\Assert\Assert not found".
 *
 * @group integration
 */
class Test_Hello_World extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to render the Hello World shortcode with no arguments and get a friendly greeting back
	 */
	public function test_shortcode_renders_with_default_name(): void {
		$output = do_shortcode( '[pinkcrab_comment_moderation_hello]' );

		$this->assertStringContainsString( 'Hello, World!', $output );
		$this->assertStringContainsString( 'pinkcrab-comment-moderation-hello-world', $output );
	}

	/**
	 * @testdox It should be possible to render the Hello World shortcode with a custom name and get a greeting addressed to that name
	 */
	public function test_shortcode_renders_with_custom_name(): void {
		$output = do_shortcode( '[pinkcrab_comment_moderation_hello name="Glynn"]' );

		$this->assertStringContainsString( 'Hello, Glynn!', $output );
		$this->assertStringContainsString( 'pinkcrab-comment-moderation-hello-world', $output );
	}

	/**
	 * @testdox It should be possible to confirm the shortcode is registered with WordPress before any rendering call
	 */
	public function test_shortcode_is_registered(): void {
		$this->assertTrue( shortcode_exists( 'pinkcrab_comment_moderation_hello' ) );
	}

	/**
	 * @testdox It should be possible to enqueue the scoped Hello World assets when the front-end loads its scripts
	 */
	public function test_assets_register_on_wp_enqueue_scripts(): void {
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_script_is( 'pinkcrab-comment-moderation-hello-world', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'pinkcrab-comment-moderation-hello-world', 'enqueued' ) );
	}
}
