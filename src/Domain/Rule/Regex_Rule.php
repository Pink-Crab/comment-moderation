<?php
/**
 * Regex_Rule — concrete rule whose pattern is a PCRE expression.
 *
 * Matches the admin form for the "Regular Expression" type in rebuild spec
 * §4.1: a delimited PCRE the admin authors, plus the tick-boxes deciding
 * which comment parts to scan. The engine compiles and runs the pattern;
 * this class only carries the pattern + selection as data.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Rule
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Rule;

use InvalidArgumentException;

/**
 * Immutable value object representing one stored Regex rule.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class Regex_Rule implements Rule {

	/**
	 * Construct with the rule's identity, pattern, scope, response, and stats.
	 *
	 * @param integer|null  $id            DB primary key, null for unsaved rules.
	 * @param string|null   $name          Optional short label.
	 * @param string|null   $description   Optional longer description.
	 * @param string        $pattern       Non-empty PCRE expression (with delimiters).
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
			throw new InvalidArgumentException( 'Regex_Rule: pattern must not be empty.' );
		}
		if ( $comment_parts->is_empty() ) {
			throw new InvalidArgumentException( 'Regex_Rule: at least one comment part must be selected.' );
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
	 * Always the regex rule type.
	 *
	 * @return Rule_Type
	 */
	public function type(): Rule_Type {
		return Rule_Type::regex();
	}

	/**
	 * The PCRE pattern (with delimiters) the engine will run.
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
