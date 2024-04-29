<?php

/**
 * Sample Test
 *
 * @package PinkCrab/Tests
 */

class Test_Test extends WP_UnitTestCase {

	/** @testdox Check that core WP functions are loaded in,. */
	function test_wordpress_and_plugin_are_loaded() {
		$this->assertTrue( function_exists( 'do_action' ) );
	}

	/** @testdox Check that WP PHPUNIT has been loaded in via composer */
	function test_wp_phpunit_is_loaded_via_composer() {
		$this->assertStringStartsWith(
			dirname( __DIR__ ) . '/vendor/',
			getenv( 'WP_PHPUNIT__DIR' )
		);

		$this->assertStringStartsWith(
			dirname( __DIR__ ) . '/vendor/',
			( new ReflectionClass( 'WP_UnitTestCase' ) )->getFileName()
		);
	}
}
