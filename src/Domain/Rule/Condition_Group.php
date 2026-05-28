<?php
/**
 * Condition_Group — a recursive node in the Stage 9 conditional rule
 * builder tree.
 *
 * A group has a Combinator (AND/OR) and an ordered list of children, each of
 * which is either:
 *   - another Condition_Group (a sub-tree), or
 *   - a Condition leaf (`part + operator + value`).
 *
 * This is what the rebuild spec's §4.5 "Create Your Own" becomes once it can
 * nest: the old flat AND-list of conditions is now a group whose combinator
 * is AND and whose children are Conditions; nesting and OR-combination are
 * additive on top.
 *
 * Immutable. Validation is minimal and structural — a group must contain at
 * least one child (a group with no children cannot decide anything), and
 * each child must be a Condition or another Condition_Group. The engine
 * enforces fail-safety at evaluation time (rebuild spec §4 — a malformed
 * rule that throws is treated as "passed" rather than blocking every
 * comment); this class is concerned only with shape.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable, recursive container of Conditions and nested groups.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
final class Condition_Group {

	/**
	 * Children of this group, in declared order.
	 *
	 * @var array<int,Condition|Condition_Group>
	 */
	private array $children;

	/**
	 * Construct with the combinator and at least one child node.
	 *
	 * @param Combinator                           $combinator AND or OR; decides how children combine.
	 * @param array<int,Condition|Condition_Group> $children   Ordered children — each a Condition or a sub-group.
	 *
	 * @throws InvalidArgumentException If $children is empty or contains anything other than a Condition / Condition_Group.
	 */
	public function __construct(
		private Combinator $combinator,
		array $children
	) {
		if ( array() === $children ) {
			throw new InvalidArgumentException( 'Condition_Group: at least one child is required.' );
		}

		foreach ( $children as $index => $child ) {
			if ( ! ( $child instanceof Condition || $child instanceof self ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'Condition_Group: child at index %d must be a Condition or Condition_Group.',
						(int) $index // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			}
		}

		$this->children = array_values( $children );
	}

	/**
	 * Convenience factory for an AND-combined group.
	 *
	 * @param Condition|Condition_Group ...$children Ordered children.
	 *
	 * @return self
	 */
	public static function all_of( Condition|Condition_Group ...$children ): self {
		return new self( Combinator::all(), array_values( $children ) );
	}

	/**
	 * Convenience factory for an OR-combined group.
	 *
	 * @param Condition|Condition_Group ...$children Ordered children.
	 *
	 * @return self
	 */
	public static function any_of( Condition|Condition_Group ...$children ): self {
		return new self( Combinator::any(), array_values( $children ) );
	}

	/**
	 * Combinator (AND/OR) deciding how this group's children combine.
	 *
	 * @return Combinator
	 */
	public function combinator(): Combinator {
		return $this->combinator;
	}

	/**
	 * Children of this group in declared order.
	 *
	 * @return array<int,Condition|Condition_Group>
	 */
	public function children(): array {
		return $this->children;
	}

	/**
	 * Number of direct children. Does not recurse into nested groups.
	 *
	 * @return integer
	 */
	public function count(): int {
		return count( $this->children );
	}

	/**
	 * True when this group contains no nested sub-group — i.e. all of its
	 * children are Conditions. Useful to the admin UI when deciding whether
	 * to render the simple, flat condition list or the recursive tree.
	 *
	 * @return boolean
	 */
	public function is_flat(): bool {
		foreach ( $this->children as $child ) {
			if ( $child instanceof self ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Every Condition reachable from this group, flattened in tree order.
	 * Nested groups contribute their own leaves recursively. Used by
	 * `comment_parts()` and by callers that want to operate on every leaf
	 * regardless of grouping.
	 *
	 * @return list<Condition>
	 */
	public function all_conditions(): array {
		$out = array();
		foreach ( $this->children as $child ) {
			if ( $child instanceof Condition ) {
				$out[] = $child;
				continue;
			}
			foreach ( $child->all_conditions() as $leaf ) {
				$out[] = $leaf;
			}
		}
		return $out;
	}

	/**
	 * Union of the comment parts every reachable leaf inspects, in canonical
	 * order. A group whose tree only touches `email` and `content` reports
	 * exactly those two parts so the admin filter "rules that inspect email"
	 * still finds composite rules whose only email exposure is via a nested
	 * condition.
	 *
	 * @return Comment_Parts
	 */
	public function comment_parts(): Comment_Parts {
		return new Comment_Parts(
			array_map(
				static fn( Condition $condition ): Comment_Part => $condition->part(),
				$this->all_conditions()
			)
		);
	}
}
