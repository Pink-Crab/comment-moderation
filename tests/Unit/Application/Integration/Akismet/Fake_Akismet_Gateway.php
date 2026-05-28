<?php
/**
 * Fake_Akismet_Gateway — spy implementation of Akismet_Gateway used by the
 * Akismet_Integration unit tests.
 *
 * Records every call so the tests can assert the per-outcome dispatch
 * table (Spam/Trash → submit_spam, Approved → submit_nonspam, Pending →
 * history note only, everything → history note) without needing the real
 * Akismet plugin in the test environment.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Application\Integration\Akismet
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Application\Integration\Akismet;

use PinkCrab\Comment_Moderation\Application\Integration\Akismet\Akismet_Gateway;

/**
 * Test-only spy double for Akismet_Gateway.
 */
final class Fake_Akismet_Gateway implements Akismet_Gateway {

	/**
	 * Whether the spy reports Akismet as available.
	 */
	private bool $available;

	/**
	 * Recorded `update_comment_history` calls (in order).
	 *
	 * @var array<int,array{comment_id:int,message:string}>
	 */
	public array $history_notes = array();

	/**
	 * Recorded `submit_spam` calls (in order).
	 *
	 * @var array<int,int>
	 */
	public array $spam_reports = array();

	/**
	 * Recorded `submit_nonspam` calls (in order).
	 *
	 * @var array<int,int>
	 */
	public array $ham_reports = array();

	/**
	 * Construct with the desired availability flag.
	 *
	 * @param boolean $available Value to return from is_available().
	 */
	public function __construct( bool $available = true ) {
		$this->available = $available;
	}

	/**
	 * @inheritDoc
	 */
	public function is_available(): bool {
		return $this->available;
	}

	/**
	 * @inheritDoc
	 */
	public function update_comment_history( int $comment_id, string $message ): void {
		$this->history_notes[] = array(
			'comment_id' => $comment_id,
			'message'    => $message,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function submit_spam( int $comment_id ): void {
		$this->spam_reports[] = $comment_id;
	}

	/**
	 * @inheritDoc
	 */
	public function submit_nonspam( int $comment_id ): void {
		$this->ham_reports[] = $comment_id;
	}
}
