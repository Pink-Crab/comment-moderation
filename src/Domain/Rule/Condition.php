<?php
/**
 * Condition — one `(comment part, operator, value)` leaf inside a
 * Condition_Group (Stage 9 builder spec).
 *
 * The earlier composite rule (rebuild spec §4.5) was a flat AND-list of
 * `(part, wildcard pattern)` pairs. The Stage 9 builder generalises this: a
 * Condition is now any of the twelve operators acting on the chosen comment
 * part. Groups (AND/OR, possibly nested) compose Conditions into the actual
 * rule tree — see `Condition_Group`.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object representing a single leaf condition in the builder
 * tree. The `value` semantics depend on the chosen operator — for example,
 * `IN` / `NOT IN` treat it as a comma-separated list; `MATCHES` treats it as
 * a regex. The Condition stores the raw string and leaves interpretation to
 * the engine.
 */
final class Condition {

	/**
	 * Construct with a comment part, an operator, and the value the operator
	 * compares against. The value must be non-empty — the spec rejects empty
	 * expressions on save.
	 *
	 * @param Comment_Part $part     Which slice of the comment to scan.
	 * @param Operator     $operator Comparison to perform.
	 * @param string       $value    Non-empty raw value; semantics vary by operator.
	 *
	 * @throws InvalidArgumentException If $value is empty.
	 */
	public function __construct(
		private Comment_Part $part,
		private Operator $operator,
		private string $value
	) {
		if ( '' === $value ) {
			throw new InvalidArgumentException( 'Condition: value must not be empty.' );
		}
	}

	/**
	 * Which slice of the comment this condition scans.
	 *
	 * @return Comment_Part
	 */
	public function part(): Comment_Part {
		return $this->part;
	}

	/**
	 * Comparison this condition performs against the chosen comment part.
	 *
	 * @return Operator
	 */
	public function operator(): Operator {
		return $this->operator;
	}

	/**
	 * Raw value the operator compares against; semantics vary by operator
	 * (substring for CONTAINS, comma-separated list for IN, regex for
	 * MATCHES, shell pattern for WILDCARD, etc.).
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}
}
