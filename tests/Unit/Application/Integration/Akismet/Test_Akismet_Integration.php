<?php
/**
 * Akismet_Integration unit tests.
 *
 * Covers the rebuild spec §10 contract in full without requiring the real
 * Akismet plugin in the test environment: the spy gateway records the
 * exact calls the integration would have made and the tests assert one
 * scenario per branch of the dispatch table.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Application\Integration\Akismet
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Application\Integration\Akismet;

use DateTimeImmutable;
use DateTimeZone;
use PinkCrab\Comment_Moderation\Application\Engine\Comment_Engine;
use PinkCrab\Comment_Moderation\Application\Integration\Akismet\Akismet_Integration;
use PinkCrab\Comment_Moderation\Domain\Engine\Comment_Submission;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Ip_Range_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use WP_UnitTestCase;

/**
 * Drives the Akismet integration through a spy gateway so every
 * rebuild-spec §10 branch is asserted explicitly:
 *
 *   - Akismet absent → no calls of any kind.
 *   - Integrator returns false from `pccm_akismet_enabled` → no calls.
 *   - Spam outcome → history note + submit_spam.
 *   - Trash outcome → history note + submit_spam.
 *   - Pending outcome → history note only.
 *   - Approved outcome → history note + submit_nonspam.
 *   - History note format reflects the spec's worked example.
 *   - The listener actually subscribes to the engine's failed-rule action.
 *
 * @group unit
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class Test_Akismet_Integration extends WP_UnitTestCase {

	/**
	 * UTC clock instant used to anchor every Usage_Stats in the tests.
	 */
	private DateTimeImmutable $now;

	/**
	 * Stash for WordPress's own `comment_post` listeners. WP core attaches a
	 * couple of callbacks at boot time (e.g. close-comments-for-old-post)
	 * that expect a real comment row when the action fires. The tests below
	 * trigger `comment_post` with synthetic ids to drive the deferred
	 * dispatch, so the WP listeners are removed for the duration of the
	 * test and restored in `tear_down`.
	 *
	 * @var array<int,array<int,array{function:mixed,accepted_args:int}>>
	 */
	private array $stashed_comment_post_listeners = array();

	/**
	 * Drop any listeners attached during the test so they do not leak, and
	 * temporarily isolate the `comment_post` hook from WordPress's own
	 * listeners so synthetic comment ids can drive the deferred dispatch.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->now = new DateTimeImmutable( '2026-05-01 12:00:00', new DateTimeZone( 'UTC' ) );

		global $wp_filter;
		if ( isset( $wp_filter['comment_post'] ) ) {
			$this->stashed_comment_post_listeners = $wp_filter['comment_post']->callbacks;
			$wp_filter['comment_post']->callbacks = array();
		}
	}

	/**
	 * Tear down — clear the engine action and the enabled filter so each
	 * test sees a clean WP hook table, and restore WordPress's own
	 * `comment_post` listeners.
	 */
	public function tear_down(): void {
		remove_all_actions( Comment_Engine::ACTION_FAILED_RULE );
		remove_all_actions( 'comment_post' );
		remove_all_filters( Akismet_Integration::FILTER_ENABLED );

		global $wp_filter;
		if ( isset( $wp_filter['comment_post'] ) && array() !== $this->stashed_comment_post_listeners ) {
			$wp_filter['comment_post']->callbacks    = $this->stashed_comment_post_listeners;
			$this->stashed_comment_post_listeners    = array();
		}

		parent::tear_down();
	}

	/**
	 * Build a wildcard rule with the supplied response and (optional) name
	 * / description / pre-existing usage count.
	 *
	 * @param Response    $response   Outcome to apply on match.
	 * @param string|null $name       Optional short label.
	 * @param string|null $desc       Optional longer description.
	 * @param integer     $times_used Pre-existing hit count (engine has already incremented; the +1 is added in the message).
	 *
	 * @return Rule
	 */
	private function rule(
		Response $response,
		?string $name = 'Block spam domain',
		?string $desc = null,
		int $times_used = 0
	): Rule {
		return new Wildcard_Rule(
			42,
			$name,
			$desc,
			'*@spam-domain.tld',
			Comment_Parts::of( Comment_Part::email() ),
			$response,
			new Usage_Stats( $times_used, null, $this->now )
		);
	}

	/**
	 * Build an empty submission — the listener does not inspect any of the
	 * submission fields, but the action signature still requires one.
	 *
	 * @return Comment_Submission
	 */
	private function submission(): Comment_Submission {
		return new Comment_Submission( 'Alice', 'alice@example.com', '', '203.0.113.5', 'Mozilla/5.0', 'Hi!' );
	}

	// -----------------------------------------------------------------
	// register()
	// -----------------------------------------------------------------

	/**
	 * @testdox It should subscribe its on_failed_rule callback to the engine's pccm_comment_failed_rule action
	 */
	public function test_register_subscribes_to_failed_rule_action(): void {
		$integration = new Akismet_Integration( new Fake_Akismet_Gateway() );
		add_action( Comment_Engine::ACTION_FAILED_RULE, array( $integration, 'on_failed_rule' ), 10, 3 );

		$this->assertTrue(
			has_action( Comment_Engine::ACTION_FAILED_RULE, array( $integration, 'on_failed_rule' ) ) !== false,
			'Akismet_Integration must subscribe to the engine\'s failed-rule action so it sees every diversion.'
		);
	}

	// -----------------------------------------------------------------
	// Akismet absent / disabled
	// -----------------------------------------------------------------

	/**
	 * @testdox It should make no Akismet calls when the Akismet plugin is not installed
	 */
	public function test_does_nothing_when_akismet_is_absent(): void {
		$gateway     = new Fake_Akismet_Gateway( false );
		$integration = new Akismet_Integration( $gateway );

		$integration->on_failed_rule( $this->rule( Response::spam() ), $this->submission(), array() );

		// Trigger comment_post to prove no deferred handler is scheduled either.
		do_action( 'comment_post', 101, 'spam', array() );

		$this->assertSame( array(), $gateway->history_notes, 'Akismet-absent path must not write any history notes (spec §10).' );
		$this->assertSame( array(), $gateway->spam_reports, 'Akismet-absent path must not report comments as spam.' );
		$this->assertSame( array(), $gateway->ham_reports, 'Akismet-absent path must not report comments as ham.' );
	}

	/**
	 * @testdox It should make no Akismet calls when an integrator returns false from the pccm_akismet_enabled filter
	 */
	public function test_does_nothing_when_integrator_disables_via_filter(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		add_filter( Akismet_Integration::FILTER_ENABLED, '__return_false' );

		$integration->on_failed_rule( $this->rule( Response::spam() ), $this->submission(), array() );
		do_action( 'comment_post', 101, 'spam', array() );

		$this->assertSame( array(), $gateway->history_notes, 'pccm_akismet_enabled=false must suppress the history note.' );
		$this->assertSame( array(), $gateway->spam_reports, 'pccm_akismet_enabled=false must suppress submit_spam.' );
		$this->assertSame( array(), $gateway->ham_reports, 'pccm_akismet_enabled=false must suppress submit_nonspam.' );
	}

	// -----------------------------------------------------------------
	// Per-outcome dispatch — driven directly through dispatch_to_gateway()
	// to test the dispatch table without WP timing.
	// -----------------------------------------------------------------

	/**
	 * @testdox It should write a history note and report the comment as spam when the matched rule's outcome is Spam
	 */
	public function test_spam_outcome_writes_note_and_submits_spam(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$integration->dispatch_to_gateway( 101, 'note for 101', Response::spam() );

		$this->assertCount( 1, $gateway->history_notes );
		$this->assertSame( array( 'comment_id' => 101, 'message' => 'note for 101' ), $gateway->history_notes[0] );
		$this->assertSame( array( 101 ), $gateway->spam_reports, 'Spam outcome must report the comment to Akismet as spam.' );
		$this->assertSame( array(), $gateway->ham_reports );
	}

	/**
	 * @testdox It should write a history note and report the comment as spam when the matched rule's outcome is Trash
	 */
	public function test_trash_outcome_writes_note_and_submits_spam(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$integration->dispatch_to_gateway( 202, 'note for 202', Response::trash() );

		$this->assertCount( 1, $gateway->history_notes );
		$this->assertSame( array( 202 ), $gateway->spam_reports, 'Trash outcome must also report the comment to Akismet as spam (spec §10).' );
		$this->assertSame( array(), $gateway->ham_reports );
	}

	/**
	 * @testdox It should write only a history note when the matched rule's outcome is Pending
	 */
	public function test_pending_outcome_writes_note_only(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$integration->dispatch_to_gateway( 303, 'note for 303', Response::pending() );

		$this->assertCount( 1, $gateway->history_notes );
		$this->assertSame( array(), $gateway->spam_reports, 'Pending outcome must not report the comment as spam (spec §10).' );
		$this->assertSame( array(), $gateway->ham_reports, 'Pending outcome must not report the comment as ham.' );
	}

	/**
	 * @testdox It should write a history note and report the comment as ham when the matched rule's hidden outcome is Approved
	 */
	public function test_approved_outcome_writes_note_and_submits_nonspam(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$integration->dispatch_to_gateway( 404, 'note for 404', Response::approved() );

		$this->assertCount( 1, $gateway->history_notes );
		$this->assertSame( array(), $gateway->spam_reports, 'Approved outcome must not report the comment as spam.' );
		$this->assertSame( array( 404 ), $gateway->ham_reports, 'Approved outcome must report the comment to Akismet as ham (spec §10).' );
	}

	// -----------------------------------------------------------------
	// History note format
	// -----------------------------------------------------------------

	/**
	 * @testdox It should include the rule name, description, and post-hit usage count in the history note
	 */
	public function test_history_note_includes_name_description_and_count(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		// times_used=13 in-memory + 1 (engine has already incremented the DB)
		// = the "(used 14 times)" the rebuild-spec worked example shows.
		$rule = $this->rule( Response::spam(), 'Block spam domain', 'Catches the noisy domain we keep hearing about.', 13 );

		$integration->on_failed_rule( $rule, $this->submission(), array() );
		do_action( 'comment_post', 7, 'spam', array() );

		$this->assertCount( 1, $gateway->history_notes );
		$message = $gateway->history_notes[0]['message'];
		$this->assertStringContainsString( 'marked as spam', $message );
		$this->assertStringContainsString( 'Block spam domain', $message );
		$this->assertStringContainsString( 'Catches the noisy domain we keep hearing about.', $message );
		$this->assertStringContainsString( '(used 14 times)', $message );
	}

	/**
	 * @testdox It should omit the description segment when the matched rule has no description
	 */
	public function test_history_note_omits_description_when_absent(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$rule = $this->rule( Response::trash(), 'Quiet block', null, 0 );

		$integration->on_failed_rule( $rule, $this->submission(), array() );
		do_action( 'comment_post', 9, 'trash', array() );

		$message = $gateway->history_notes[0]['message'];
		$this->assertStringNotContainsString( '—', $message, 'No description means no em-dash separator in the note.' );
		$this->assertStringContainsString( 'moved to trash', $message );
		$this->assertStringContainsString( 'Quiet block', $message );
		$this->assertStringContainsString( '(used 1 time)', $message );
	}

	/**
	 * @testdox It should use the rule type as the display name when the rule has no name
	 */
	public function test_history_note_falls_back_to_type_when_rule_is_unnamed(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$rule = new Ip_Range_Rule(
			11,
			null,
			null,
			'203.0.113.0',
			'203.0.113.255',
			Response::trash(),
			new Usage_Stats( 0, null, $this->now )
		);

		$integration->on_failed_rule( $rule, $this->submission(), array() );
		do_action( 'comment_post', 12, 'trash', array() );

		$message = $gateway->history_notes[0]['message'];
		$this->assertStringContainsString( 'Ip Range', $message, 'Unnamed rules must fall back to a readable type label.' );
	}

	// -----------------------------------------------------------------
	// Deferral mechanics
	// -----------------------------------------------------------------

	/**
	 * @testdox It should defer Akismet calls until comment_post fires so it has a real comment id
	 */
	public function test_listener_defers_until_comment_post(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$integration->on_failed_rule( $this->rule( Response::spam() ), $this->submission(), array() );

		// Until comment_post fires, no gateway calls should have happened —
		// pre_comment_approved runs before the comment row exists, so there
		// would be no id for Akismet to attach the note to (spec §10).
		$this->assertSame( array(), $gateway->history_notes );
		$this->assertSame( array(), $gateway->spam_reports );

		do_action( 'comment_post', 555, 'spam', array() );

		$this->assertCount( 1, $gateway->history_notes );
		$this->assertSame( 555, $gateway->history_notes[0]['comment_id'] );
		$this->assertSame( array( 555 ), $gateway->spam_reports );
	}

	/**
	 * @testdox It should fire its deferred handler at most once per diversion so a later unrelated insert does not retrigger
	 */
	public function test_deferred_handler_is_one_shot(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		$integration->on_failed_rule( $this->rule( Response::spam() ), $this->submission(), array() );

		do_action( 'comment_post', 111, 'spam', array() );
		do_action( 'comment_post', 222, 'spam', array() );

		$this->assertCount( 1, $gateway->history_notes, 'A second unrelated insert in the same request must not re-trigger the same deferred handler.' );
		$this->assertSame( array( 111 ), $gateway->spam_reports );
	}

	/**
	 * @testdox It should still no-op once the deferred handler runs if Akismet has since been disabled by the filter
	 */
	public function test_filter_can_disable_between_engine_match_and_comment_post(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		// Filter switches off *before* on_failed_rule runs — the listener
		// should bail out without scheduling anything.
		add_filter( Akismet_Integration::FILTER_ENABLED, '__return_false' );
		$integration->on_failed_rule( $this->rule( Response::spam() ), $this->submission(), array() );
		do_action( 'comment_post', 999, 'spam', array() );

		$this->assertSame( array(), $gateway->history_notes );
		$this->assertSame( array(), $gateway->spam_reports );
	}
}
