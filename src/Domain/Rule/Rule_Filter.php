<?php
/**
 * Rule_Filter — value object describing the multi-criteria filter the admin
 * screen passes to the repository when listing rules.
 *
 * Mirrors the §7 filter panel from the rebuild spec: a free-text search term
 * (matched against the rule's pattern/expression and name), a set of rule
 * types, a set of comment parts, and a set of responses. Each group is
 * additive (logical OR within the group) and ANDed against the other groups;
 * an empty group means "do not filter on this dimension".
 *
 * Pure data: this value object describes the criteria — it does not run them.
 * The repository translates it into a prepared SQL query.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

/**
 * Immutable filter passed to Rule_Repository when listing rules.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
final class Rule_Filter {

	/**
	 * Comment parts the matched rules must inspect; empty = any.
	 *
	 * Stored as a private property (not promoted) so the default can be set
	 * in the body — PHP 8.0 disallows `new` in parameter initialisers.
	 *
	 * @var Comment_Parts
	 */
	private Comment_Parts $parts;

	/**
	 * Construct with each criterion. Empty values mean "no filter".
	 *
	 * @param string               $search_term Free-text fragment matched against name + pattern (case-insensitive).
	 * @param array<int,Rule_Type> $types       Allowed rule types; empty = any.
	 * @param Comment_Parts|null   $parts       Rules that touch at least one of these parts; null/empty = any.
	 * @param array<int,Response>  $responses   Allowed responses; empty = any.
	 */
	public function __construct(
		private string $search_term = '',
		private array $types = array(),
		?Comment_Parts $parts = null,
		private array $responses = array()
	) {
		$this->parts = $parts ?? Comment_Parts::none();
	}

	/**
	 * Empty filter — matches every rule. Convenience for unfiltered listings.
	 *
	 * @return self
	 */
	public static function none(): self {
		return new self();
	}

	/**
	 * Free-text fragment (lowercased / trimmed by the repository). Empty
	 * string means no text filter.
	 *
	 * @return string
	 */
	public function search_term(): string {
		return $this->search_term;
	}

	/**
	 * Allowed rule types in declared order; empty = any.
	 *
	 * @return array<int,Rule_Type>
	 */
	public function types(): array {
		return array_values( $this->types );
	}

	/**
	 * Comment parts the matched rules must inspect; empty = any.
	 *
	 * @return Comment_Parts
	 */
	public function parts(): Comment_Parts {
		return $this->parts;
	}

	/**
	 * Allowed responses; empty = any.
	 *
	 * @return array<int,Response>
	 */
	public function responses(): array {
		return array_values( $this->responses );
	}

	/**
	 * True when every criterion is empty — the repository can skip the
	 * WHERE clause entirely.
	 *
	 * @return boolean
	 */
	public function is_empty(): bool {
		return '' === $this->search_term
			&& array() === $this->types
			&& $this->parts->is_empty()
			&& array() === $this->responses;
	}
}
