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
use PinkCrab\Perique\Services\View\Component\Component_Compiler;
use PinkCrab\Perique\Services\View\PHP_Engine;
use PinkCrab\Perique\Services\View\View;
use PinkCrab\Perique_Admin_Menu\Page\Menu_Page;
use WP_UnitTestCase;

/**
 * Behavioural unit coverage for the Settings → Comment Moderation page:
 *  - lives under Settings (parent = options-general.php),
 *  - defaults to manage_options but routes through the
 *    `pccm_required_capability` filter,
 *  - exposes the constants the Elm bootstrap + e2e suite rely on,
 *  - routes its rendered markup through `pccm_admin_page_html` so an
 *    integrator can replace or restyle the whole screen (spec §11),
 *  - exposes a deep-link helper whose default `<a>` is filterable via
 *    `pccm_edit_rule_link` (spec §11 + §6).
 *
 * @group unit
 */
class Test_Admin_Page extends WP_UnitTestCase {

	/**
	 * Tear down — remove any extensibility filters added during a test so
	 * the next test sees a clean hook table.
	 */
	public function tear_down(): void {
		remove_all_filters( Admin_Page::FILTER_CAPABILITY );
		remove_all_filters( Admin_Page::FILTER_PAGE_HTML );
		remove_all_filters( Admin_Page::FILTER_EDIT_RULE_LINK );
		parent::tear_down();
	}

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
	 * Build a real Perique View backed by the project's `views/` directory
	 * so `render_view()` can render `views/pages/admin-page.php` for real.
	 */
	private function make_view(): View {
		$views_path = dirname( __DIR__, 4 ) . '/views';
		return new View(
			new PHP_Engine( $views_path ),
			new Component_Compiler( $views_path . '/components' )
		);
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

	// -----------------------------------------------------------------
	// pccm_admin_page_html — replace / restyle the management screen.
	// -----------------------------------------------------------------

	/**
	 * @testdox It should render the default admin shell when no integration filters pccm_admin_page_html
	 */
	public function test_render_view_emits_the_default_admin_shell(): void {
		$page = new Admin_Page( $this->make_config() );
		$page->set_view( $this->make_view() );

		ob_start();
		( $page->render_view() )();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="wrap pinkcrab-comment-moderation"', $html );
		$this->assertStringContainsString( 'id="' . Admin_Page::MOUNT_ID . '"', $html );
	}

	/**
	 * @testdox It should pass the rendered markup, and the Admin_Page instance, through the pccm_admin_page_html filter before echoing it
	 */
	public function test_render_view_passes_html_through_filter(): void {
		$page = new Admin_Page( $this->make_config() );
		$page->set_view( $this->make_view() );

		$received = null;
		add_filter(
			Admin_Page::FILTER_PAGE_HTML,
			static function ( string $html, Admin_Page $instance ) use ( &$received ): string {
				$received = array(
					'html'     => $html,
					'instance' => $instance,
				);
				return $html;
			},
			10,
			2
		);

		ob_start();
		( $page->render_view() )();
		ob_end_clean();

		$this->assertIsArray( $received, 'pccm_admin_page_html must fire with the rendered markup.' );
		$this->assertStringContainsString( Admin_Page::MOUNT_ID, $received['html'] );
		$this->assertSame( $page, $received['instance'], 'Filter must receive the Admin_Page instance as its second argument.' );
	}

	/**
	 * @testdox It should let an integrator replace the rendered admin screen entirely by returning a different string from pccm_admin_page_html
	 */
	public function test_render_view_filter_can_replace_screen(): void {
		$page = new Admin_Page( $this->make_config() );
		$page->set_view( $this->make_view() );

		add_filter(
			Admin_Page::FILTER_PAGE_HTML,
			static function (): string {
				return '<div class="custom-replacement">REPLACED</div>';
			}
		);

		ob_start();
		( $page->render_view() )();
		$html = (string) ob_get_clean();

		$this->assertSame( '<div class="custom-replacement">REPLACED</div>', $html );
		$this->assertStringNotContainsString( 'pinkcrab-comment-moderation', $html );
	}

	/**
	 * @testdox It should fall back to the default markup when pccm_admin_page_html returns a non-string value
	 */
	public function test_render_view_falls_back_when_filter_returns_garbage(): void {
		$page = new Admin_Page( $this->make_config() );
		$page->set_view( $this->make_view() );

		add_filter(
			Admin_Page::FILTER_PAGE_HTML,
			static function () {
				return 12345;
			}
		);

		ob_start();
		( $page->render_view() )();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="wrap pinkcrab-comment-moderation"', $html );
	}

	// -----------------------------------------------------------------
	// pccm_edit_rule_link — deep-link markup customisation.
	// -----------------------------------------------------------------

	/**
	 * @testdox It should build a default Edit Rule anchor pointing at the management screen with the pccm_edit query var
	 */
	public function test_edit_link_default_markup(): void {
		$markup = Admin_Page::edit_link( 42 );

		$this->assertStringContainsString( 'class="pccm-edit-rule-link"', $markup );
		$this->assertStringContainsString( 'page=' . Admin_Page::PAGE_SLUG, $markup );
		$this->assertStringContainsString( Admin_Page::EDIT_QUERY_VAR . '=42', $markup );
		$this->assertStringContainsString( '>Edit Rule</a>', $markup );
	}

	/**
	 * @testdox It should escape a caller-supplied label and inject it into the deep-link anchor
	 */
	public function test_edit_link_escapes_caller_supplied_label(): void {
		$markup = Admin_Page::edit_link( 7, 'Open <script>alert(1)</script>' );

		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $markup );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $markup );
	}

	/**
	 * @testdox It should pass the default markup, rule id, label, and URL through the pccm_edit_rule_link filter
	 */
	public function test_edit_link_filter_receives_full_context(): void {
		$received = null;
		add_filter(
			Admin_Page::FILTER_EDIT_RULE_LINK,
			static function ( string $markup, int $rule_id, string $label, string $url ) use ( &$received ): string {
				$received = compact( 'markup', 'rule_id', 'label', 'url' );
				return $markup;
			},
			10,
			4
		);

		Admin_Page::edit_link( 99, 'Review this rule' );

		$this->assertIsArray( $received, 'pccm_edit_rule_link must fire with the default anchor markup.' );
		$this->assertSame( 99, $received['rule_id'] );
		$this->assertSame( 'Review this rule', $received['label'] );
		$this->assertStringContainsString( Admin_Page::EDIT_QUERY_VAR . '=99', $received['url'] );
		$this->assertStringContainsString( 'pccm-edit-rule-link', $received['markup'] );
	}

	/**
	 * @testdox It should let an integrator replace the Edit Rule markup entirely via pccm_edit_rule_link
	 */
	public function test_edit_link_filter_can_replace_markup(): void {
		add_filter(
			Admin_Page::FILTER_EDIT_RULE_LINK,
			static function ( string $markup, int $rule_id, string $label, string $url ): string {
				return sprintf(
					'<button type="button" data-rule-id="%1$d" data-url="%2$s">%3$s</button>',
					$rule_id,
					esc_attr( $url ),
					esc_html( $label )
				);
			},
			10,
			4
		);

		$markup = Admin_Page::edit_link( 5, 'Edit' );

		$this->assertStringStartsWith( '<button', $markup );
		$this->assertStringContainsString( 'data-rule-id="5"', $markup );
		$this->assertStringContainsString( '>Edit</button>', $markup );
	}

	/**
	 * @testdox It should fall back to the default markup when pccm_edit_rule_link returns a non-string value
	 */
	public function test_edit_link_falls_back_when_filter_returns_garbage(): void {
		add_filter(
			Admin_Page::FILTER_EDIT_RULE_LINK,
			static function () {
				return null;
			}
		);

		$markup = Admin_Page::edit_link( 5 );

		$this->assertStringContainsString( 'pccm-edit-rule-link', $markup );
		$this->assertStringContainsString( Admin_Page::EDIT_QUERY_VAR . '=5', $markup );
	}

	/**
	 * @testdox It should expose stable PHP constants for every documented pccm_-prefixed hook the screen owns
	 */
	public function test_exposes_stable_constants_for_documented_hooks(): void {
		$this->assertSame( 'pccm_required_capability', Admin_Page::FILTER_CAPABILITY );
		$this->assertSame( 'pccm_admin_page_html', Admin_Page::FILTER_PAGE_HTML );
		$this->assertSame( 'pccm_edit_rule_link', Admin_Page::FILTER_EDIT_RULE_LINK );
		$this->assertSame( 'pccm_edit', Admin_Page::EDIT_QUERY_VAR );
	}
}
