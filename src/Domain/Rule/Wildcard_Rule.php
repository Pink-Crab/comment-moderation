<?php
/**
 * Wildcard_Rule — concrete rule whose pattern uses shell-style wildcards.
 *
 * Matches the admin form for the "Wildcard" type in rebuild spec §4.2:
 * `*` for any run of characters, `?` for one, applied case-insensitively
 * to whichever ticked comment parts the admin chose. The engine handles the
 * matching; this class only carries the pattern + selection as data.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object representing one stored Wildcard rule.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class Wildcard_Rule implements Rule {

	/**
	 * Construct with the rule's identity, pattern, scope, response, and stats.
	 *
	 * @param integer|null  $id            DB primary key, null for unsaved rules.
	 * @param string|null   $name          Optional short label.
	 * @param string|null   $description   Optional longer description.
	 * @param string        $pattern       Non-empty wildcard expression.
	 * @param Comment_Parts $comment_parts At least one Comment_Part to scan.
	 * @param Response      $response      Outcome to apply on match.
	 * @param Usage_Stats   $usage_stats   Auto-tracked stats.
	 *
	 * @throws InvalidArgumentException If $pattern is empty or $comment_parts has no entries.
	 */
	public function __construct(
		private ?int $id,
		private ?string $name,
		private ?string $description,
		private string $pattern,
		private Comment_Parts $comment_parts,
		private Response $response,
		private Usage_Stats $usage_stats
	) {
		if ( '' === $pattern ) {
			throw new InvalidArgumentException( 'Wildcard_Rule: pattern must not be empty.' );
		}
		if ( $comment_parts->is_empty() ) {
			throw new InvalidArgumentException( 'Wildcard_Rule: at least one comment part must be selected.' );
		}
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
	 * Always the wildcard rule type.
	 *
	 * @return Rule_Type
	 */
	public function type(): Rule_Type {
		return Rule_Type::wildcard();
	}

	/**
	 * The wildcard expression the engine will match against.
	 *
	 * @return string
	 */
	public function pattern(): string {
		return $this->pattern;
	}

	/**
	 * Which slice(s) of the comment this rule inspects.
	 *
	 * @return Comment_Parts
	 */
	public function comment_parts(): Comment_Parts {
		return $this->comment_parts;
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
