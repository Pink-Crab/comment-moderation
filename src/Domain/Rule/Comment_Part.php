<?php
/**
 * Comment_Part value object — names the slice of a submitted comment a rule
 * can be told to inspect.
 *
 * The six values mirror the six "comment parts" listed against every
 * type-1/2/5 rule on the admin screen (rebuild spec §4): the commenter's
 * name, email address, website URL, IP address, browser user-agent, and the
 * comment text itself. IP-based rule types (IP Range, CIDR) inspect only the
 * IP and so do not present this choice in the UI.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object enumerating the six inspectable parts of a comment.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
final class Comment_Part {

	public const NAME       = 'name';
	public const EMAIL      = 'email';
	public const URL        = 'url';
	public const IP         = 'ip';
	public const USER_AGENT = 'user_agent';
	public const CONTENT    = 'content';

	/**
	 * Canonical ordered list of the six recognised values.
	 *
	 * @var array<int,string>
	 */
	private const VALUES = array(
		self::NAME,
		self::EMAIL,
		self::URL,
		self::IP,
		self::USER_AGENT,
		self::CONTENT,
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
	 * The commenter's display name.
	 *
	 * @return self
	 */
	public static function name(): self {
		return new self( self::NAME );
	}

	/**
	 * The commenter's email address.
	 *
	 * @return self
	 */
	public static function email(): self {
		return new self( self::EMAIL );
	}

	/**
	 * The commenter's website URL.
	 *
	 * @return self
	 */
	public static function url(): self {
		return new self( self::URL );
	}

	/**
	 * The commenter's IP address.
	 *
	 * @return self
	 */
	public static function ip(): self {
		return new self( self::IP );
	}

	/**
	 * The commenter's browser user-agent string.
	 *
	 * @return self
	 */
	public static function user_agent(): self {
		return new self( self::USER_AGENT );
	}

	/**
	 * The body of the comment.
	 *
	 * @return self
	 */
	public static function content(): self {
		return new self( self::CONTENT );
	}

	/**
	 * Rehydrate from a stored string value.
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
				sprintf( 'Unknown comment part: "%s".', $value ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
	 * True when two Comment_Parts carry the same stored value.
	 *
	 * @param self $other Other comment part to compare to.
	 *
	 * @return boolean
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * The six recognised values, in canonical order, as fresh instances.
	 *
	 * @return array<int,self>
	 */
	public static function all(): array {
		return array(
			self::name(),
			self::email(),
			self::url(),
			self::ip(),
			self::user_agent(),
			self::content(),
		);
	}
}
