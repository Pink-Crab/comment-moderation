<?php
/**
 * Comment_Engine — the invisible runtime side of the plugin.
 *
 * Hooks into WordPress just before it decides whether to publish a submitted
 * comment, assembles a Comment_Submission from the request data, walks the
 * stored rule list in declared order, and — on the first match — records the
 * hit, fires the integration action, and diverts the comment to the matched
 * rule's outcome. If no rule fires the original WordPress decision is
 * returned untouched (rebuild spec §9).
 *
 * Two extensibility points are exposed by name for integrators (spec §11):
 *
 *   - `pccm_comment_submission` (filter): receives the assembled
 *     Comment_Submission and the raw `$commentdata` array; lets another
 *     plugin enrich the submission (e.g. fold a custom "subject" field into
 *     the body the rule engine sees) before evaluation begins.
 *
 *   - `pccm_comment_failed_rule` (action): fires when a rule has matched a
 *     comment, with the matched Rule, the Comment_Submission, and the raw
 *     `$commentdata`. The bundled Akismet co-operation (a later stage) is
 *     one consumer; any other plugin can add logging/alerting via the same
 *     action.
 *
 * Pingbacks, trackbacks, and any other non-comment interaction are skipped
 * outright — only true reader comments are evaluated (spec §9.2).
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Application\Engine
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Application\Engine;

use DateTimeImmutable;
use DateTimeZone;
use PinkCrab\Comment_Moderation\Domain\Engine\Comment_Submission;
use PinkCrab\Comment_Moderation\Domain\Engine\Rule_Evaluator;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Filter;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Repository;
use PinkCrab\Loader\Hook_Loader;
use PinkCrab\Perique\Interfaces\Hookable;

/**
 * Hookable bridge between WordPress's comment pipeline and the rule engine.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class Comment_Engine implements Hookable {

	/**
	 * Filter name applied to the assembled Comment_Submission before
	 * evaluation begins (rebuild spec §11 "Add extra information to a comment
	 * before it is judged").
	 *
	 * @var string
	 */
	public const FILTER_ENRICH_SUBMISSION = 'pccm_comment_submission';

	/**
	 * Action fired when a comment matches one of the configured rules
	 * (rebuild spec §11 "Be notified whenever a comment fails a rule" —
	 * the basis for the Akismet history note, and available to any other
	 * integration).
	 *
	 * @var string
	 */
	public const ACTION_FAILED_RULE = 'pccm_comment_failed_rule';

	/**
	 * Construct with the repository (for iterating rules in declared order
	 * and recording hits) and the evaluator (for deciding whether a single
	 * rule fires against a single submission).
	 *
	 * @param Rule_Repository $repository Persistence port over the rules table.
	 * @param Rule_Evaluator  $evaluator  Fail-safe rule evaluator (stage 15).
	 */
	public function __construct(
		private Rule_Repository $repository,
		private Rule_Evaluator $evaluator
	) {}

	/**
	 * Register the engine on WordPress's `pre_comment_approved` filter — the
	 * last hook before WordPress acts on the decided approval status. Two
	 * arguments are requested so the engine can read `$commentdata`.
	 *
	 * @param Hook_Loader $loader Perique hook loader.
	 *
	 * @return void
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->filter( 'pre_comment_approved', array( $this, 'filter_pre_comment_approved' ), 10, 2 );
	}

	/**
	 * `pre_comment_approved` callback. Returns the moderation outcome the
	 * matched rule dictates, or `$approved` unchanged when no rule fires
	 * (rebuild spec §9.7 "the plugin is completely transparent for
	 * legitimate comments").
	 *
	 * @param integer|string|\WP_Error $approved    The approval value WordPress decided on (0 / 1 / 'spam' / WP_Error).
	 * @param array<string,mixed>      $commentdata Raw comment data passed by WordPress.
	 *
	 * @return integer|string|\WP_Error
	 */
	public function filter_pre_comment_approved( $approved, $commentdata ) {
		if ( ! is_array( $commentdata ) ) {
			return $approved;
		}

		// Rebuild spec §9.2 — pingbacks, trackbacks and any other non-comment
		// interaction pass straight through untouched.
		if ( ! self::is_reader_comment( $commentdata ) ) {
			return $approved;
		}

		$submission = $this->enrich_submission(
			self::build_submission( $commentdata ),
			$commentdata
		);

		$match = $this->first_match( $submission );
		if ( null === $match ) {
			return $approved;
		}

		$this->repository->record_hit( (int) $match->id(), self::now() );

		/**
		 * Fires when a comment trips one of the configured moderation rules.
		 *
		 * Hook name resolves to `pccm_comment_failed_rule`; the constant is
		 * indirected so callers can reference it as a stable PHP symbol.
		 *
		 * @param Rule                $rule        The matched rule (with its pre-update stats).
		 * @param Comment_Submission  $submission  The evaluated submission.
		 * @param array<string,mixed> $commentdata Raw comment data WordPress passed in.
		 */
		do_action( self::ACTION_FAILED_RULE, $match, $submission, $commentdata ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant value carries the pccm_ prefix.

		return self::apply_outcome( $approved, $match->response() );
	}

	/**
	 * Walk the rule list in declared (id ASC) order and return the first rule
	 * that fires. Rules are pulled one page at a time so a site with hundreds
	 * of rules never loads them all into memory at once; iteration stops the
	 * moment a hit is found (rebuild spec §9.5 "first match wins").
	 *
	 * @param Comment_Submission $submission Submission to evaluate.
	 *
	 * @return Rule|null
	 */
	private function first_match( Comment_Submission $submission ): ?Rule {
		$page = 1;
		while ( true ) {
			$rules = $this->repository->find_page( Rule_Filter::none(), $page );
			if ( array() === $rules ) {
				return null;
			}
			foreach ( $rules as $rule ) {
				if ( $this->evaluator->fires( $rule, $submission ) ) {
					return $rule;
				}
			}
			if ( count( $rules ) < Rule_Repository::PAGE_SIZE ) {
				return null;
			}
			++$page;
		}
	}

	/**
	 * Run the assembled submission through the `pccm_comment_submission`
	 * filter so integrations may enrich the body the engine sees.
	 *
	 * @param Comment_Submission  $submission  Built-from-WordPress submission.
	 * @param array<string,mixed> $commentdata Raw comment data, passed through to listeners.
	 *
	 * @return Comment_Submission
	 */
	private function enrich_submission( Comment_Submission $submission, array $commentdata ): Comment_Submission {
		/**
		 * Filters the Comment_Submission the engine is about to evaluate.
		 *
		 * Hook name resolves to `pccm_comment_submission`; the constant is
		 * indirected so callers can reference it as a stable PHP symbol.
		 *
		 * @param Comment_Submission  $submission  Built-from-WordPress submission.
		 * @param array<string,mixed> $commentdata Raw comment data passed by WordPress.
		 */
		$filtered = apply_filters( self::FILTER_ENRICH_SUBMISSION, $submission, $commentdata ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant value carries the pccm_ prefix.
		return $filtered instanceof Comment_Submission ? $filtered : $submission;
	}

	/**
	 * Decide whether a `$commentdata` payload represents a true reader
	 * comment. Pingbacks (`pingback`) and trackbacks (`trackback`) are
	 * excluded; an empty string and the modern `'comment'` value both
	 * count as reader comments (rebuild spec §9.2).
	 *
	 * @param array<string,mixed> $commentdata Raw comment data.
	 *
	 * @return boolean
	 */
	private static function is_reader_comment( array $commentdata ): bool {
		$type = isset( $commentdata['comment_type'] ) ? (string) $commentdata['comment_type'] : '';
		return '' === $type || 'comment' === $type;
	}

	/**
	 * Build a Comment_Submission from the raw WordPress `$commentdata` shape.
	 * Missing fields fall back to empty strings (Comment_Submission treats
	 * those as "nothing to match against" rather than failing).
	 *
	 * @param array<string,mixed> $commentdata Raw comment data.
	 *
	 * @return Comment_Submission
	 */
	private static function build_submission( array $commentdata ): Comment_Submission {
		return new Comment_Submission(
			self::field( $commentdata, 'comment_author' ),
			self::field( $commentdata, 'comment_author_email' ),
			self::field( $commentdata, 'comment_author_url' ),
			self::field( $commentdata, 'comment_author_IP' ),
			self::field( $commentdata, 'comment_agent' ),
			self::field( $commentdata, 'comment_content' )
		);
	}

	/**
	 * Coerce one `$commentdata` field to a string, defaulting to ''.
	 *
	 * @param array<string,mixed> $commentdata Raw comment data.
	 * @param string              $key         Field name.
	 *
	 * @return string
	 */
	private static function field( array $commentdata, string $key ): string {
		return isset( $commentdata[ $key ] ) && is_scalar( $commentdata[ $key ] )
			? (string) $commentdata[ $key ]
			: '';
	}

	/**
	 * Map a Response to the value the engine returns to WordPress through the
	 * `pre_comment_approved` filter. Approved is intentionally a pass-through
	 * — the spec hides that value from the admin UI and §9.6 says the
	 * comment is "not changed" when a leave-as-is rule fires.
	 *
	 * @param integer|string|\WP_Error $approved Original value WordPress passed in.
	 * @param Response                 $response Matched rule's outcome.
	 *
	 * @return integer|string|\WP_Error
	 */
	private static function apply_outcome( $approved, Response $response ) {
		if ( $response->is_pending() ) {
			return 0;
		}
		if ( $response->is_spam() ) {
			return 'spam';
		}
		if ( $response->is_trash() ) {
			return 'trash';
		}
		// Approved / leave-as-is: pass through whatever WordPress decided.
		return $approved;
	}

	/**
	 * Current UTC instant — extracted so tests can fake the clock if they need
	 * to in future.
	 *
	 * @return DateTimeImmutable
	 */
	private static function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
