<?php
/**
 * Condition — one (comment part + wildcard pattern) pair inside a
 * Conditional_Rule (rebuild spec §4.5).
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object representing a single row in a "Create Your Own"
 * rule's conditions list.
 */
final class Condition {

	/**
	 * Construct with a comment part and a non-empty wildcard pattern.
	 *
	 * @param Comment_Part $part    Which slice of the comment to scan.
	 * @param string       $pattern Non-empty wildcard expression to match.
	 *
	 * @throws InvalidArgumentException If $pattern is empty.
	 */
	public function __construct(
		private Comment_Part $part,
		private string $pattern
	) {
		if ( '' === $pattern ) {
			throw new InvalidArgumentException( 'Condition: pattern must not be empty.' );
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
	 * Wildcard expression this condition matches against.
	 *
	 * @return string
	 */
	public function pattern(): string {
		return $this->pattern;
	}
}
