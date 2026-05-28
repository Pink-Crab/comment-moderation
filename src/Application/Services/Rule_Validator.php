<?php
/**
 * Rule_Validator — save-time validation for raw admin/REST rule input,
 * producing the rebuild spec's human-readable error messages.
 *
 * Runs BEFORE the domain value objects (Regex_Rule, Wildcard_Rule,
 * Ip_Range_Rule, Conditional_Rule) are constructed. Those constructors throw
 * InvalidArgumentException with developer-facing strings; this service
 * inspects the same constraints first and emits the polite messages the
 * rebuild spec §6 calls out for the admin error banner, so the visitor never
 * sees a stack trace.
 *
 * The validator never persists anything and never short-circuits at the first
 * error — every check that can be evaluated against the raw input is run, so
 * the admin sees the full list (e.g. "End IP invalid" and "first 3 octets
 * must match") in one pass instead of round-tripping per error.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Application\Services
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Application\Services;

use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;

/**
 * Save-time validation of rule input, producing user-facing error messages.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final class Rule_Validator {

	/**
	 * Validate a raw rule input array. `$input['type']` decides which
	 * type-specific check set runs; unknown / missing types fail with a
	 * single "unknown rule type" error.
	 *
	 * @param array<string,mixed> $input Form-submission shape; keys depend on type.
	 *
	 * @return Validation_Result
	 */
	public function validate( array $input ): Validation_Result {
		$type = isset( $input['type'] ) && is_string( $input['type'] ) ? $input['type'] : '';

		switch ( $type ) {
			case Rule_Type::REGEX:
				return $this->validate_regex( $input );
			case Rule_Type::WILDCARD:
				return $this->validate_wildcard( $input );
			case Rule_Type::IP_RANGE:
				return $this->validate_ip_range( $input );
			case Rule_Type::CONDITIONAL:
				return $this->validate_conditional( $input );
			default:
				return Validation_Result::failed(
					array( __( 'Unknown rule type.', 'pinkcrab-comment-moderation' ) )
				);
		}
	}

	/**
	 * Regex rule: pattern non-empty and PCRE-compilable; at least one
	 * comment part ticked.
	 *
	 * @param array<string,mixed> $input Raw input array.
	 *
	 * @return Validation_Result
	 */
	private function validate_regex( array $input ): Validation_Result {
		$errors  = array();
		$pattern = $this->string_field( $input, 'pattern' );

		if ( '' === $pattern ) {
			$errors[] = __( 'Pattern must not be empty.', 'pinkcrab-comment-moderation' );
		} elseif ( ! self::is_valid_regex( $pattern ) ) {
			$errors[] = sprintf(
				/* translators: %s: the regex expression the admin entered. */
				__( '"%s" is not a valid regex expression.', 'pinkcrab-comment-moderation' ),
				$pattern
			);
		}

		if ( ! $this->has_any_comment_part( $input ) ) {
			$errors[] = __( 'Regex rules has no fields selected.', 'pinkcrab-comment-moderation' );
		} else {
			foreach ( $this->invalid_comment_parts( $input ) as $invalid ) {
				$errors[] = sprintf(
					/* translators: %s: the comment part token the admin submitted. */
					__( 'Unknown comment part: "%s".', 'pinkcrab-comment-moderation' ),
					$invalid
				);
			}
		}

		return array() === $errors ? Validation_Result::passed() : Validation_Result::failed( $errors );
	}

	/**
	 * Wildcard rule: pattern non-empty; at least one comment part ticked.
	 * Wildcards are intentionally permissive ('*' / '?' plus literal text) —
	 * there is no syntax to malform beyond emptiness.
	 *
	 * @param array<string,mixed> $input Raw input array.
	 *
	 * @return Validation_Result
	 */
	private function validate_wildcard( array $input ): Validation_Result {
		$errors  = array();
		$pattern = $this->string_field( $input, 'pattern' );

		if ( '' === $pattern ) {
			$errors[] = __( 'Pattern must not be empty.', 'pinkcrab-comment-moderation' );
		}

		if ( ! $this->has_any_comment_part( $input ) ) {
			$errors[] = __( 'Wildcard rules has no fields selected.', 'pinkcrab-comment-moderation' );
		} else {
			foreach ( $this->invalid_comment_parts( $input ) as $invalid ) {
				$errors[] = sprintf(
					/* translators: %s: the comment part token the admin submitted. */
					__( 'Unknown comment part: "%s".', 'pinkcrab-comment-moderation' ),
					$invalid
				);
			}
		}

		return array() === $errors ? Validation_Result::passed() : Validation_Result::failed( $errors );
	}

	/**
	 * IP Range rule: both addresses valid IPv4; first three octets identical;
	 * start <= end.
	 *
	 * @param array<string,mixed> $input Raw input array.
	 *
	 * @return Validation_Result
	 */
	private function validate_ip_range( array $input ): Validation_Result {
		$errors   = array();
		$start_ip = $this->string_field( $input, 'start_ip' );
		$end_ip   = $this->string_field( $input, 'end_ip' );

		$start_ok = '' !== $start_ip && false !== filter_var( $start_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$end_ok   = '' !== $end_ip && false !== filter_var( $end_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );

		if ( ! $start_ok ) {
			$errors[] = __( 'Start IP address is not a valid IPv4 address.', 'pinkcrab-comment-moderation' );
		}
		if ( ! $end_ok ) {
			$errors[] = __( 'End IP address is not a valid IPv4 address.', 'pinkcrab-comment-moderation' );
		}

		if ( $start_ok && $end_ok ) {
			$start_parts = explode( '.', $start_ip );
			$end_parts   = explode( '.', $end_ip );
			if ( array_slice( $start_parts, 0, 3 ) !== array_slice( $end_parts, 0, 3 ) ) {
				$errors[] = __(
					'The network of the IP address (first 3 parts) must match.',
					'pinkcrab-comment-moderation'
				);
			} elseif ( ip2long( $start_ip ) > ip2long( $end_ip ) ) {
				$errors[] = __(
					'Start IP address must be less than or equal to End IP address.',
					'pinkcrab-comment-moderation'
				);
			}
		}

		return array() === $errors ? Validation_Result::passed() : Validation_Result::failed( $errors );
	}

	/**
	 * Conditional rule: input must carry a recursive root group whose leaves
	 * each have a valid part, valid operator, and non-empty value; MATCHES /
	 * DOES NOT MATCH leaves must additionally carry a compilable regex; the
	 * tree as a whole must contain at least one leaf.
	 *
	 * @param array<string,mixed> $input Raw input array.
	 *
	 * @return Validation_Result
	 */
	private function validate_conditional( array $input ): Validation_Result {
		$errors = array();

		if ( ! isset( $input['root'] ) || ! is_array( $input['root'] ) ) {
			$errors[] = __(
				'Conditional rules must contain at least one condition.',
				'pinkcrab-comment-moderation'
			);
			return Validation_Result::failed( $errors );
		}

		$counter = 0;
		$this->walk_group( $input['root'], $errors, $counter );

		if ( 0 === $counter && array() === $errors ) {
			$errors[] = __(
				'Conditional rules must contain at least one condition.',
				'pinkcrab-comment-moderation'
			);
		}

		return array() === $errors ? Validation_Result::passed() : Validation_Result::failed( $errors );
	}

	/**
	 * Recursively walk a group node; append errors and increment the shared
	 * leaf counter. Groups with no children fail; nodes that are neither a
	 * condition nor a group fail; valid conditions are routed to
	 * `validate_condition_node()`.
	 *
	 * @param mixed             $node    The group node — expected to be an associative array carrying `children`.
	 * @param array<int,string> $errors  Accumulating error list, modified in place.
	 * @param integer           $counter Sequential leaf number, modified in place.
	 *
	 * @return void
	 */
	private function walk_group( mixed $node, array &$errors, int &$counter ): void {
		if ( ! is_array( $node ) || ! isset( $node['children'] ) || ! is_array( $node['children'] ) || array() === $node['children'] ) {
			$errors[] = __(
				'Every condition group must contain at least one condition.',
				'pinkcrab-comment-moderation'
			);
			return;
		}

		foreach ( $node['children'] as $child ) {
			if ( is_array( $child ) && isset( $child['children'] ) ) {
				$this->walk_group( $child, $errors, $counter );
				continue;
			}
			++$counter;
			$this->validate_condition_node( $child, $counter, $errors );
		}
	}

	/**
	 * Validate one leaf condition node: must be an array carrying a known
	 * comment part, a known operator, and a non-empty value; MATCHES /
	 * DOES NOT MATCH values must compile as a PCRE expression.
	 *
	 * @param mixed             $node     The condition node — expected to be `{part, operator, value}`.
	 * @param integer           $position Human 1-indexed condition number for the error message.
	 * @param array<int,string> $errors   Accumulating error list, modified in place.
	 *
	 * @return void
	 */
	private function validate_condition_node( mixed $node, int $position, array &$errors ): void {
		if ( ! is_array( $node ) ) {
			$errors[] = sprintf(
				/* translators: %d: 1-indexed condition number. */
				__( 'Condition #%d: malformed condition entry.', 'pinkcrab-comment-moderation' ),
				$position
			);
			return;
		}

		$part_raw     = $this->string_field( $node, 'part' );
		$operator_raw = $this->string_field( $node, 'operator' );
		$value        = $this->string_field( $node, 'value' );

		if ( '' === $part_raw || ! in_array( $part_raw, $this->known_comment_parts(), true ) ) {
			$errors[] = sprintf(
				/* translators: %d: 1-indexed condition number. */
				__( 'Condition #%d: must have a valid comment part.', 'pinkcrab-comment-moderation' ),
				$position
			);
		}

		$is_known_operator = '' !== $operator_raw && $this->is_known_operator( $operator_raw );
		if ( ! $is_known_operator ) {
			$errors[] = sprintf(
				/* translators: %d: 1-indexed condition number. */
				__( 'Condition #%d: must have a valid operator.', 'pinkcrab-comment-moderation' ),
				$position
			);
		}

		if ( '' === $value ) {
			$errors[] = sprintf(
				/* translators: %d: 1-indexed condition number. */
				__( 'Condition #%d: value must not be empty.', 'pinkcrab-comment-moderation' ),
				$position
			);
			return;
		}

		if ( $is_known_operator && $this->operator_requires_regex( $operator_raw ) && ! self::is_valid_regex( $value ) ) {
			$errors[] = sprintf(
				/* translators: 1: 1-indexed condition number; 2: regex value the admin entered. */
				__( 'Condition #%1$d: "%2$s" is not a valid regex expression.', 'pinkcrab-comment-moderation' ),
				$position,
				$value
			);
		}
	}

	/**
	 * Read a scalar string field from a raw input array, returning '' for
	 * any missing or non-scalar value.
	 *
	 * @param array<string,mixed> $input Raw input array.
	 * @param string              $key   Field to read.
	 *
	 * @return string
	 */
	private function string_field( array $input, string $key ): string {
		if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
			return '';
		}
		return trim( (string) $input[ $key ] );
	}

	/**
	 * True when the input carries at least one recognised comment part.
	 *
	 * @param array<string,mixed> $input Raw input array.
	 *
	 * @return boolean
	 */
	private function has_any_comment_part( array $input ): bool {
		if ( ! isset( $input['comment_parts'] ) || ! is_array( $input['comment_parts'] ) ) {
			return false;
		}
		$known = $this->known_comment_parts();
		foreach ( $input['comment_parts'] as $part ) {
			if ( is_string( $part ) && in_array( $part, $known, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Submitted comment-part tokens that are not in the canonical set.
	 *
	 * @param array<string,mixed> $input Raw input array.
	 *
	 * @return list<string>
	 */
	private function invalid_comment_parts( array $input ): array {
		if ( ! isset( $input['comment_parts'] ) || ! is_array( $input['comment_parts'] ) ) {
			return array();
		}
		$known = $this->known_comment_parts();
		$bad   = array();
		foreach ( $input['comment_parts'] as $part ) {
			if ( ! is_string( $part ) || '' === $part ) {
				continue;
			}
			if ( ! in_array( $part, $known, true ) ) {
				$bad[] = $part;
			}
		}
		return $bad;
	}

	/**
	 * Canonical Comment_Part string values, derived from the domain enum so
	 * the validator stays in lockstep with the source of truth.
	 *
	 * @return list<string>
	 */
	private function known_comment_parts(): array {
		return array_values(
			array_map(
				static fn( Comment_Part $part ): string => $part->value(),
				Comment_Part::all()
			)
		);
	}

	/**
	 * True when the supplied raw operator token resolves via `Operator::from()`
	 * (which tolerates aliases / casing).
	 *
	 * @param string $raw The operator token to test.
	 *
	 * @return boolean
	 */
	private function is_known_operator( string $raw ): bool {
		try {
			Operator::from( $raw );
		} catch ( \InvalidArgumentException $e ) {
			return false;
		}
		return true;
	}

	/**
	 * True when the supplied raw operator is the regex pair (MATCHES /
	 * DOES NOT MATCH). Safe to call only after `is_known_operator()` returned
	 * true.
	 *
	 * @param string $raw The operator token; assumed parseable.
	 *
	 * @return boolean
	 */
	private function operator_requires_regex( string $raw ): bool {
		$operator = Operator::from( $raw );
		return $operator->equals( Operator::matches() )
			|| $operator->equals( Operator::does_not_match() );
	}

	/**
	 * True when the supplied PCRE pattern compiles. Uses a probe call against
	 * an empty subject; PHP returns false when the pattern itself is invalid,
	 * regardless of whether it would match.
	 *
	 * @param string $pattern Caller-supplied PCRE expression.
	 *
	 * @return boolean
	 */
	private static function is_valid_regex( string $pattern ): bool {
		// Silence the PHP warning a malformed PCRE triggers — we only care
		// about the boolean false return that already signals "did not
		// compile". phpcs flags set_error_handler as a development function;
		// here it is the only way to suppress the warning without using @.
		set_error_handler( static fn(): bool => true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		try {
			$result = preg_match( $pattern, '' );
		} finally {
			restore_error_handler();
		}
		return false !== $result;
	}
}
