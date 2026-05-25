<?php
/**
 * Akismet_Integration — optional, self-contained co-operation with the
 * Akismet plugin (rebuild spec §10).
 *
 * Listens to the `pccm_comment_failed_rule` action the comment engine emits
 * whenever a configured rule diverts a comment. When Akismet is installed
 * and active *and* the `pccm_akismet_enabled` filter has not been switched
 * off, this listener:
 *
 *   - writes an explanatory note into the diverted comment's Akismet
 *     history (always);
 *   - additionally reports the comment to Akismet as **spam** when the
 *     matched rule's outcome is Spam or Trash;
 *   - additionally reports the comment to Akismet as **not spam (ham)**
 *     when the matched rule's outcome is the hidden Approved/leave-as-is
 *     value;
 *   - does nothing extra when the matched rule's outcome is Pending —
 *     only the history note is written (spec §10 "the comment is not
 *     reported as spam").
 *
 * The work is deferred until WordPress fires `comment_post` so the listener
 * has a real comment id to hand to Akismet (the `pccm_comment_failed_rule`
 * action fires from inside `pre_comment_approved`, before the row exists).
 *
 * The plugin makes no other outbound network calls and never handles any
 * Akismet account/key configuration (spec §10).
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Application\Integration\Akismet
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Application\Integration\Akismet;

use PinkCrab\Comment_Moderation\Application\Engine\Comment_Engine;
use PinkCrab\Comment_Moderation\Domain\Engine\Comment_Submission;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Loader\Hook_Loader;
use PinkCrab\Perique\Interfaces\Hookable;

/**
 * Hookable bridge between the engine's `pccm_comment_failed_rule` action
 * and the Akismet plugin.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class Akismet_Integration implements Hookable {

	/**
	 * Filter that lets an integrator disable the entire Akismet co-operation
	 * (rebuild spec §10 "The integration can be disabled entirely by a
	 * developer if it is not wanted"). Returning a falsy value from this
	 * filter makes the listener no-op even when Akismet itself is active.
	 *
	 * @var string
	 */
	public const FILTER_ENABLED = 'pccm_akismet_enabled';

	/**
	 * Construct with the narrow gateway over the Akismet plugin's surface.
	 *
	 * @param Akismet_Gateway $gateway Injected gateway; the production binding
	 *                                 calls the real `\Akismet` class, tests
	 *                                 substitute a spy.
	 */
	public function __construct(
		private Akismet_Gateway $gateway
	) {}

	/**
	 * Subscribe to the engine's failed-rule action. Availability and the
	 * `pccm_akismet_enabled` filter are re-checked on every dispatch rather
	 * than at registration time, so the listener picks up Akismet whether it
	 * loaded before or after this plugin and respects a late-bound filter.
	 *
	 * @param Hook_Loader $loader Perique hook loader.
	 *
	 * @return void
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( Comment_Engine::ACTION_FAILED_RULE, array( $this, 'on_failed_rule' ), 10, 3 );
	}

	/**
	 * Engine-action callback. Builds the history message from the matched
	 * rule and queues a deferred dispatch that runs once WordPress has
	 * actually inserted the comment (and Akismet therefore has a row to
	 * attach the history note to). When Akismet is unavailable or the
	 * integration has been disabled by the `pccm_akismet_enabled` filter,
	 * the listener returns without scheduling anything.
	 *
	 * @param Rule                $rule        Matched rule (with pre-record_hit stats; the engine has already incremented the DB row).
	 * @param Comment_Submission  $submission  Submission the engine evaluated. Currently unused; kept on the signature so the action contract stays stable for other consumers.
	 * @param array<string,mixed> $commentdata Raw `$commentdata` WordPress passed in. Currently unused; kept on the signature so the action contract stays stable for other consumers.
	 *
	 * @return void
	 *
	 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
	 */
	public function on_failed_rule( Rule $rule, Comment_Submission $submission, array $commentdata ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$message  = self::build_message( $rule );
		$response = $rule->response();

		$dispatch = null;
		$dispatch = function ( $comment_id ) use ( &$dispatch, $message, $response ): void {
			// One-shot — peel ourselves off immediately so a second comment in
			// the same request goes through its own pre_comment_approved →
			// pccm_comment_failed_rule → comment_post chain rather than re-using
			// this closure.
			remove_action( 'comment_post', $dispatch );
			$this->dispatch_to_gateway( (int) $comment_id, $message, $response );
		};
		add_action( 'comment_post', $dispatch, 10, 1 );
	}

	/**
	 * Hand the prepared message and outcome to the gateway. Public so the
	 * deferred closure can call it cleanly; the unit tests also drive this
	 * directly to assert the per-outcome dispatch table without going
	 * through the `comment_post` deferral.
	 *
	 * @param integer  $comment_id Inserted comment id.
	 * @param string   $message    Pre-built history note.
	 * @param Response $response   Matched rule's outcome.
	 *
	 * @return void
	 */
	public function dispatch_to_gateway( int $comment_id, string $message, Response $response ): void {
		$this->gateway->update_comment_history( $comment_id, $message );

		if ( $response->is_spam() || $response->is_trash() ) {
			$this->gateway->submit_spam( $comment_id );
			return;
		}

		if ( $response->is_approved() ) {
			$this->gateway->submit_nonspam( $comment_id );
		}
		// Pending: history note only — spec §10 "the comment is not reported as spam".
	}

	/**
	 * True when Akismet is loaded *and* an integrator has not switched the
	 * co-operation off. Both checks happen on every dispatch so the
	 * environment is read at the moment of decision rather than frozen at
	 * registration time.
	 *
	 * @return boolean
	 */
	private function is_active(): bool {
		if ( ! $this->gateway->is_available() ) {
			return false;
		}

		/**
		 * Filters whether the Akismet co-operation runs for this diversion.
		 *
		 * Hook name resolves to `pccm_akismet_enabled`; the constant is
		 * indirected so callers can reference it as a stable PHP symbol.
		 *
		 * @param boolean $enabled True to run the co-operation, false to skip it.
		 */
		return (bool) apply_filters( self::FILTER_ENABLED, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant value carries the pccm_ prefix.
	}

	/**
	 * Build the Akismet history note shown to administrators reviewing the
	 * comment. Mirrors the spec's worked example:
	 *
	 *   "Comment was marked as spam by moderation rule: {name} — {desc}
	 *    (used 14 times)"
	 *
	 * The description segment is omitted when the rule has no description,
	 * and "used N times" reflects the *post-hit* count (the engine has
	 * already incremented the row by the time this listener runs, but the
	 * in-memory $rule still carries the pre-hit value).
	 *
	 * @param Rule $rule Matched rule.
	 *
	 * @return string
	 */
	private static function build_message( Rule $rule ): string {
		$verb        = self::verb_for_response( $rule->response() );
		$name        = self::display_name( $rule );
		$description = self::trimmed_string( $rule->description() );
		$count       = $rule->usage_stats()->times_used() + 1;

		if ( '' !== $description ) {
			return sprintf(
				/* translators: 1: outcome verb, 2: rule name, 3: rule description, 4: usage count. */
				_n(
					'Comment was %1$s by moderation rule: %2$s — %3$s (used %4$d time)',
					'Comment was %1$s by moderation rule: %2$s — %3$s (used %4$d times)',
					$count,
					'pinkcrab-comment-moderation'
				),
				$verb,
				$name,
				$description,
				$count
			);
		}

		return sprintf(
			/* translators: 1: outcome verb, 2: rule name, 3: usage count. */
			_n(
				'Comment was %1$s by moderation rule: %2$s (used %3$d time)',
				'Comment was %1$s by moderation rule: %2$s (used %3$d times)',
				$count,
				'pinkcrab-comment-moderation'
			),
			$verb,
			$name,
			$count
		);
	}

	/**
	 * The rule's display name for the history note. Unnamed rules fall back
	 * to the rule's type label ("Wildcard", "Regex", "Ip Range",
	 * "Conditional") so the note remains informative — the same fall-back
	 * the admin list uses for unnamed rules (spec §5).
	 *
	 * @param Rule $rule Matched rule.
	 *
	 * @return string
	 */
	private static function display_name( Rule $rule ): string {
		$name = self::trimmed_string( $rule->name() );
		if ( '' !== $name ) {
			return $name;
		}
		return ucwords( str_replace( '_', ' ', $rule->type()->value() ) );
	}

	/**
	 * Coerce a nullable string to a trimmed string ('' for null/whitespace).
	 *
	 * @param string|null $value Raw value.
	 *
	 * @return string
	 */
	private static function trimmed_string( ?string $value ): string {
		return null === $value ? '' : trim( $value );
	}

	/**
	 * Human-readable verb describing what the rule did to the comment, used
	 * as the lead-in of the history note. Mirrors the four Response values
	 * the rest of the system understands; an unknown response falls back to
	 * the neutral "moderated" so the message remains legible if a future
	 * outcome is added.
	 *
	 * @param Response $response Matched rule's outcome.
	 *
	 * @return string
	 */
	private static function verb_for_response( Response $response ): string {
		if ( $response->is_spam() ) {
			return __( 'marked as spam', 'pinkcrab-comment-moderation' );
		}
		if ( $response->is_trash() ) {
			return __( 'moved to trash', 'pinkcrab-comment-moderation' );
		}
		if ( $response->is_pending() ) {
			return __( 'held for review', 'pinkcrab-comment-moderation' );
		}
		if ( $response->is_approved() ) {
			return __( 'marked as not spam', 'pinkcrab-comment-moderation' );
		}
		return __( 'moderated', 'pinkcrab-comment-moderation' );
	}
}
