<?php
/**
 * Akismet_Gateway — narrow port over the bits of the Akismet plugin this
 * product is allowed to touch (rebuild spec §10).
 *
 * The spec is explicit: the moderation plugin makes *no* outbound network
 * calls of its own, never handles an Akismet API key, and only ever
 * co-operates with the Akismet plugin via three operations — write an
 * explanatory line into a comment's Akismet history, report a diverted
 * comment as spam, and report a leave-as-is rule's match as ham. This
 * interface exposes exactly those three operations plus an availability
 * probe; an implementation that calls into the real `\Akismet` class lives
 * in Infrastructure, and tests substitute a fake.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Application\Integration\Akismet
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Application\Integration\Akismet;

/**
 * Domain port over the Akismet plugin's history/report surface.
 */
interface Akismet_Gateway {

	/**
	 * True when the Akismet plugin is loaded on this site and the operations
	 * below are safe to call. Implementations short-circuit every other
	 * method when this returns false.
	 *
	 * @return boolean
	 */
	public function is_available(): bool;

	/**
	 * Append an explanatory history note to the given comment's Akismet
	 * history (rebuild spec §10 — "a note is added to the comment's Akismet
	 * history … so an administrator reviewing Akismet's logs can see *why*
	 * the comment was filed where it was").
	 *
	 * @param integer $comment_id Inserted comment id the note belongs to.
	 * @param string  $message    Human-readable explanation of which rule fired and why.
	 *
	 * @return void
	 */
	public function update_comment_history( int $comment_id, string $message ): void;

	/**
	 * Report the given comment to Akismet as spam (rebuild spec §10 — "the
	 * comment is additionally reported to Akismet as spam, which helps train
	 * Akismet's global filter"). Called for rules whose outcome is Spam or
	 * Trash.
	 *
	 * @param integer $comment_id Inserted comment id to report.
	 *
	 * @return void
	 */
	public function submit_spam( int $comment_id ): void;

	/**
	 * Report the given comment to Akismet as *not* spam / ham (rebuild spec
	 * §10 — "if a rule's (hidden) outcome was 'leave as-is/approved', the
	 * comment is instead reported to Akismet as not spam (ham)").
	 *
	 * @param integer $comment_id Inserted comment id to report.
	 *
	 * @return void
	 */
	public function submit_nonspam( int $comment_id ): void;
}
