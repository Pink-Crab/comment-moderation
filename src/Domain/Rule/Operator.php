<?php
/**
 * Operator value object — names the comparison a Condition performs against
 * the chosen slice of a submitted comment.
 *
 * The twelve operators implement the table in the Stage 9 builder spec:
 * `IS` / `IS NOT`, `CONTAINS` / `DOES NOT CONTAIN` (with `HAS` as a write-time
 * alias for `CONTAINS`), `STARTS WITH` / `ENDS WITH`, `IN` / `NOT IN`,
 * `MATCHES` / `DOES NOT MATCH`, and `WILDCARD` / `NOT WILDCARD`.
 *
 * Pure data: this value object describes which comparison a Condition will run
 * — it does not perform the comparison. The engine layer applies the operator
 * to a subject string in a separate stage.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object enumerating the twelve condition operators.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class Operator {

	public const IS               = 'is';
	public const IS_NOT           = 'is_not';
	public const CONTAINS         = 'contains';
	public const DOES_NOT_CONTAIN = 'does_not_contain';
	public const STARTS_WITH      = 'starts_with';
	public const ENDS_WITH        = 'ends_with';
	public const IN               = 'in';
	public const NOT_IN           = 'not_in';
	public const MATCHES          = 'matches';
	public const DOES_NOT_MATCH   = 'does_not_match';
	public const WILDCARD         = 'wildcard';
	public const NOT_WILDCARD     = 'not_wildcard';

	/**
	 * The twelve canonical stored values, in display order.
	 *
	 * @var array<int,string>
	 */
	private const VALUES = array(
		self::IS,
		self::IS_NOT,
		self::CONTAINS,
		self::DOES_NOT_CONTAIN,
		self::STARTS_WITH,
		self::ENDS_WITH,
		self::IN,
		self::NOT_IN,
		self::MATCHES,
		self::DOES_NOT_MATCH,
		self::WILDCARD,
		self::NOT_WILDCARD,
	);

	/**
	 * Pairs each negating operator with the affirmative form it inverts. Used
	 * by `is_negation()` / `negation()` so callers do not need to hard-code
	 * the relationship.
	 *
	 * @var array<string,string>
	 */
	private const NEGATIONS = array(
		self::IS_NOT           => self::IS,
		self::DOES_NOT_CONTAIN => self::CONTAINS,
		self::NOT_IN           => self::IN,
		self::DOES_NOT_MATCH   => self::MATCHES,
		self::NOT_WILDCARD     => self::WILDCARD,
	);

	/**
	 * Inputs accepted by `from()` that are not the canonical stored token —
	 * the spec calls these out as aliases. The mapping is intentionally
	 * one-way: the canonical value is `contains`, and `has` is the alias.
	 *
	 * @var array<string,string>
	 */
	private const ALIASES = array(
		'has' => self::CONTAINS,
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
	 * Exact equality (case-insensitive at evaluation time).
	 *
	 * @return self
	 */
	public static function is(): self {
		return new self( self::IS );
	}

	/**
	 * Exact inequality.
	 *
	 * @return self
	 */
	public static function is_not(): self {
		return new self( self::IS_NOT );
	}

	/**
	 * Substring match. `HAS` is accepted as a write-time alias of this
	 * operator through `from()`.
	 *
	 * @return self
	 */
	public static function contains(): self {
		return new self( self::CONTAINS );
	}

	/**
	 * Substring miss.
	 *
	 * @return self
	 */
	public static function does_not_contain(): self {
		return new self( self::DOES_NOT_CONTAIN );
	}

	/**
	 * Prefix match.
	 *
	 * @return self
	 */
	public static function starts_with(): self {
		return new self( self::STARTS_WITH );
	}

	/**
	 * Suffix match.
	 *
	 * @return self
	 */
	public static function ends_with(): self {
		return new self( self::ENDS_WITH );
	}

	/**
	 * Subject is one of the comma-separated values supplied as the
	 * Condition's value.
	 *
	 * @return self
	 */
	public static function in(): self {
		return new self( self::IN );
	}

	/**
	 * Negation of `in()`.
	 *
	 * @return self
	 */
	public static function not_in(): self {
		return new self( self::NOT_IN );
	}

	/**
	 * PCRE regex match.
	 *
	 * @return self
	 */
	public static function matches(): self {
		return new self( self::MATCHES );
	}

	/**
	 * Negation of `matches()`.
	 *
	 * @return self
	 */
	public static function does_not_match(): self {
		return new self( self::DOES_NOT_MATCH );
	}

	/**
	 * Shell-style `*` / `?` wildcard match.
	 *
	 * @return self
	 */
	public static function wildcard(): self {
		return new self( self::WILDCARD );
	}

	/**
	 * Negation of `wildcard()`.
	 *
	 * @return self
	 */
	public static function not_wildcard(): self {
		return new self( self::NOT_WILDCARD );
	}

	/**
	 * Rehydrate from a stored string value or a documented alias. Recognised
	 * aliases (e.g. `has` → `contains`) are normalised to their canonical
	 * operator so a single stored representation is used everywhere.
	 *
	 * Matching is case-insensitive and tolerant of spaces in the input —
	 * `"DOES NOT CONTAIN"` and `"does_not_contain"` both resolve to the same
	 * operator. Round-tripping through `value()` always returns the
	 * underscored canonical token.
	 *
	 * @param string $value Stored value or alias.
	 *
	 * @return self
	 *
	 * @throws InvalidArgumentException If the value is not a recognised operator or alias.
	 */
	public static function from( string $value ): self {
		$normalised = strtolower( str_replace( array( ' ', '-' ), '_', trim( $value ) ) );

		if ( isset( self::ALIASES[ $normalised ] ) ) {
			$normalised = self::ALIASES[ $normalised ];
		}

		if ( ! in_array( $normalised, self::VALUES, true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown condition operator: "%s".', $value ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		return new self( $normalised );
	}

	/**
	 * Canonical stored string value, suitable for persistence.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * True when two Operators carry the same stored value.
	 *
	 * @param self $other Other operator to compare to.
	 *
	 * @return boolean
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * True for the negating half of each pair (e.g. `IS NOT`, `DOES NOT
	 * CONTAIN`). Useful for the admin UI when grouping operators.
	 *
	 * @return boolean
	 */
	public function is_negation(): bool {
		return array_key_exists( $this->value, self::NEGATIONS );
	}

	/**
	 * The opposite operator from this one — `IS` ↔ `IS NOT`, `CONTAINS` ↔
	 * `DOES NOT CONTAIN`, and so on. Operators that have no negating pair
	 * (`STARTS WITH`, `ENDS WITH`) return themselves; callers that care can
	 * compare with `equals()` to detect that case.
	 *
	 * @return self
	 */
	public function negation(): self {
		if ( isset( self::NEGATIONS[ $this->value ] ) ) {
			return new self( self::NEGATIONS[ $this->value ] );
		}

		$flipped = array_flip( self::NEGATIONS );
		if ( isset( $flipped[ $this->value ] ) ) {
			return new self( $flipped[ $this->value ] );
		}

		return new self( $this->value );
	}

	/**
	 * All twelve operators in display order, as fresh instances.
	 *
	 * @return array<int,self>
	 */
	public static function all(): array {
		return array_map(
			static fn( string $value ): self => new self( $value ),
			self::VALUES
		);
	}
}
