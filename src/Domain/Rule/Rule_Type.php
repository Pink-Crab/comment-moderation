<?php
/**
 * Rule_Type value object — names the kind of pattern a rule uses.
 *
 * Stage 8 implements four of the five types listed in the rebuild spec (§4):
 * Regular Expression, Wildcard, IP Range, and the composite Create-Your-Own.
 * CIDR is intentionally omitted at this stage per the maintainer's call —
 * see the issue notes on the parent rebuild ticket.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object enumerating the implemented rule types.
 */
final class Rule_Type {

	public const REGEX       = 'regex';
	public const WILDCARD    = 'wildcard';
	public const IP_RANGE    = 'ip_range';
	public const CONDITIONAL = 'conditional';

	/**
	 * Recognised values, in admin-dropdown order.
	 *
	 * @var array<int,string>
	 */
	private const VALUES = array(
		self::REGEX,
		self::WILDCARD,
		self::IP_RANGE,
		self::CONDITIONAL,
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
	 * Construct the Regular Expression rule type.
	 *
	 * @return self
	 */
	public static function regex(): self {
		return new self( self::REGEX );
	}

	/**
	 * Construct the Wildcard rule type.
	 *
	 * @return self
	 */
	public static function wildcard(): self {
		return new self( self::WILDCARD );
	}

	/**
	 * Construct the IP Range rule type.
	 *
	 * @return self
	 */
	public static function ip_range(): self {
		return new self( self::IP_RANGE );
	}

	/**
	 * Construct the composite "Create Your Own" rule type.
	 *
	 * @return self
	 */
	public static function conditional(): self {
		return new self( self::CONDITIONAL );
	}

	/**
	 * Rehydrate from a stored string value (e.g. the rules table's `type`
	 * column).
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
				sprintf( 'Unknown rule type: "%s".', $value ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
	 * True when two Rule_Types carry the same stored value.
	 *
	 * @param self $other Other rule type to compare to.
	 *
	 * @return boolean
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * True when this rule type only ever inspects the commenter's IP and so
	 * does not present the six comment-part tick-boxes on the admin screen.
	 *
	 * @return boolean
	 */
	public function is_ip_only(): bool {
		return self::IP_RANGE === $this->value;
	}

	/**
	 * All recognised types, in the order the admin dropdown offers them.
	 *
	 * @return array<int,self>
	 */
	public static function all(): array {
		return array(
			self::regex(),
			self::wildcard(),
			self::ip_range(),
			self::conditional(),
		);
	}
}
