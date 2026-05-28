<?php
/**
 * Integration test for Load_Text_Domain.
 *
 * Pins the WP 6.7+ contract: the plugin's text domain must be loaded on
 * `init` at priority 10 so any translation call later in the request does
 * not trip `_load_textdomain_just_in_time`'s doing-it-wrong notice.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Integration\I18n
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Integration\I18n;

use PinkCrab\Comment_Moderation\Application\I18n\Load_Text_Domain;
use PinkCrab\Loader\Hook_Loader;
use WP_UnitTestCase;

/**
 * @group integration
 */
class Test_Load_Text_Domain extends WP_UnitTestCase {

	/**
	 * Remove the listener attached in tests so it does not leak between cases.
	 */
	public function tear_down(): void {
		remove_all_actions( 'doing_it_wrong_run' );
		parent::tear_down();
	}

	/**
	 * @testdox It should attach load() to the init action at priority 10
	 */
	public function test_register_attaches_on_init_priority_10(): void {
		$sut    = new Load_Text_Domain();
		$loader = new Hook_Loader();

		$sut->register( $loader );
		$loader->register_hooks();

		$this->assertSame(
			10,
			has_action( 'init', array( $sut, 'load' ) ),
			'Load_Text_Domain must register its loader on the `init` action at priority 10 to comply with the WP 6.7+ text-domain timing rules.'
		);
	}

	/**
	 * @testdox It should load the plugin text domain without emitting the WP 6.7+ _load_textdomain_just_in_time doing-it-wrong notice
	 */
	public function test_load_does_not_emit_just_in_time_notice(): void {
		$notices = array();
		add_action(
			'doing_it_wrong_run',
			static function ( $function_name, $message, $version ) use ( &$notices ): void {
				$notices[] = array(
					'function' => (string) $function_name,
					'message'  => (string) $message,
					'version'  => (string) $version,
				);
			},
			10,
			3
		);

		// The WP test harness has already fired `init` by the time a test
		// method runs, so calling load() directly mirrors what happens when
		// the action fires in production — and any earlier translation calls
		// triggered by activation would have already surfaced the notice if
		// the load timing were wrong.
		( new Load_Text_Domain() )->load();

		// Force a translation call so anything that *would* trigger the
		// just-in-time fallback path runs now while we are listening.
		__( 'Comment Moderation', 'pinkcrab-comment-moderation' );

		$just_in_time = array_filter(
			$notices,
			static fn( array $notice ): bool => '_load_textdomain_just_in_time' === $notice['function']
		);

		$this->assertSame(
			array(),
			$just_in_time,
			'load_plugin_textdomain() on `init` must not trigger the `_load_textdomain_just_in_time` doing-it-wrong notice introduced in WP 6.7.'
		);
	}
}
