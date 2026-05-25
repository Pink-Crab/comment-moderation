<?php
/**
 * Conditional_Rule — the "Create Your Own" composite rule (rebuild spec §4.5).
 *
 * Built from one or more Conditions. ALL conditions must match for the rule
 * to fire (AND, not OR). The collection's `comment_parts()` is the union of
 * the parts inspected by its conditions — so an admin filter on "rules that
 * inspect email" still finds composite rules whose only email-touching
 * exposure is via a condition.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object representing one stored Conditional rule.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class Conditional_Rule implements Rule {

	/**
	 * Conditions in declared order — all must match for the rule to fire.
	 *
	 * @var array<int,Condition>
	 */
	private array $conditions;

	/**
	 * Construct with the rule's identity, conditions, response, and stats.
	 *
	 * @param integer|null         $id          DB primary key, null for unsaved rules.
	 * @param string|null          $name        Optional short label.
	 * @param string|null          $description Optional longer description.
	 * @param array<int,Condition> $conditions  At least one condition; ALL must match for the rule to fire.
	 * @param Response             $response    Outcome to apply on match.
	 * @param Usage_Stats          $usage_stats Auto-tracked stats.
	 *
	 * @throws InvalidArgumentException If the conditions list is empty.
	 */
	public function __construct(
		private ?int $id,
		private ?string $name,
		private ?string $description,
		array $conditions,
		private Response $response,
		private Usage_Stats $usage_stats
	) {
		if ( array() === $conditions ) {
			throw new InvalidArgumentException( 'Conditional_Rule: at least one condition is required.' );
		}

		// Re-key to a 0-indexed array so callers can rely on the shape.
		$this->conditions = array_values( $conditions );
	}

	/**
	 * DB primary key, or null for an unsaved rule.
	 *
	 * @return integer|null
	 */
	public function id(): ?int {
		return $this->id;
	}

	/**
	 * Optional short label.
	 *
	 * @return string|null
	 */
	public function name(): ?string {
		return $this->name;
	}

	/**
	 * Optional longer description.
	 *
	 * @return string|null
	 */
	public function description(): ?string {
		return $this->description;
	}

	/**
	 * Always the conditional rule type.
	 *
	 * @return Rule_Type
	 */
	public function type(): Rule_Type {
		return Rule_Type::conditional();
	}

	/**
	 * The conditions in declared order; all must match for the rule to fire.
	 *
	 * @return array<int,Condition>
	 */
	public function conditions(): array {
		return $this->conditions;
	}

	/**
	 * Union of the comment parts the conditions inspect, in canonical order.
	 *
	 * @return Comment_Parts
	 */
	public function comment_parts(): Comment_Parts {
		return new Comment_Parts(
			array_map(
				static fn( Condition $condition ): Comment_Part => $condition->part(),
				$this->conditions
			)
		);
	}

	/**
	 * Outcome applied when the rule fires.
	 *
	 * @return Response
	 */
	public function response(): Response {
		return $this->response;
	}

	/**
	 * Auto-tracked usage record.
	 *
	 * @return Usage_Stats
	 */
	public function usage_stats(): Usage_Stats {
		return $this->usage_stats;
	}
}
