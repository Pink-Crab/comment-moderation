<?php
/**
 * Plugin_Config unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Application\Settings
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Application\Settings;

use PinkCrab\Perique\Application\App_Config;
use WP_UnitTestCase;
use PinkCrab\Comment_Moderation\Application\Settings\Plugin_Config;

/**
 * Proves the test harness boots, the autoloader resolves the namespace, and
 * Plugin_Config delegates correctly to App_Config.
 *
 * App_Config is `final` — we construct a real instance with a test config
 * array rather than mocking. This also exercises the real
 * config-array-shape contract.
 *
 * @group unit
 */
class Test_Plugin_Config extends WP_UnitTestCase {

	/**
	 * Build a real App_Config seeded with the values a given test cares
	 * about. Unspecified keys fall back to App_Config's defaults.
	 *
	 * @param array<string,mixed> $overrides Partial config array.
	 */
	private function make_app_config( array $overrides = array() ): App_Config {
		$defaults = array(
			'path'       => array(
				'plugin' => '/var/www/plugin/',
				'assets' => '/var/www/plugin/assets/build',
				'view'   => '/var/www/plugin/views',
			),
			'url'        => array(
				'plugin' => 'https://example.org/wp-content/plugins/my-plugin/',
				'assets' => 'https://example.org/wp-content/plugins/my-plugin/assets/build',
				'view'   => 'https://example.org/wp-content/plugins/my-plugin/views',
			),
			'plugin'     => array( 'version' => '1.2.3' ),
			'namespaces' => array( 'rest' => 'my-plugin/v1' ),
		);

		return new App_Config( array_replace_recursive( $defaults, $overrides ) );
	}

	/**
	 * @testdox It should be possible to read the plugin version using the config helper class
	 */
	public function test_version_returns_configured_value(): void {
		$config = new Plugin_Config( $this->make_app_config() );

		$this->assertSame( '1.2.3', $config->version() );
	}

	/**
	 * @testdox It should be possible to read the plugin text domain using the config helper class
	 */
	public function test_text_domain_is_class_constant(): void {
		$config = new Plugin_Config( $this->make_app_config() );

		$this->assertSame( Plugin_Config::TEXT_DOMAIN, $config->text_domain() );
		$this->assertNotEmpty( $config->text_domain() );
	}

	/**
	 * @testdox It should be possible to get the defined REST namespace using the config helper class
	 */
	public function test_rest_namespace_returns_configured_value(): void {
		$config = new Plugin_Config(
			$this->make_app_config( array( 'namespaces' => array( 'rest' => 'foo/v2' ) ) )
		);

		$this->assertSame( 'foo/v2', $config->rest_namespace() );
	}

	/**
	 * @testdox It should be possible to get the assets base URL without supplying a sub-path
	 */
	public function test_asset_url_returns_base_when_no_relative_path(): void {
		$config = new Plugin_Config( $this->make_app_config() );

		// App_Config (Perique ^2.1) trailing-slashes url/path getters.
		$this->assertSame(
			'https://example.org/wp-content/plugins/my-plugin/assets/build/',
			$config->asset_url()
		);
	}

	/**
	 * @testdox It should be possible to build the URL for a specific asset by passing its relative path to the config helper class
	 */
	public function test_asset_url_concatenates_relative_path(): void {
		$config = new Plugin_Config(
			$this->make_app_config(
				array(
					'url' => array(
						'assets' => 'https://example.org/wp-content/plugins/my-plugin/assets/build/',
					),
				)
			)
		);

		$this->assertSame(
			'https://example.org/wp-content/plugins/my-plugin/assets/build/scripts/hello-world.js',
			$config->asset_url( 'scripts/hello-world.js' )
		);
		// Leading slashes on the relative path are stripped — only one between base and path.
		$this->assertSame(
			'https://example.org/wp-content/plugins/my-plugin/assets/build/styles/hello-world.css',
			$config->asset_url( '/styles/hello-world.css' )
		);
	}

	/**
	 * @testdox It should be possible to read the views directory path using the config helper class
	 */
	public function test_view_path_returns_configured_value(): void {
		$config = new Plugin_Config( $this->make_app_config() );

		$this->assertSame( '/var/www/plugin/views/', $config->view_path() );
	}
}
