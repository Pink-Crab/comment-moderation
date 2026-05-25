<?php
/**
 * Plugin_Akismet_Gateway — production binding for Akismet_Gateway that
 * delegates to the real `\Akismet` class supplied by the Akismet plugin
 * (rebuild spec §10).
 *
 * Every method is a thin, defensive call: nothing happens unless the
 * Akismet class is loaded *and* exposes the static method we are about to
 * invoke. This keeps the plugin transparently no-op on sites where Akismet
 * is missing, partially installed, or has changed its public API across
 * versions — exactly the spec's promise that "If Akismet is not present,
 * none of the above happens and the plugin behaves identically minus the
 * history logging".
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Infrastructure\Akismet
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Infrastructure\Akismet;

use PinkCrab\Comment_Moderation\Application\Integration\Akismet\Akismet_Gateway;

/**
 * Calls the real Akismet plugin's static API.
 */
final class Plugin_Akismet_Gateway implements Akismet_Gateway {

	/**
	 * Fully-qualified Akismet class name — kept as a constant so the
	 * defensive `class_exists()` checks below stay in lock-step.
	 *
	 * @var string
	 */
	private const AKISMET_CLASS = '\\Akismet';

	/**
	 * True when the Akismet plugin's main class is loaded on this site.
	 *
	 * @return boolean
	 */
	public function is_available(): bool {
		return class_exists( self::AKISMET_CLASS );
	}

	/**
	 * Forward to `\Akismet::update_comment_history()` when present.
	 *
	 * @param integer $comment_id Inserted comment id the note belongs to.
	 * @param string  $message    Pre-built history note.
	 *
	 * @return void
	 */
	public function update_comment_history( int $comment_id, string $message ): void {
		$callable = array( self::AKISMET_CLASS, 'update_comment_history' );
		if ( $this->is_available() && is_callable( $callable ) ) {
			call_user_func( $callable, $comment_id, $message );
		}
	}

	/**
	 * Forward to `\Akismet::submit_spam()` when present.
	 *
	 * @param integer $comment_id Inserted comment id to report.
	 *
	 * @return void
	 */
	public function submit_spam( int $comment_id ): void {
		$callable = array( self::AKISMET_CLASS, 'submit_spam' );
		if ( $this->is_available() && is_callable( $callable ) ) {
			call_user_func( $callable, $comment_id );
		}
	}

	/**
	 * Forward to `\Akismet::submit_nonspam()` when present.
	 *
	 * @param integer $comment_id Inserted comment id to report.
	 *
	 * @return void
	 */
	public function submit_nonspam( int $comment_id ): void {
		$callable = array( self::AKISMET_CLASS, 'submit_nonspam' );
		if ( $this->is_available() && is_callable( $callable ) ) {
			call_user_func( $callable, $comment_id );
		}
	}
}
