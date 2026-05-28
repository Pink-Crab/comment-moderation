<?php
/**
 * Comment_Parts — immutable ordered set of Comment_Part values.
 *
 * A regex / wildcard / composite rule inspects one or more comment parts;
 * this collection carries that selection without duplicates and in canonical
 * order, so two rules built with the same ticked boxes always produce the
 * same value object.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Ordered, duplicate-free collection of Comment_Part instances.
 *
 * @implements IteratorAggregate<int,Comment_Part>
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
final class Comment_Parts implements Countable, IteratorAggregate {

	/**
	 * Parts in this collection, normalised to canonical order with no duplicates.
	 *
	 * @var list<Comment_Part>
	 */
	private array $parts;

	/**
	 * Construct from zero or more parts; duplicates and ordering are
	 * normalised so equal selections always produce equal collections.
	 *
	 * @param array<Comment_Part> $parts Zero or more parts; duplicates and ordering are normalised.
	 */
	public function __construct( array $parts = array() ) {
		$this->parts = self::normalise( $parts );
	}

	/**
	 * Variadic convenience factory.
	 *
	 * @param Comment_Part ...$parts Zero or more parts.
	 *
	 * @return self
	 */
	public static function of( Comment_Part ...$parts ): self {
		return new self( $parts );
	}

	/**
	 * All six recognised parts ticked.
	 *
	 * @return self
	 */
	public static function all(): self {
		return new self( Comment_Part::all() );
	}

	/**
	 * Empty collection — used by IP-only rule types and as a default.
	 *
	 * @return self
	 */
	public static function none(): self {
		return new self();
	}

	/**
	 * Build from an array of stored string values (e.g. the persisted
	 * `comment_parts` column split on a separator).
	 *
	 * @param array<int,string> $values Stored Comment_Part values.
	 *
	 * @return self
	 */
	public static function from_values( array $values ): self {
		return new self(
			array_map(
				static fn( string $value ): Comment_Part => Comment_Part::from( $value ),
				$values
			)
		);
	}

	/**
	 * True when this collection includes the given part.
	 *
	 * @param Comment_Part $part Part to look for.
	 *
	 * @return boolean
	 */
	public function has( Comment_Part $part ): bool {
		foreach ( $this->parts as $existing ) {
			if ( $existing->equals( $part ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * All parts in canonical order.
	 *
	 * @return list<Comment_Part>
	 */
	public function all_parts(): array {
		return $this->parts;
	}

	/**
	 * Stored string values, in canonical order — handy for persistence.
	 *
	 * @return list<string>
	 */
	public function values(): array {
		return array_values(
			array_map(
				static fn( Comment_Part $part ): string => $part->value(),
				$this->parts
			)
		);
	}

	/**
	 * True when the collection contains no parts.
	 *
	 * @return boolean
	 */
	public function is_empty(): bool {
		return array() === $this->parts;
	}

	/**
	 * Return a new collection with the given part added (no-op if already
	 * present). The original collection is unchanged.
	 *
	 * @param Comment_Part $part Part to add.
	 *
	 * @return self
	 */
	public function with( Comment_Part $part ): self {
		if ( $this->has( $part ) ) {
			return $this;
		}
		return new self( array_merge( $this->parts, array( $part ) ) );
	}

	/**
	 * Return a new collection with the given part removed (no-op if absent).
	 *
	 * @param Comment_Part $part Part to remove.
	 *
	 * @return self
	 */
	public function without( Comment_Part $part ): self {
		if ( ! $this->has( $part ) ) {
			return $this;
		}
		return new self(
			array_filter(
				$this->parts,
				static fn( Comment_Part $existing ): bool => ! $existing->equals( $part )
			)
		);
	}

	/**
	 * Number of parts in the collection.
	 *
	 * @return integer
	 *
	 * @phpstan-return int<0, max>
	 */
	public function count(): int {
		return count( $this->parts );
	}

	/**
	 * Iterate parts in canonical order.
	 *
	 * @return Traversable<int,Comment_Part>
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->parts );
	}

	/**
	 * Strip duplicates and reorder the supplied parts into the canonical
	 * order defined by Comment_Part::all().
	 *
	 * @param array<Comment_Part> $parts Caller-supplied parts.
	 *
	 * @return list<Comment_Part>
	 */
	private static function normalise( array $parts ): array {
		$seen   = array();
		$ranked = array();
		foreach ( $parts as $part ) {
			$value = $part->value();
			if ( isset( $seen[ $value ] ) ) {
				continue;
			}
			$seen[ $value ] = true;
			$ranked[]       = $part;
		}

		// Reorder to canonical sequence so equal selections produce equal collections.
		$order = array_flip(
			array_map(
				static fn( Comment_Part $part ): string => $part->value(),
				Comment_Part::all()
			)
		);
		usort(
			$ranked,
			static fn( Comment_Part $left, Comment_Part $right ): int => $order[ $left->value() ] <=> $order[ $right->value() ]
		);

		return $ranked;
	}
}
