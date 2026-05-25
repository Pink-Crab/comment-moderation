<?php
/**
 * Response value object — the outcome a moderation rule applies to a matching
 * comment.
 *
 * Three values are user-selectable on the management screen — pending, spam,
 * trash — exactly as listed in the rebuild spec (§5). A fourth value,
 * approved, exists internally so the Akismet co-operation (§10) can report a
 * "leave-as-is" outcome as ham; it is intentionally hidden from the UI.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object enumerating the four possible rule outcomes.
 *
 * Use the named constructors (`pending()` etc.) when creating a Response in
 * code, and `from()` when rehydrating from a stored string value. Equality is
 * always via `equals()` — `===` on instances will succeed only when they are
 * literally the same object.
 */
final class Response {

	public const PENDING  = 'pending';
	public const SPAM     = 'spam';
	public const TRASH    = 'trash';
	public const APPROVED = 'approved';

	/**
	 * The full set of stored values — used for `from()` validation.
	 *
	 * @var array<int,string>
	 */
	private const VALUES = array(
		self::PENDING,
		self::SPAM,
		self::TRASH,
		self::APPROVED,
	);

	/**
	 * The subset offered as choices on the admin screen.
	 *
	 * @var array<int,string>
	 */
	private const USER_SELECTABLE_VALUES = array(
		self::PENDING,
		self::SPAM,
		self::TRASH,
	);

	/**
	 * Private to force callers through the named constructors / `from()`.
	 *
	 * @param string $value One of the VALUES constants.
	 */
	private function __construct(
		private string $value
	) {}

	/**
	 * Hold the comment for manual moderation.
	 *
	 * @return self
	 */
	public static function pending(): self {
		return new self( self::PENDING );
	}

	/**
	 * File the comment as spam.
	 *
	 * @return self
	 */
	public static function spam(): self {
		return new self( self::SPAM );
	}

	/**
	 * Move the comment to trash.
	 *
	 * @return self
	 */
	public static function trash(): self {
		return new self( self::TRASH );
	}

	/**
	 * Hidden "leave the comment alone" outcome. Not offered in the admin UI;
	 * exists so the Akismet integration can report this state as ham.
	 *
	 * @return self
	 */
	public static function approved(): self {
		return new self( self::APPROVED );
	}

	/**
	 * Rehydrate a Response from a stored string value (e.g. the `response`
	 * column on the rules table).
	 *
	 * @param string $value One of the VALUES constants.
	 *
	 * @return self
	 *
	 * @throws InvalidArgumentException If the value is not recognised.
	 */
	public static function from( string $value ): self {
		if ( ! in_array( $value, self::VALUES, true ) ) {
			throw new InvalidArgumentException(
				// Pure-PHP domain layer — esc_html() is a WP function and does
				// not belong here. The message is for developers, never echoed.
				sprintf( 'Unknown rule response value: "%s".', $value ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}
		return new self( $value );
	}

	/**
	 * Stored string value, suitable for persistence.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * True when two Responses carry the same stored value.
	 *
	 * @param self $other Other response to compare to.
	 *
	 * @return boolean
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * True when this Response represents the "hold for review" outcome.
	 *
	 * @return boolean
	 */
	public function is_pending(): bool {
		return self::PENDING === $this->value;
	}

	/**
	 * True when this Response represents the "file as spam" outcome.
	 *
	 * @return boolean
	 */
	public function is_spam(): bool {
		return self::SPAM === $this->value;
	}

	/**
	 * True when this Response represents the "move to trash" outcome.
	 *
	 * @return boolean
	 */
	public function is_trash(): bool {
		return self::TRASH === $this->value;
	}

	/**
	 * True when this Response represents the hidden "leave-as-is" outcome.
	 *
	 * @return boolean
	 */
	public function is_approved(): bool {
		return self::APPROVED === $this->value;
	}

	/**
	 * True for the three responses an administrator can pick on the admin
	 * screen; false for the hidden approved/leave-as-is value.
	 *
	 * @return boolean
	 */
	public function is_user_selectable(): bool {
		return in_array( $this->value, self::USER_SELECTABLE_VALUES, true );
	}

	/**
	 * The three Responses an administrator can pick on the admin screen, in
	 * the order they are offered (Pending, Spam, Trash).
	 *
	 * @return array<int,self>
	 */
	public static function user_selectable(): array {
		return array(
			self::pending(),
			self::spam(),
			self::trash(),
		);
	}
}
