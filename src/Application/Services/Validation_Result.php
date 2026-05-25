<?php
/**
 * Validation_Result — the outcome of a single Rule_Validator call.
 *
 * Carries either an empty list (rule input is acceptable) or one or more
 * human-readable error messages drawn from the rebuild spec (§6). The admin
 * screen surfaces these verbatim in its red error banner; callers must not
 * persist the rule when `is_valid()` is false.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Application\Services
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Application\Services;

/**
 * Immutable value object describing a Rule_Validator outcome.
 *
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
final class Validation_Result {

	/**
	 * Human-readable failure messages, in the order they were discovered.
	 *
	 * @var list<string>
	 */
	private array $errors;

	/**
	 * Private — callers go through the named constructors.
	 *
	 * @param array<int,string> $errors Ordered failure messages; empty means "passed".
	 */
	private function __construct( array $errors ) {
		$this->errors = array_values( $errors );
	}

	/**
	 * Successful validation — no errors collected.
	 *
	 * @return self
	 */
	public static function passed(): self {
		return new self( array() );
	}

	/**
	 * Failed validation — at least one error must be supplied.
	 *
	 * @param array<int,string> $errors Ordered failure messages.
	 *
	 * @return self
	 */
	public static function failed( array $errors ): self {
		return new self( $errors );
	}

	/**
	 * True when no errors were collected.
	 *
	 * @return boolean
	 */
	public function is_valid(): bool {
		return array() === $this->errors;
	}

	/**
	 * All collected error messages, in discovery order.
	 *
	 * @return list<string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * First collected error message, or null when valid. Convenience for
	 * callers that only surface a single banner line.
	 *
	 * @return string|null
	 */
	public function first_error(): ?string {
		return $this->errors[0] ?? null;
	}
}
