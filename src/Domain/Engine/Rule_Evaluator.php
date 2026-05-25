<?php
/**
 * Rule_Evaluator — fail-safe, type-dispatched evaluation of a single rule
 * against a single Comment_Submission.
 *
 * Implements the rebuild spec §4 "the engine is fail-safe: if a rule is
 * somehow malformed and errors while being evaluated, that rule is treated
 * as 'passed' so it can never accidentally block every comment on the site"
 * by wrapping the entire dispatch in a Throwable catch. Anything that throws,
 * warns, or produces an invalid PCRE compilation is silently treated as "the
 * rule did not fire" — the safe default that lets the rest of the rule list
 * run.
 *
 * Per rule type:
 *
 *   - Regex_Rule: the admin-supplied PCRE is run against each ticked comment
 *     part (rebuild spec §4.1). The rule fires the moment any one part matches.
 *
 *   - Wildcard_Rule: the admin's shell-style pattern (`*` / `?`) is converted
 *     to PCRE, anchored, and applied case-insensitively (rebuild spec §4.2 —
 *     "Matching is case-insensitive and works correctly even on extremely
 *     long comment bodies"). PCRE is preferred to `fnmatch()` because
 *     `fnmatch()` is platform-dependent and has historically struggled with
 *     long subjects on some systems.
 *
 *   - Ip_Range_Rule: the submission's IP is compared as a 32-bit integer
 *     against the rule's start/end (rebuild spec §4.3). Anything non-IPv4 on
 *     the submission side fails-safe to "did not fire".
 *
 *   - Conditional_Rule: the root Condition_Group is walked depth-first; each
 *     group applies its Combinator (AND/OR) to its children (recursively for
 *     nested groups, terminally for leaf Conditions). Each Condition leaf
 *     applies one of the twelve Operators (step 9) to the submission's value
 *     for the chosen Comment_Part.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Domain\Engine
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Domain\Engine;

use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition_Group;
use PinkCrab\Comment_Moderation\Domain\Rule\Conditional_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Ip_Range_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use RuntimeException;
use Throwable;

/**
 * Fail-safe rule evaluator. Returns `true` exactly when the rule should
 * fire against the supplied submission; anything else (no match, unknown
 * rule shape, malformed pattern, thrown error) returns `false`.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class Rule_Evaluator {

	/**
	 * Decide whether the supplied rule fires against the supplied submission.
	 * Every code path is wrapped in a Throwable catch so a malformed or
	 * exception-throwing rule never blocks the rest of the list (rebuild
	 * spec §4 fail-safe guarantee).
	 *
	 * @param Rule               $rule       Rule to evaluate.
	 * @param Comment_Submission $submission Submission to inspect.
	 *
	 * @return boolean True if the rule fires.
	 */
	public function fires( Rule $rule, Comment_Submission $submission ): bool {
		try {
			return $this->dispatch( $rule, $submission );
		} catch ( Throwable $e ) {
			// Rebuild spec §4: a malformed rule errors → treat as "passed"
			// (did not fire) so the rest of the list still runs.
			return false;
		}
	}

	/**
	 * Route the rule to the right per-type evaluator. Unknown rule shapes
	 * fail-safe to "did not fire".
	 *
	 * @param Rule               $rule       Rule to evaluate.
	 * @param Comment_Submission $submission Submission to inspect.
	 *
	 * @return boolean
	 */
	private function dispatch( Rule $rule, Comment_Submission $submission ): bool {
		if ( $rule instanceof Regex_Rule ) {
			return $this->evaluate_regex( $rule, $submission );
		}
		if ( $rule instanceof Wildcard_Rule ) {
			return $this->evaluate_wildcard( $rule, $submission );
		}
		if ( $rule instanceof Ip_Range_Rule ) {
			return $this->evaluate_ip_range( $rule, $submission );
		}
		if ( $rule instanceof Conditional_Rule ) {
			return $this->evaluate_group( $rule->root(), $submission );
		}
		return false;
	}

	/**
	 * Run the rule's PCRE against each ticked comment part. First match wins;
	 * an invalid PCRE throws and is caught by `fires()`.
	 *
	 * @param Regex_Rule         $rule       Regex rule.
	 * @param Comment_Submission $submission Submission to inspect.
	 *
	 * @return boolean
	 */
	private function evaluate_regex( Regex_Rule $rule, Comment_Submission $submission ): bool {
		foreach ( $rule->comment_parts() as $part ) {
			if ( self::regex_matches( $rule->pattern(), $submission->part( $part ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compile the wildcard pattern to PCRE and run against each ticked part,
	 * case-insensitively. Anchored — the wildcard pattern matches the whole
	 * string the spec example `*@spam-domain.tld` relies on.
	 *
	 * @param Wildcard_Rule      $rule       Wildcard rule.
	 * @param Comment_Submission $submission Submission to inspect.
	 *
	 * @return boolean
	 */
	private function evaluate_wildcard( Wildcard_Rule $rule, Comment_Submission $submission ): bool {
		foreach ( $rule->comment_parts() as $part ) {
			if ( self::wildcard_matches( $rule->pattern(), $submission->part( $part ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compare the submission's IP, as a 32-bit integer, against the rule's
	 * inclusive start/end. A non-IPv4 submission IP fails-safe to "did not
	 * fire".
	 *
	 * @param Ip_Range_Rule      $rule       IP range rule.
	 * @param Comment_Submission $submission Submission to inspect.
	 *
	 * @return boolean
	 */
	private function evaluate_ip_range( Ip_Range_Rule $rule, Comment_Submission $submission ): bool {
		$ip_long = self::ip_to_long( $submission->ip() );
		if ( null === $ip_long ) {
			return false;
		}

		// Rule constructor already validated start/end; null guard is belt-and-braces.
		$start_long = self::ip_to_long( $rule->start_ip() );
		$end_long   = self::ip_to_long( $rule->end_ip() );
		if ( null === $start_long || null === $end_long ) {
			return false;
		}

		return $ip_long >= $start_long && $ip_long <= $end_long;
	}

	/**
	 * Walk a Condition_Group depth-first, applying its combinator to its
	 * children. Nested groups recurse; leaf Conditions are evaluated via
	 * `evaluate_condition()`. AND short-circuits on the first false; OR
	 * short-circuits on the first true.
	 *
	 * @param Condition_Group    $group      Group to evaluate.
	 * @param Comment_Submission $submission Submission to inspect.
	 *
	 * @return boolean
	 */
	private function evaluate_group( Condition_Group $group, Comment_Submission $submission ): bool {
		$is_and = $group->combinator()->is_and();

		foreach ( $group->children() as $child ) {
			$matched = $child instanceof Condition_Group
				? $this->evaluate_group( $child, $submission )
				: $this->evaluate_condition( $child, $submission );

			if ( $is_and && ! $matched ) {
				return false;
			}
			if ( ! $is_and && $matched ) {
				return true;
			}
		}

		// AND with no falses → true; OR with no trues → false.
		return $is_and;
	}

	/**
	 * Apply a leaf Condition's operator to the submission's value for the
	 * chosen comment part.
	 *
	 * @param Condition          $condition  Leaf condition.
	 * @param Comment_Submission $submission Submission to inspect.
	 *
	 * @return boolean
	 */
	private function evaluate_condition( Condition $condition, Comment_Submission $submission ): bool {
		$subject = $submission->part( $condition->part() );
		return self::evaluate_operator( $condition->operator(), $subject, $condition->value() );
	}

	/**
	 * Apply one of the twelve operators (step 9) to a (subject, value) pair.
	 * MATCHES / DOES NOT MATCH expect `$value` to be a delimited PCRE pattern;
	 * WILDCARD / NOT WILDCARD expect a shell-style pattern; IN / NOT IN expect
	 * a comma-separated list. All string comparisons are case-insensitive per
	 * the spec.
	 *
	 * @param Operator $operator Operator to apply.
	 * @param string   $subject  Subject value drawn from the submission.
	 * @param string   $value    Condition value the operator compares against.
	 *
	 * @return boolean
	 */
	private static function evaluate_operator( Operator $operator, string $subject, string $value ): bool {
		return match ( $operator->value() ) {
			Operator::IS               => 0 === strcasecmp( $subject, $value ),
			Operator::IS_NOT           => 0 !== strcasecmp( $subject, $value ),
			Operator::CONTAINS         => '' !== $value && false !== stripos( $subject, $value ),
			Operator::DOES_NOT_CONTAIN => '' === $value || false === stripos( $subject, $value ),
			Operator::STARTS_WITH      => self::starts_with( $subject, $value ),
			Operator::ENDS_WITH        => self::ends_with( $subject, $value ),
			Operator::IN               => self::in_list( $subject, $value ),
			Operator::NOT_IN           => ! self::in_list( $subject, $value ),
			Operator::MATCHES          => self::regex_matches( $value, $subject ),
			Operator::DOES_NOT_MATCH   => ! self::regex_matches( $value, $subject ),
			Operator::WILDCARD         => self::wildcard_matches( $value, $subject ),
			Operator::NOT_WILDCARD     => ! self::wildcard_matches( $value, $subject ),
			default                    => false,
		};
	}

	/**
	 * Case-insensitive starts-with. Empty `$value` is treated as a non-match
	 * — an empty leading substring is always trivially true and would make
	 * the condition fire for every submission.
	 *
	 * @param string $subject Subject string.
	 * @param string $value   Prefix to look for.
	 *
	 * @return boolean
	 */
	private static function starts_with( string $subject, string $value ): bool {
		if ( '' === $value ) {
			return false;
		}
		$length = strlen( $value );
		if ( strlen( $subject ) < $length ) {
			return false;
		}
		return 0 === strncasecmp( $subject, $value, $length );
	}

	/**
	 * Case-insensitive ends-with. Empty `$value` is treated as a non-match
	 * for the same reason as `starts_with()`.
	 *
	 * @param string $subject Subject string.
	 * @param string $value   Suffix to look for.
	 *
	 * @return boolean
	 */
	private static function ends_with( string $subject, string $value ): bool {
		if ( '' === $value ) {
			return false;
		}
		$length = strlen( $value );
		if ( strlen( $subject ) < $length ) {
			return false;
		}
		return 0 === substr_compare( $subject, $value, -$length, $length, true );
	}

	/**
	 * True when the subject equals (case-insensitively) any entry in a
	 * comma-separated list. Surrounding whitespace per entry is trimmed so
	 * `"a, b, c"` and `"a,b,c"` behave identically.
	 *
	 * @param string $subject    Subject string.
	 * @param string $candidates Comma-separated list of candidate values.
	 *
	 * @return boolean
	 */
	private static function in_list( string $subject, string $candidates ): bool {
		foreach ( explode( ',', $candidates ) as $item ) {
			$trimmed = trim( $item );
			if ( '' === $trimmed ) {
				continue;
			}
			if ( 0 === strcasecmp( $subject, $trimmed ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run `preg_match()` with PHP warnings silenced (an invalid PCRE warns and
	 * returns false). A `false` return throws a RuntimeException so the
	 * top-level fail-safe catch in `fires()` treats the rule as "did not
	 * fire" — without the throw, a malformed regex on a DOES NOT MATCH
	 * operator would otherwise flip to truthy and silently misfire.
	 *
	 * @param string $pattern Delimited PCRE pattern.
	 * @param string $subject Subject string.
	 *
	 * @return boolean True when the pattern matches the subject.
	 *
	 * @throws RuntimeException When the pattern fails to compile.
	 */
	private static function regex_matches( string $pattern, string $subject ): bool {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- only way to silence a malformed-PCRE warning without `@`.
		set_error_handler( static fn(): bool => true );
		try {
			$result = preg_match( $pattern, $subject );
		} finally {
			restore_error_handler();
		}
		if ( false === $result ) {
			throw new RuntimeException( 'Invalid PCRE pattern.' );
		}
		return 1 === $result;
	}

	/**
	 * Compile the wildcard pattern into an anchored, case-insensitive,
	 * dot-matches-newlines PCRE and run it against the subject. PCRE is used
	 * in preference to `fnmatch()` for the rebuild spec §4.2 guarantee
	 * "works correctly even on extremely long comment bodies" — `fnmatch()`
	 * is platform-dependent and has historically struggled with very long
	 * subjects on some systems.
	 *
	 * @param string $pattern Shell-style wildcard pattern (`*`, `?`, literals).
	 * @param string $subject Subject string.
	 *
	 * @return boolean True when the pattern matches the entire subject.
	 *
	 * @throws RuntimeException When the compiled PCRE fails to run.
	 */
	private static function wildcard_matches( string $pattern, string $subject ): bool {
		$escaped = preg_quote( $pattern, '#' );
		$escaped = str_replace( array( '\\*', '\\?' ), array( '.*', '.' ), $escaped );
		$regex   = '#^' . $escaped . '$#isu';
		return self::regex_matches( $regex, $subject );
	}

	/**
	 * Convert an IPv4 dotted-quad string to its 32-bit integer form. Returns
	 * `null` when the string is not a valid IPv4 address; callers treat null
	 * as a fail-safe "did not fire".
	 *
	 * @param string $ip IPv4 dotted-quad string.
	 *
	 * @return integer|null
	 */
	private static function ip_to_long( string $ip ): ?int {
		if ( '' === $ip ) {
			return null;
		}
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return null;
		}
		$long = ip2long( $ip );
		return false === $long ? null : $long;
	}
}
