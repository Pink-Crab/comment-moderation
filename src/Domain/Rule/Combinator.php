<?php
/**
 * Combinator value object — names how a Condition_Group combines its
 * children.
 *
 * `AND` means every child must match for the group to fire; `OR` means any
 * one child suffices. The recursive builder spec (Stage 9) treats this as
 * the only knob a group carries besides its ordered child list.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object enumerating the two boolean combinators.
 */
final class Combinator {

	public const AND_VALUE = 'and';
	public const OR_VALUE  = 'or';

	/**
	 * Recognised stored values.
	 *
	 * @var array<int,string>
	 */
	private const VALUES = array(
		self::AND_VALUE,
		self::OR_VALUE,
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
	 * Boolean AND — every child must match.
	 *
	 * @return self
	 */
	public static function all(): self {
		return new self( self::AND_VALUE );
	}

	/**
	 * Boolean OR — any one child may match.
	 *
	 * @return self
	 */
	public static function any(): self {
		return new self( self::OR_VALUE );
	}

	/**
	 * Rehydrate from a stored string value. Accepts the canonical tokens and
	 * tolerates `&&` / `||` shorthands so an import format can use whichever
	 * is most readable.
	 *
	 * @param string $value Stored value.
	 *
	 * @return self
	 *
	 * @throws InvalidArgumentException If the value is not recognised.
	 */
	public static function from( string $value ): self {
		$normalised = strtolower( trim( $value ) );

		if ( '&&' === $normalised ) {
			$normalised = self::AND_VALUE;
		} elseif ( '||' === $normalised ) {
			$normalised = self::OR_VALUE;
		}

		if ( ! in_array( $normalised, self::VALUES, true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown combinator: "%s".', $value ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}
		return new self( $normalised );
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
	 * True when two Combinators carry the same stored value.
	 *
	 * @param self $other Other combinator to compare to.
	 *
	 * @return boolean
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * True when this combinator is `AND` (every child must match).
	 *
	 * @return boolean
	 */
	public function is_and(): bool {
		return self::AND_VALUE === $this->value;
	}

	/**
	 * True when this combinator is `OR` (any one child may match).
	 *
	 * @return boolean
	 */
	public function is_or(): bool {
		return self::OR_VALUE === $this->value;
	}
}
