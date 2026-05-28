<?php
/**
 * Registration list contract.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit;

use WP_UnitTestCase;

/**
 * Pins the FQCN list returned by config/registration.php after the
 * scaffold Hello_World cluster was retired.
 *
 * @group unit
 */
class Test_Registration extends WP_UnitTestCase {

	/**
	 * Path to the registration definition. Resolved once per test class.
	 */
	private static function registration_file(): string {
		return dirname( __DIR__, 2 ) . '/config/registration.php';
	}

	/**
	 * @testdox It should be possible to load config/registration.php and receive an array of FQCN strings
	 */
	public function test_registration_returns_array_of_class_strings(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/wordpress/' );
		}

		$registered = require self::registration_file();

		$this->assertIsArray( $registered );
		$this->assertNotEmpty( $registered );
		foreach ( $registered as $fqcn ) {
			$this->assertIsString( $fqcn );
			$this->assertTrue(
				class_exists( $fqcn ),
				"Registered class {$fqcn} should be autoloadable."
			);
		}
	}

	/**
	 * @testdox It should not be possible to find the retired Hello_World scaffold Hookable in the registration list
	 */
	public function test_registration_no_longer_lists_hello_world(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/wordpress/' );
		}

		$registered = require self::registration_file();
		$hello      = 'PinkCrab\\Comment_Moderation\\Presentation\\Hook\\Hello_World';

		$this->assertNotContains( $hello, $registered );
		$this->assertFalse(
			class_exists( $hello ),
			'Hello_World scaffold class should no longer be autoloadable after stage 1 removal.'
		);
	}
}
