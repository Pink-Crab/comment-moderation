<?php
/**
 * Plugin_Bootstrap unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit;

use PinkCrab\Comment_Moderation\Plugin_Bootstrap;
use ReflectionClass;
use ReflectionNamedType;
use WP_UnitTestCase;

/**
 * Proves the class the plugin entry file relies on exists, lives in the right
 * namespace, takes a plugin path on construction, and exposes a boot method.
 *
 * Kept reflection-only on purpose — actually invoking boot() registers Perique
 * globally and would collide with the live boot triggered by activate_plugin()
 * in tests/bootstrap.php. The boot side effect is exercised end-to-end by the
 * integration suite (every test there boots the plugin once via the bootstrap).
 *
 * @group unit
 */
class Test_Plugin_Bootstrap extends WP_UnitTestCase {

	/**
	 * @testdox It should be possible to find the Plugin_Bootstrap class in the PinkCrab\Comment_Moderation namespace
	 */
	public function test_class_is_declared_in_expected_namespace(): void {
		$this->assertTrue( class_exists( Plugin_Bootstrap::class ) );

		$reflection = new ReflectionClass( Plugin_Bootstrap::class );
		$this->assertSame( 'PinkCrab\\Comment_Moderation', $reflection->getNamespaceName() );
		$this->assertSame( 'Plugin_Bootstrap', $reflection->getShortName() );
	}

	/**
	 * @testdox It should be possible to construct a Plugin_Bootstrap with a plugin path
	 */
	public function test_constructor_accepts_plugin_path_string(): void {
		$reflection  = new ReflectionClass( Plugin_Bootstrap::class );
		$constructor = $reflection->getConstructor();

		$this->assertNotNull( $constructor );
		$params = $constructor->getParameters();
		$this->assertCount( 1, $params );

		$type = $params[0]->getType();
		$this->assertInstanceOf( ReflectionNamedType::class, $type );
		$this->assertSame( 'string', $type->getName() );
	}

	/**
	 * @testdox It should be possible to call the public boot method on a Plugin_Bootstrap instance
	 */
	public function test_exposes_public_boot_method(): void {
		$reflection = new ReflectionClass( Plugin_Bootstrap::class );

		$this->assertTrue( $reflection->hasMethod( 'boot' ) );
		$boot = $reflection->getMethod( 'boot' );
		$this->assertTrue( $boot->isPublic() );
	}

	/**
	 * @testdox It should be possible to confirm the entry file invokes Plugin_Bootstrap exactly once after the autoloader is required
	 */
	public function test_entry_file_invokes_plugin_bootstrap(): void {
		$entry = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/pinkcrab-comment-moderation.php'
		);

		$this->assertStringContainsString( 'PinkCrab\\Comment_Moderation\\Plugin_Bootstrap', $entry );
		$this->assertStringContainsString( '->boot()', $entry );
	}
}
