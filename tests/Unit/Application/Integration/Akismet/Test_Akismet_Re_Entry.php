<?php
/**
 * Akismet_Integration re-entry regression test.
 *
 * Guards the cross-closure coordination introduced in stage 6: when two
 * `on_failed_rule()` invocations are made in the same PHP request with
 * different captured submissions, firing `comment_post` once for comment
 * A's id must dispatch comment A's matched rule payload to Akismet and
 * never comment B's. The corresponding scenario in
 * `Test_Akismet_Integration` asserts only the dispatched comment id;
 * this file additionally asserts the *message payload* so a regression
 * that crossed captures (e.g. closure A picking up rule B's note) would
 * be caught even when the id is right by accident.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Application\Integration\Akismet
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Application\Integration\Akismet;

use DateTimeImmutable;
use DateTimeZone;
use PinkCrab\Comment_Moderation\Application\Integration\Akismet\Akismet_Integration;
use PinkCrab\Comment_Moderation\Domain\Engine\Comment_Submission;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use WP_UnitTestCase;

/**
 * Regression: two diversions, one `comment_post`, correct payload wins.
 *
 * @group unit
 */
class Test_Akismet_Re_Entry extends WP_UnitTestCase {

	/**
	 * UTC clock instant used to anchor Usage_Stats in the test.
	 */
	private DateTimeImmutable $now;

	/**
	 * Stash for WordPress's own `comment_post` listeners — see the
	 * matching block in Test_Akismet_Integration for why this is necessary
	 * (the synthetic-id flow trips core listeners that expect a real row).
	 *
	 * @var array<int,array<int,array{function:mixed,accepted_args:int}>>
	 */
	private array $stashed_comment_post_listeners = array();

	/**
	 * Isolate `comment_post` from WordPress core listeners and pin the
	 * clock so Usage_Stats are deterministic.
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
	 * Restore the WP core `comment_post` listeners and clear the
	 * integration's filter/action so each test sees a clean hook table.
	 */
	public function tear_down(): void {
		remove_all_actions( 'comment_post' );
		remove_all_filters( Akismet_Integration::FILTER_ENABLED );

		global $wp_filter;
		if ( isset( $wp_filter['comment_post'] ) && array() !== $this->stashed_comment_post_listeners ) {
			$wp_filter['comment_post']->callbacks = $this->stashed_comment_post_listeners;
			$this->stashed_comment_post_listeners = array();
		}

		parent::tear_down();
	}

	/**
	 * Build a wildcard rule with the supplied response and name. The name
	 * is the discriminator the assertion relies on — it appears in the
	 * Akismet history message so a crossed capture would be visible there.
	 *
	 * @param Response $response Outcome to apply on match.
	 * @param string   $name     Human label embedded in the history note.
	 *
	 * @return Rule
	 */
	private function rule( Response $response, string $name ): Rule {
		return new Wildcard_Rule(
			42,
			$name,
			null,
			'*@spam-domain.tld',
			Comment_Parts::of( Comment_Part::email() ),
			$response,
			new Usage_Stats( 0, null, $this->now )
		);
	}

	/**
	 * @testdox It should dispatch comment A's rule payload — not comment B's — when comment_post fires once with comment A's id
	 */
	public function test_two_diversions_dispatch_only_the_matching_closures_payload(): void {
		$gateway     = new Fake_Akismet_Gateway( true );
		$integration = new Akismet_Integration( $gateway );

		// Comment A — the one whose `comment_post` will fire.
		$post_id_a = self::factory()->post->create();
		$comment_a = (int) self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id_a,
				'comment_author'       => 'Alice',
				'comment_author_email' => 'alice@example.com',
				'comment_author_IP'    => '203.0.113.5',
				'comment_agent'        => 'Mozilla/5.0',
				'comment_content'      => 'Hi from Alice!',
			)
		);

		// Comment B — same request, different fields. Its `comment_post`
		// is NOT fired in this test; closure B exists only to prove that
		// it cannot hijack comment A's dispatch.
		$post_id_b = self::factory()->post->create();
		(int) self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id_b,
				'comment_author'       => 'Bob',
				'comment_author_email' => 'bob@example.com',
				'comment_author_IP'    => '198.51.100.20',
				'comment_agent'        => 'Mozilla/5.0 (Bob)',
				'comment_content'      => 'Hello from Bob.',
			)
		);

		$submission_a = new Comment_Submission( 'Alice', 'alice@example.com', '', '203.0.113.5', 'Mozilla/5.0', 'Hi from Alice!' );
		$submission_b = new Comment_Submission( 'Bob', 'bob@example.com', '', '198.51.100.20', 'Mozilla/5.0 (Bob)', 'Hello from Bob.' );

		// Distinct rules + responses so the history-note text and the
		// submit_spam / no-call branch are independently checkable.
		$rule_a = $this->rule( Response::spam(), 'Rule A — Alice trap' );
		$rule_b = $this->rule( Response::trash(), 'Rule B — Bob trap' );

		$integration->on_failed_rule( $rule_a, $submission_a, array() );
		$integration->on_failed_rule( $rule_b, $submission_b, array() );

		// Fire `comment_post` exactly once, for comment A.
		do_action( 'comment_post', $comment_a, 'spam', array() );

		// Closure A identity-matches comment A and dispatches its own
		// captured rule payload. Closure B's identity check mismatches
		// and removes itself without touching the gateway.
		$this->assertCount( 1, $gateway->history_notes, 'Only one diversion should reach the gateway when `comment_post` fires once for comment A.' );
		$this->assertSame( $comment_a, $gateway->history_notes[0]['comment_id'], 'The dispatched history note must be attached to comment A.' );
		$this->assertStringContainsString( 'Rule A — Alice trap', $gateway->history_notes[0]['message'], 'The history note must carry rule A\'s name — a crossed capture would put rule B\'s name here.' );
		$this->assertStringNotContainsString( 'Rule B — Bob trap', $gateway->history_notes[0]['message'], 'Rule B\'s payload must NOT leak into comment A\'s dispatch.' );

		// Rule A is Spam, so submit_spam(A) — not (B), not (nothing).
		$this->assertSame( array( $comment_a ), $gateway->spam_reports, 'Spam outcome of rule A must report comment A to Akismet as spam.' );
		$this->assertSame( array(), $gateway->ham_reports );
	}
}
