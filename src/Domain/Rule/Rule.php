<?php
/**
 * Rule — the common contract every concrete rule type satisfies.
 *
 * The four implementations (Regex_Rule, Wildcard_Rule, Ip_Range_Rule,
 * Conditional_Rule) carry their type-specific pattern data; this interface
 * exposes only the cross-cutting attributes the engine, repository, and
 * admin screen treat uniformly: identity, label/description, type, response,
 * the inspected comment parts, and the auto-tracked usage stats.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

/**
 * Cross-cutting accessors implemented by every rule type.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
interface Rule {

	/**
	 * Numeric primary key, or null for an unsaved rule.
	 *
	 * @return integer|null
	 */
	public function id(): ?int;

	/**
	 * Optional short human label (rebuild spec §5).
	 *
	 * @return string|null
	 */
	public function name(): ?string;

	/**
	 * Optional longer note explaining the rule's intent (rebuild spec §5).
	 *
	 * @return string|null
	 */
	public function description(): ?string;

	/**
	 * Which of the four supported pattern kinds this rule uses.
	 *
	 * @return Rule_Type
	 */
	public function type(): Rule_Type;

	/**
	 * Outcome to apply when this rule fires.
	 *
	 * @return Response
	 */
	public function response(): Response;

	/**
	 * The slice(s) of a submitted comment this rule inspects. IP-only rule
	 * types return a collection containing only Comment_Part::ip().
	 *
	 * @return Comment_Parts
	 */
	public function comment_parts(): Comment_Parts;

	/**
	 * Auto-tracked "times used / last used / last updated" record.
	 *
	 * @return Usage_Stats
	 */
	public function usage_stats(): Usage_Stats;
}
