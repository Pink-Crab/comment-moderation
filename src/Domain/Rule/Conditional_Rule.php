<?php
/**
 * Conditional_Rule — the "Create Your Own" composite rule, generalised in
 * Stage 9 to a recursive group tree.
 *
 * The rebuild spec's §4.5 originally described a flat list of `(part,
 * wildcard pattern)` conditions joined by AND. Stage 9 keeps that as the
 * default top-level shape (a single AND group of Conditions) while
 * permitting the admin to nest sub-groups and switch combinators between AND
 * and OR — see Condition_Group and Operator for the full grammar.
 *
 * This class itself stays thin: it carries the rule's identity, response,
 * stats, and a single root Condition_Group that owns the actual tree.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

/**
 * Immutable value object representing one stored Conditional rule.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class Conditional_Rule implements Rule {

	/**
	 * Construct with the rule's identity, the root group of its condition
	 * tree, the response, and the stats record.
	 *
	 * @param integer|null    $id          DB primary key, null for unsaved rules.
	 * @param string|null     $name        Optional short label.
	 * @param string|null     $description Optional longer description.
	 * @param Condition_Group $root        Root of the recursive condition tree.
	 * @param Response        $response    Outcome to apply on match.
	 * @param Usage_Stats     $usage_stats Auto-tracked stats.
	 */
	public function __construct(
		private ?int $id,
		private ?string $name,
		private ?string $description,
		private Condition_Group $root,
		private Response $response,
		private Usage_Stats $usage_stats
	) {}

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
	 * Root of the condition tree. Walk this to inspect or evaluate the
	 * rule's logic.
	 *
	 * @return Condition_Group
	 */
	public function root(): Condition_Group {
		return $this->root;
	}

	/**
	 * Union of the comment parts every leaf in the tree inspects, in
	 * canonical order. Delegates to the root group so a single recursive
	 * walk produces the value the admin filter relies on.
	 *
	 * @return Comment_Parts
	 */
	public function comment_parts(): Comment_Parts {
		return $this->root->comment_parts();
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
