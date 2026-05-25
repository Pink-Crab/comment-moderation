<?php
/**
 * Usage_Stats value object — the auto-maintained "times used / last used /
 * last updated" record carried by every rule (rebuild spec §5).
 *
 * Immutable: every mutator returns a fresh instance, so a rule that wants to
 * record a hit replaces its stats with `$stats->record_hit($now)`.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable record of how many times a rule has fired, when it last fired,
 * and when its definition was last edited.
 */
final class Usage_Stats {

	/**
	 * Construct with an explicit hit count and timestamps.
	 *
	 * @param integer                $times_used   Non-negative hit count.
	 * @param DateTimeImmutable|null $last_used    Null until the rule fires at least once.
	 * @param DateTimeImmutable      $last_updated When the rule's definition was last saved.
	 *
	 * @throws InvalidArgumentException If $times_used is negative.
	 */
	public function __construct(
		private int $times_used,
		private ?DateTimeImmutable $last_used,
		private DateTimeImmutable $last_updated
	) {
		if ( $times_used < 0 ) {
			throw new InvalidArgumentException(
				sprintf( 'Usage_Stats: times_used must be >= 0, got %d.', $times_used ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}
	}

	/**
	 * Fresh stats for a brand-new rule — zero hits, no last-used, last
	 * updated = now.
	 *
	 * @param DateTimeImmutable $now Wall-clock instant to record as "last updated".
	 *
	 * @return self
	 */
	public static function fresh( DateTimeImmutable $now ): self {
		return new self( 0, null, $now );
	}

	/**
	 * How many times this rule has fired.
	 *
	 * @return integer
	 */
	public function times_used(): int {
		return $this->times_used;
	}

	/**
	 * When the rule last fired, or null if it has never fired.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function last_used(): ?DateTimeImmutable {
		return $this->last_used;
	}

	/**
	 * When the rule's definition was last saved.
	 *
	 * @return DateTimeImmutable
	 */
	public function last_updated(): DateTimeImmutable {
		return $this->last_updated;
	}

	/**
	 * Return new stats with the hit count incremented and last-used set to
	 * $when. Last-updated is preserved — only an edit moves that forward (§6).
	 *
	 * @param DateTimeImmutable $when Wall-clock instant of the hit.
	 *
	 * @return self
	 */
	public function record_hit( DateTimeImmutable $when ): self {
		return new self( $this->times_used + 1, $when, $this->last_updated );
	}

	/**
	 * Return new stats with last-updated refreshed to $when, hit history
	 * preserved. Used when the admin edits a rule (§6 "stats preserved").
	 *
	 * @param DateTimeImmutable $when Wall-clock instant of the edit.
	 *
	 * @return self
	 */
	public function touch( DateTimeImmutable $when ): self {
		return new self( $this->times_used, $this->last_used, $when );
	}
}
