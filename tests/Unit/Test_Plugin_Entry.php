<?php
/**
 * Plugin entry file contract.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit;

use WP_UnitTestCase;

/**
 * Pins the plugin entry file's load sequence after the empty helper-stub
 * `functions.php` was retired in stage 3.
 *
 * @group unit
 */
class Test_Plugin_Entry extends WP_UnitTestCase {

	/**
	 * @testdox It should no longer ship a root functions.php helper-stub
	 */
	public function test_functions_php_helper_stub_is_removed(): void {
		$this->assertFileDoesNotExist(
			dirname( __DIR__, 2 ) . '/functions.php',
			'functions.php was an empty helper-stub from the scaffold and should be removed.'
		);
	}

	/**
	 * @testdox It should not require functions.php from the plugin entry file
	 */
	public function test_plugin_entry_does_not_require_functions_php(): void {
		$entry = file_get_contents( dirname( __DIR__, 2 ) . '/pinkcrab-comment-moderation.php' );

		$this->assertIsString( $entry );
		$this->assertStringNotContainsString(
			"'functions.php'",
			$entry,
			'pinkcrab-comment-moderation.php should no longer load the retired functions.php stub.'
		);
	}
}
