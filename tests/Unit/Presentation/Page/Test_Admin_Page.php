<?php
/**
 * Admin_Page unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Presentation\Page
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Presentation\Page;

use PinkCrab\Comment_Moderation\Application\Settings\Plugin_Config;
use PinkCrab\Comment_Moderation\Presentation\Page\Admin_Page;
use PinkCrab\Perique\Application\App_Config;
use PinkCrab\Perique_Admin_Menu\Page\Menu_Page;
use WP_UnitTestCase;

/**
 * Behavioural unit coverage for the Settings → Comment Moderation page:
 *  - lives under Settings (parent = options-general.php),
 *  - defaults to manage_options but routes through the
 *    `pccm_required_capability` filter,
 *  - exposes the constants the Elm bootstrap + e2e suite rely on.
 *
 * @group unit
 */
class Test_Admin_Page extends WP_UnitTestCase {

	/**
	 * Build a real Plugin_Config seeded for the page tests.
	 */
	private function make_config(): Plugin_Config {
		$app_config = new App_Config(
			array(
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
				'namespaces' => array( 'rest' => 'pccm/v1' ),
			)
		);

		return new Plugin_Config( $app_config );
	}

	/**
	 * @testdox It should extend the admin-menu Menu_Page base so the Perique admin-menu module can register it
	 */
	public function test_extends_menu_page(): void {
		$this->assertInstanceOf( Menu_Page::class, new Admin_Page( $this->make_config() ) );
	}

	/**
	 * @testdox It should live under Settings → Comment Moderation (parent slug options-general.php)
	 */
	public function test_parent_slug_is_settings(): void {
		$page = new Admin_Page( $this->make_config() );

		$this->assertSame( 'options-general.php', $page->parent_slug() );
	}

	/**
	 * @testdox It should expose the public PAGE_SLUG and use it as its registered slug
	 */
	public function test_slug_matches_public_constant(): void {
		$page = new Admin_Page( $this->make_config() );

		$this->assertSame( 'pinkcrab-comment-moderation', Admin_Page::PAGE_SLUG );
		$this->assertSame( Admin_Page::PAGE_SLUG, $page->slug() );
	}

	/**
	 * @testdox It should default the required capability to manage_options
	 */
	public function test_capability_defaults_to_manage_options(): void {
		$page = new Admin_Page( $this->make_config() );

		$this->assertSame( 'manage_options', $page->capability() );
	}

	/**
	 * @testdox It should expose the required capability through the pccm_required_capability filter
	 */
	public function test_capability_is_filterable(): void {
		$page = new Admin_Page( $this->make_config() );

		$callback = static function (): string {
			return 'edit_comments';
		};
		add_filter( Admin_Page::FILTER_CAPABILITY, $callback );

		try {
			$this->assertSame( 'edit_comments', $page->capability() );
		} finally {
			remove_filter( Admin_Page::FILTER_CAPABILITY, $callback );
		}
	}

	/**
	 * @testdox It should fall back to manage_options when the capability filter returns an unusable value
	 */
	public function test_capability_filter_falls_back_on_garbage(): void {
		$page = new Admin_Page( $this->make_config() );

		$callback = static function () {
			return '';
		};
		add_filter( Admin_Page::FILTER_CAPABILITY, $callback );

		try {
			$this->assertSame( 'manage_options', $page->capability() );
		} finally {
			remove_filter( Admin_Page::FILTER_CAPABILITY, $callback );
		}
	}

	/**
	 * @testdox It should localize the Elm bootstrap data onto the admin script as pccmAdminData when the page is rendered
	 */
	public function test_enqueue_registers_script_and_localized_data(): void {
		$page = new Admin_Page( $this->make_config() );

		// Run enqueue() as the admin-menu module would on the page's load hook.
		$page->enqueue( $page );

		try {
			$this->assertTrue(
				wp_script_is( Admin_Page::ASSET_HANDLE, 'registered' ),
				'Admin script should be registered after enqueue().'
			);

			$data = wp_scripts()->get_data( Admin_Page::ASSET_HANDLE, 'data' );
			$this->assertIsString( $data );
			$this->assertStringContainsString( Admin_Page::LOCALIZE_OBJECT, $data );
			$this->assertStringContainsString( Admin_Page::MOUNT_ID, $data );
			$this->assertStringContainsString( 'pccm/v1', $data, 'REST namespace should be localized.' );
			$this->assertStringContainsString( '"nonce"', $data );
		} finally {
			wp_dequeue_script( Admin_Page::ASSET_HANDLE );
			wp_deregister_script( Admin_Page::ASSET_HANDLE );
		}
	}
}
