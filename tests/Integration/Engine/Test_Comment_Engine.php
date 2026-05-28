<?php
/**
 * End-to-end test for the invisible comment engine.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Integration\Engine
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Integration\Engine;

use DateTimeImmutable;
use DateTimeZone;
use PinkCrab\Comment_Moderation\Application\Engine\Comment_Engine;
use PinkCrab\Comment_Moderation\Domain\Engine\Comment_Submission;
use PinkCrab\Comment_Moderation\Domain\Engine\Rule_Evaluator;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Ip_Range_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Filter;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use PinkCrab\Comment_Moderation\Infrastructure\Persistence\Wpdb_Rule_Repository;
use PinkCrab\Perique\Application\App_Config;
use WP_UnitTestCase;

/**
 * Exercises Comment_Engine against the real wpdb-backed repository so the
 * full rebuild-spec §9 path is covered:
 *
 *   - pingbacks / trackbacks are ignored (§9.2),
 *   - reader comments are evaluated rule-by-rule in declared order (§9.4–§9.5),
 *   - the first-matching rule has its stats bumped (§9.6),
 *   - the `pccm_comment_failed_rule` action fires for integrations (§11),
 *   - the comment is diverted to the rule's outcome (§9.6),
 *   - a comment that matches nothing passes WordPress's decision through (§9.7).
 *
 * @group integration
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
class Test_Comment_Engine extends WP_UnitTestCase {

	/**
	 * Real wpdb-backed repository — exactly what runs in production.
	 */
	private Wpdb_Rule_Repository $repo;

	/**
	 * Subject under test.
	 */
	private Comment_Engine $engine;

	/**
	 * Boot a fresh repo + engine and empty the rules table for every test.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->repo = new Wpdb_Rule_Repository(
			$wpdb,
			new App_Config(
				array(
					'db_tables' => array( 'rules' => $wpdb->prefix . 'pccm_rules' ),
				)
			)
		);
		$this->repo->clear();
		$this->engine = new Comment_Engine( $this->repo, new Rule_Evaluator() );
	}

	/**
	 * Tear down hooks so listeners attached in one test do not leak into
	 * the next.
	 */
	public function tear_down(): void {
		remove_all_actions( Comment_Engine::ACTION_FAILED_RULE );
		remove_all_filters( Comment_Engine::FILTER_ENRICH_SUBMISSION );
		parent::tear_down();
	}

	/**
	 * Fresh stats anchored to a deterministic instant.
	 */
	private function fresh_stats(): Usage_Stats {
		return Usage_Stats::fresh( new DateTimeImmutable( '2026-05-01 00:00:00', new DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Build a representative `$commentdata` payload — the WordPress comment
	 * pipeline hands the filter an array of exactly these keys.
	 *
	 * @param array<string,mixed> $overrides Field overrides.
	 *
	 * @return array<string,mixed>
	 */
	private function commentdata( array $overrides = array() ): array {
		return array_merge(
			array(
				'comment_author'       => 'Alice',
				'comment_author_email' => 'alice@example.com',
				'comment_author_url'   => 'https://alice.example',
				'comment_author_IP'    => '203.0.113.5',
				'comment_agent'        => 'Mozilla/5.0',
				'comment_content'      => 'Hello world!',
				'comment_type'         => 'comment',
			),
			$overrides
		);
	}

	/**
	 * @testdox It should return the original approval untouched when no rule fires
	 */
	public function test_no_rule_match_passes_approval_through(): void {
		$this->repo->save(
			new Regex_Rule(
				null,
				'Casino',
				null,
				'/casino/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved( 1, $this->commentdata() );

		$this->assertSame( 1, $result, 'A legitimate comment with no matching rule should be left exactly as WordPress decided.' );
	}

	/**
	 * @testdox It should divert a matching comment to the rule's Spam outcome
	 */
	public function test_spam_response_returns_spam_string(): void {
		$this->repo->save(
			new Wildcard_Rule(
				null,
				'Block spam domain',
				null,
				'*@spam-domain.tld',
				Comment_Parts::of( Comment_Part::email() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_author_email' => 'bob@spam-domain.tld' ) )
		);

		$this->assertSame( 'spam', $result );
	}

	/**
	 * @testdox It should divert a matching comment to the rule's Pending outcome
	 */
	public function test_pending_response_returns_zero(): void {
		$this->repo->save(
			new Regex_Rule(
				null,
				'Held casino',
				null,
				'/casino/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::pending(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_content' => 'visit my CASINO' ) )
		);

		$this->assertSame( 0, $result );
	}

	/**
	 * @testdox It should divert a matching IP-range hit to the rule's Trash outcome
	 */
	public function test_trash_response_returns_trash_string(): void {
		$this->repo->save(
			new Ip_Range_Rule(
				null,
				'Noisy block',
				null,
				'203.0.113.0',
				'203.0.113.255',
				Response::trash(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_author_IP' => '203.0.113.42' ) )
		);

		$this->assertSame( 'trash', $result );
	}

	/**
	 * @testdox It should pass the original approval through when the matched rule is Approved (leave-as-is)
	 */
	public function test_approved_response_is_a_pass_through(): void {
		$this->repo->save(
			new Wildcard_Rule(
				null,
				'Trusted commenter',
				null,
				'alice@example.com',
				Comment_Parts::of( Comment_Part::email() ),
				Response::approved(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved( 0, $this->commentdata() );

		$this->assertSame(
			0,
			$result,
			'An Approved rule must not flip a pending decision to spam/trash; it leaves the comment as WordPress had it.'
		);
	}

	/**
	 * @testdox It should stop at the first matching rule and bump only that rule's stats
	 */
	public function test_first_match_wins_and_only_first_rule_stats_bump(): void {
		$first  = $this->repo->save(
			new Regex_Rule(
				null,
				'First match',
				null,
				'/casino/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);
		$second = $this->repo->save(
			new Regex_Rule(
				null,
				'Second match',
				null,
				'/casino/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::trash(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_content' => 'best CASINO ever' ) )
		);

		$this->assertSame( 'spam', $result, 'First-listed rule wins regardless of which outcome would be "stricter".' );

		$first_reloaded  = $this->repo->find( (int) $first->id() );
		$second_reloaded = $this->repo->find( (int) $second->id() );

		$this->assertNotNull( $first_reloaded );
		$this->assertNotNull( $second_reloaded );
		$this->assertSame( 1, $first_reloaded->usage_stats()->times_used() );
		$this->assertNotNull( $first_reloaded->usage_stats()->last_used() );
		$this->assertSame( 0, $second_reloaded->usage_stats()->times_used() );
		$this->assertNull( $second_reloaded->usage_stats()->last_used() );
	}

	/**
	 * @testdox It should leave last_updated untouched when a rule's hit counter increments
	 */
	public function test_record_hit_does_not_advance_last_updated(): void {
		$saved          = $this->repo->save(
			new Regex_Rule(
				null,
				'Casino',
				null,
				'/casino/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);
		$before_updated = $saved->usage_stats()->last_updated();

		$this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_content' => 'CASINO bonus' ) )
		);

		$reloaded = $this->repo->find( (int) $saved->id() );
		$this->assertNotNull( $reloaded );
		$this->assertSame(
			$before_updated->getTimestamp(),
			$reloaded->usage_stats()->last_updated()->getTimestamp(),
			'A rule firing must not advance last_updated — only an admin edit does (rebuild spec §6).'
		);
	}

	/**
	 * @testdox It should fire the pccm_comment_failed_rule action with the matched rule, submission, and raw commentdata
	 */
	public function test_action_fires_on_match(): void {
		$saved = $this->repo->save(
			new Regex_Rule(
				null,
				'Casino',
				null,
				'/casino/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);

		$captured = array();
		add_action(
			Comment_Engine::ACTION_FAILED_RULE,
			static function ( Rule $rule, Comment_Submission $submission, array $commentdata ) use ( &$captured ): void {
				$captured = array(
					'rule'        => $rule,
					'submission'  => $submission,
					'commentdata' => $commentdata,
				);
			},
			10,
			3
		);

		$commentdata = $this->commentdata( array( 'comment_content' => 'CASINO night' ) );
		$this->engine->filter_pre_comment_approved( 1, $commentdata );

		$this->assertNotEmpty( $captured, 'Listener should have been invoked when the rule matched.' );
		$this->assertSame( $saved->id(), $captured['rule']->id() );
		$this->assertSame( 'CASINO night', $captured['submission']->content() );
		$this->assertSame( $commentdata, $captured['commentdata'] );
	}

	/**
	 * @testdox It should let an integration enrich the submission via the pccm_comment_submission filter
	 */
	public function test_submission_can_be_enriched_by_filter(): void {
		$this->repo->save(
			new Regex_Rule(
				null,
				'Crypto',
				null,
				'/crypto/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);

		// Original body has no "crypto"; the enrichment filter folds in a
		// custom subject the engine should then match against.
		add_filter(
			Comment_Engine::FILTER_ENRICH_SUBMISSION,
			static function ( Comment_Submission $submission, array $commentdata ): Comment_Submission {
				$subject = isset( $commentdata['pccm_subject'] ) ? (string) $commentdata['pccm_subject'] : '';
				return new Comment_Submission(
					$submission->name(),
					$submission->email(),
					$submission->url(),
					$submission->ip(),
					$submission->user_agent(),
					trim( $subject . ' ' . $submission->content() )
				);
			},
			10,
			2
		);

		$result = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata(
				array(
					'comment_content' => 'Just a friendly hello',
					'pccm_subject'    => 'CRYPTO offer',
				)
			)
		);

		$this->assertSame( 'spam', $result );
	}

	/**
	 * @testdox It should ignore pingbacks and trackbacks and pass their approval through untouched
	 */
	public function test_pingback_and_trackback_skip_evaluation(): void {
		$this->repo->save(
			new Wildcard_Rule(
				null,
				'Catch everything',
				null,
				'*',
				Comment_Parts::of( Comment_Part::content() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);

		$pingback  = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_type' => 'pingback' ) )
		);
		$trackback = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_type' => 'trackback' ) )
		);

		$this->assertSame( 1, $pingback, 'Pingbacks must pass through untouched (spec §9.2).' );
		$this->assertSame( 1, $trackback, 'Trackbacks must pass through untouched (spec §9.2).' );
	}

	/**
	 * @testdox It should still pass non-array commentdata through unchanged so older filter callers do not crash the engine
	 */
	public function test_non_array_commentdata_is_returned_unchanged(): void {
		$result = $this->engine->filter_pre_comment_approved( 'spam', 'not-an-array' );
		$this->assertSame( 'spam', $result );
	}

	/**
	 * @testdox It should iterate beyond the first 25-rule page so a match on rule 26 still fires
	 */
	public function test_iterates_beyond_first_page(): void {
		// Fill the first page with non-matching wildcard rules, then place a
		// matching rule on what becomes the second page.
		for ( $i = 0; $i < Wpdb_Rule_Repository::PAGE_SIZE; $i++ ) {
			$this->repo->save(
				new Wildcard_Rule(
					null,
					sprintf( 'Filler %d', $i ),
					null,
					'no-such-token',
					Comment_Parts::of( Comment_Part::content() ),
					Response::spam(),
					$this->fresh_stats()
				)
			);
		}
		$matcher = $this->repo->save(
			new Regex_Rule(
				null,
				'Page-two match',
				null,
				'/casino/i',
				Comment_Parts::of( Comment_Part::content() ),
				Response::trash(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_content' => 'CASINO night' ) )
		);

		$this->assertSame( 'trash', $result );
		$reloaded = $this->repo->find( (int) $matcher->id() );
		$this->assertNotNull( $reloaded );
		$this->assertSame( 1, $reloaded->usage_stats()->times_used() );
	}

	/**
	 * @testdox It should treat a malformed regex rule as a no-fire and keep walking the list so a later valid rule still matches
	 */
	public function test_malformed_rule_is_fail_safe_and_does_not_block_remaining_rules(): void {
		// Rebuild spec §4 fail-safe: an unrunnable PCRE must not abort the
		// engine — the rule is treated as "passed" and the next rule is given
		// its turn. Regex_Rule's constructor only forbids an empty pattern, so
		// a syntactically broken delimiter pair persists to the DB cleanly and
		// blows up at preg_match() time, exactly as a real misconfigured rule
		// would in production.
		$broken = $this->repo->save(
			new Regex_Rule(
				null,
				'Broken regex',
				null,
				'/[unterminated',
				Comment_Parts::of( Comment_Part::content() ),
				Response::trash(),
				$this->fresh_stats()
			)
		);
		$valid  = $this->repo->save(
			new Wildcard_Rule(
				null,
				'Block spam domain',
				null,
				'*@spam-domain.tld',
				Comment_Parts::of( Comment_Part::email() ),
				Response::spam(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved(
			1,
			$this->commentdata( array( 'comment_author_email' => 'bob@spam-domain.tld' ) )
		);

		$this->assertSame(
			'spam',
			$result,
			'Malformed rule must fail-safe to "did not fire" so the next valid rule still gets a chance to match (spec §4 / §9.5).'
		);

		$broken_reloaded = $this->repo->find( (int) $broken->id() );
		$valid_reloaded  = $this->repo->find( (int) $valid->id() );
		$this->assertNotNull( $broken_reloaded );
		$this->assertNotNull( $valid_reloaded );
		$this->assertSame( 0, $broken_reloaded->usage_stats()->times_used(), 'Fail-safe path must not credit the broken rule with a hit.' );
		$this->assertSame( 1, $valid_reloaded->usage_stats()->times_used(), 'The later valid rule is the one that fired and its stats must reflect that.' );
	}

	/**
	 * @testdox It should fail-safe to "no rule fired" when the only rule errors and pass the original approval through
	 */
	public function test_malformed_only_rule_returns_original_approval(): void {
		// Same fail-safe contract as above, but here the broken rule is the
		// only rule on the site: the engine must return WordPress's original
		// approval untouched rather than accidentally diverting every comment.
		$this->repo->save(
			new Regex_Rule(
				null,
				'Broken regex',
				null,
				'/[unterminated',
				Comment_Parts::of( Comment_Part::content() ),
				Response::trash(),
				$this->fresh_stats()
			)
		);

		$result = $this->engine->filter_pre_comment_approved( 1, $this->commentdata() );

		$this->assertSame( 1, $result, 'A site whose only rule is malformed must still let legitimate comments through (spec §4 fail-safe).' );
	}
}
