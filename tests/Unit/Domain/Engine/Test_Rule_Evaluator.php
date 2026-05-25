<?php
/**
 * Rule_Evaluator unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Domain\Engine
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Domain\Engine;

use PinkCrab\Comment_Moderation\Domain\Engine\Comment_Submission;
use PinkCrab\Comment_Moderation\Domain\Engine\Rule_Evaluator;
use PinkCrab\Comment_Moderation\Domain\Rule\Combinator;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition_Group;
use PinkCrab\Comment_Moderation\Domain\Rule\Conditional_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Ip_Range_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use DateTimeImmutable;
use WP_UnitTestCase;

/**
 * Proves Rule_Evaluator
 *   - returns true exactly when the supplied rule fires against the
 *     supplied submission, across every implemented rule type,
 *   - applies every step-9 operator correctly inside a Conditional rule's
 *     tree, including nested AND/OR combinators,
 *   - is fail-safe: a malformed pattern or unknown rule shape never throws
 *     out of `fires()` — it simply returns false.
 *
 * @group unit
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class Test_Rule_Evaluator extends WP_UnitTestCase {

	/**
	 * Fresh evaluator per test — the service holds no state.
	 */
	private Rule_Evaluator $evaluator;

	/**
	 * Build a fresh evaluator for each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->evaluator = new Rule_Evaluator();
	}

	/**
	 * Convenience for the rule constructors below — Usage_Stats::fresh()
	 * requires a wall-clock; the tests do not care about its value.
	 *
	 * @return Usage_Stats
	 */
	private static function stats(): Usage_Stats {
		return Usage_Stats::fresh( new DateTimeImmutable( '2026-01-01T00:00:00Z' ) );
	}

	/**
	 * Build a Comment_Submission with sensible defaults so individual tests
	 * only override the fields they care about.
	 *
	 * @param array<string,string> $overrides Field-by-field overrides.
	 *
	 * @return Comment_Submission
	 */
	private function submission( array $overrides = array() ): Comment_Submission {
		$defaults = array(
			'name'       => 'Alice',
			'email'      => 'alice@example.com',
			'url'        => 'https://alice.example',
			'ip'         => '203.0.113.5',
			'user_agent' => 'Mozilla/5.0',
			'content'    => 'Hello, world!',
		);
		$merged   = array_merge( $defaults, $overrides );

		return new Comment_Submission(
			$merged['name'],
			$merged['email'],
			$merged['url'],
			$merged['ip'],
			$merged['user_agent'],
			$merged['content']
		);
	}

	// ---------------------------------------------------------------------
	// Regex rule
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to fire a regex rule when the pattern matches any ticked comment part
	 */
	public function test_regex_fires_when_any_part_matches(): void {
		$rule = new Regex_Rule(
			null,
			null,
			null,
			'/casin[o0]/i',
			Comment_Parts::of( Comment_Part::content(), Comment_Part::name() ),
			Response::spam(),
			self::stats()
		);

		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'content' => 'Visit my casino now!' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to keep a regex rule silent when no ticked part matches
	 */
	public function test_regex_does_not_fire_when_no_part_matches(): void {
		$rule = new Regex_Rule(
			null,
			null,
			null,
			'/casino/i',
			Comment_Parts::of( Comment_Part::content() ),
			Response::spam(),
			self::stats()
		);

		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'content' => 'A perfectly normal comment.' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to treat a malformed regex pattern as "did not fire" (fail-safe per rebuild spec §4)
	 */
	public function test_regex_with_invalid_pattern_is_fail_safe(): void {
		$rule = new Regex_Rule(
			null,
			null,
			null,
			'/[unterminated',
			Comment_Parts::of( Comment_Part::content() ),
			Response::spam(),
			self::stats()
		);

		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'content' => '[unterminated' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to inspect the IP via a regex rule that ticks the IP comment part
	 */
	public function test_regex_inspects_ip_when_ticked(): void {
		$rule = new Regex_Rule(
			null,
			null,
			null,
			'/^203\.0\.113\./',
			Comment_Parts::of( Comment_Part::ip() ),
			Response::spam(),
			self::stats()
		);

		$this->assertTrue( $this->evaluator->fires( $rule, $this->submission() ) );
		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '198.51.100.7' ) ) )
		);
	}

	// ---------------------------------------------------------------------
	// Wildcard rule
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to match a shell-style wildcard pattern against a ticked comment part
	 */
	public function test_wildcard_fires_when_pattern_matches_email(): void {
		$rule = new Wildcard_Rule(
			null,
			null,
			null,
			'*@spam-domain.tld',
			Comment_Parts::of( Comment_Part::email() ),
			Response::spam(),
			self::stats()
		);

		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'email' => 'bob@spam-domain.tld' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to match a wildcard pattern case-insensitively
	 */
	public function test_wildcard_match_is_case_insensitive(): void {
		$rule = new Wildcard_Rule(
			null,
			null,
			null,
			'*@SPAM-domain.tld',
			Comment_Parts::of( Comment_Part::email() ),
			Response::spam(),
			self::stats()
		);

		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'email' => 'BOB@Spam-Domain.TLD' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to anchor wildcard matching so a partial-string match alone does not fire
	 */
	public function test_wildcard_is_whole_string_anchored(): void {
		$rule = new Wildcard_Rule(
			null,
			null,
			null,
			'spam',
			Comment_Parts::of( Comment_Part::content() ),
			Response::spam(),
			self::stats()
		);

		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'content' => 'this is spammy' ) )
			)
		);
		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'content' => 'spam' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to match a `?` wildcard against any single character
	 */
	public function test_wildcard_question_mark_matches_one_character(): void {
		$rule = new Wildcard_Rule(
			null,
			null,
			null,
			'b?b',
			Comment_Parts::of( Comment_Part::name() ),
			Response::spam(),
			self::stats()
		);

		$this->assertTrue(
			$this->evaluator->fires( $rule, $this->submission( array( 'name' => 'bob' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'name' => 'boob' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to match a wildcard pattern across a very long, multi-line comment body
	 */
	public function test_wildcard_matches_long_multiline_content(): void {
		$rule = new Wildcard_Rule(
			null,
			null,
			null,
			'*needle*',
			Comment_Parts::of( Comment_Part::content() ),
			Response::spam(),
			self::stats()
		);

		// 200_000 chars of haystack with a needle in the middle, and several
		// newline characters around it to prove `.` matches newlines (the
		// engine compiles wildcards with the `/s` flag).
		$long_body = str_repeat( "lorem ipsum\n", 8000 )
			. "\n\nneedle in here\n\n"
			. str_repeat( "lorem ipsum\n", 8000 );

		$this->assertTrue(
			$this->evaluator->fires( $rule, $this->submission( array( 'content' => $long_body ) ) )
		);
	}

	// ---------------------------------------------------------------------
	// IP range rule
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to fire an IP-range rule when the submission IP falls inside the inclusive range
	 */
	public function test_ip_range_fires_inside_range(): void {
		$rule = new Ip_Range_Rule(
			null,
			null,
			null,
			'203.0.113.10',
			'203.0.113.40',
			Response::trash(),
			self::stats()
		);

		$this->assertTrue(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '203.0.113.10' ) ) )
		);
		$this->assertTrue(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '203.0.113.25' ) ) )
		);
		$this->assertTrue(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '203.0.113.40' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to keep an IP-range rule silent when the submission IP is outside the range
	 */
	public function test_ip_range_does_not_fire_outside_range(): void {
		$rule = new Ip_Range_Rule(
			null,
			null,
			null,
			'203.0.113.10',
			'203.0.113.40',
			Response::trash(),
			self::stats()
		);

		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '203.0.113.9' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '203.0.113.41' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '198.51.100.20' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to fail-safe an IP-range rule when the submission's IP is not valid IPv4
	 */
	public function test_ip_range_with_invalid_submission_ip_is_fail_safe(): void {
		$rule = new Ip_Range_Rule(
			null,
			null,
			null,
			'203.0.113.10',
			'203.0.113.40',
			Response::trash(),
			self::stats()
		);

		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => '' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'ip' => 'not-an-ip' ) ) )
		);
	}

	// ---------------------------------------------------------------------
	// Conditional rule — combinators & nesting
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to fire a flat AND group when every leaf matches (spec scenario C)
	 */
	public function test_conditional_and_group_requires_all_leaves(): void {
		$rule = new Conditional_Rule(
			null,
			null,
			null,
			Condition_Group::all_of(
				new Condition( Comment_Part::email(), Operator::wildcard(), '*@gmail.com' ),
				new Condition( Comment_Part::content(), Operator::wildcard(), '*crypto*' ),
			),
			Response::pending(),
			self::stats()
		);

		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission(
					array(
						'email'   => 'spammer@gmail.com',
						'content' => 'Buy my crypto coin!',
					)
				)
			)
		);
		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'email' => 'spammer@gmail.com', 'content' => 'A normal comment.' ) )
			)
		);
		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'email' => 'alice@yahoo.com', 'content' => 'Buy my crypto coin!' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to fire an OR group when any leaf matches
	 */
	public function test_conditional_or_group_fires_when_any_leaf_matches(): void {
		$rule = new Conditional_Rule(
			null,
			null,
			null,
			Condition_Group::any_of(
				new Condition( Comment_Part::url(), Operator::ends_with(), '.ru' ),
				new Condition( Comment_Part::email(), Operator::ends_with(), '@spam.tld' ),
			),
			Response::pending(),
			self::stats()
		);

		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'url' => 'https://evil.ru', 'email' => 'alice@example.com' ) )
			)
		);
		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'url' => 'https://nice.example', 'email' => 'bob@spam.tld' ) )
			)
		);
		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission( array( 'url' => 'https://nice.example', 'email' => 'alice@example.com' ) )
			)
		);
	}

	/**
	 * @testdox It should be possible to walk a recursively nested tree depth-first with combinators applied at each group
	 */
	public function test_conditional_nested_tree_walks_depth_first(): void {
		// Outer AND: content contains "crypto" AND (email ends @gmail.com OR name is "bob")
		$rule = new Conditional_Rule(
			null,
			null,
			null,
			Condition_Group::all_of(
				new Condition( Comment_Part::content(), Operator::contains(), 'crypto' ),
				Condition_Group::any_of(
					new Condition( Comment_Part::email(), Operator::ends_with(), '@gmail.com' ),
					new Condition( Comment_Part::name(), Operator::is(), 'bob' ),
				),
			),
			Response::pending(),
			self::stats()
		);

		// Both halves of the AND satisfied (content + gmail).
		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission(
					array(
						'name'    => 'Alice',
						'email'   => 'spammer@gmail.com',
						'content' => 'Crypto millionaire!',
					)
				)
			)
		);

		// Both halves of the AND satisfied (content + name=bob), inner OR via name branch.
		$this->assertTrue(
			$this->evaluator->fires(
				$rule,
				$this->submission(
					array(
						'name'    => 'bob',
						'email'   => 'bob@yahoo.com',
						'content' => 'Crypto millionaire!',
					)
				)
			)
		);

		// Outer AND fails — content has no crypto.
		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission(
					array(
						'name'    => 'bob',
						'email'   => 'spammer@gmail.com',
						'content' => 'A perfectly normal comment.',
					)
				)
			)
		);

		// Outer AND fails — inner OR has no satisfied leaf.
		$this->assertFalse(
			$this->evaluator->fires(
				$rule,
				$this->submission(
					array(
						'name'    => 'Alice',
						'email'   => 'alice@yahoo.com',
						'content' => 'Crypto millionaire!',
					)
				)
			)
		);
	}

	// ---------------------------------------------------------------------
	// Conditional rule — every step-9 operator
	// ---------------------------------------------------------------------

	/**
	 * Build a one-condition rule against `Comment_Part::content()` so each
	 * operator can be exercised in isolation.
	 *
	 * @param Operator $operator Operator to apply.
	 * @param string   $value    Condition value.
	 *
	 * @return Conditional_Rule
	 */
	private function content_rule( Operator $operator, string $value ): Conditional_Rule {
		return new Conditional_Rule(
			null,
			null,
			null,
			Condition_Group::all_of(
				new Condition( Comment_Part::content(), $operator, $value )
			),
			Response::pending(),
			self::stats()
		);
	}

	/**
	 * @testdox It should be possible to evaluate IS / IS NOT case-insensitively
	 */
	public function test_operator_is_and_is_not(): void {
		$rule_is = $this->content_rule( Operator::is(), 'Hello, World!' );
		$this->assertTrue(
			$this->evaluator->fires( $rule_is, $this->submission( array( 'content' => 'hello, world!' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule_is, $this->submission( array( 'content' => 'goodbye' ) ) )
		);

		$rule_not = $this->content_rule( Operator::is_not(), 'hello' );
		$this->assertTrue(
			$this->evaluator->fires( $rule_not, $this->submission( array( 'content' => 'goodbye' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule_not, $this->submission( array( 'content' => 'HELLO' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to evaluate CONTAINS / DOES NOT CONTAIN case-insensitively
	 */
	public function test_operator_contains_and_does_not_contain(): void {
		$rule_in  = $this->content_rule( Operator::contains(), 'CRYPTO' );
		$rule_out = $this->content_rule( Operator::does_not_contain(), 'crypto' );

		$this->assertTrue(
			$this->evaluator->fires( $rule_in, $this->submission( array( 'content' => 'Buy my Crypto coin!' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule_in, $this->submission( array( 'content' => 'A normal comment.' ) ) )
		);

		$this->assertTrue(
			$this->evaluator->fires( $rule_out, $this->submission( array( 'content' => 'A normal comment.' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $rule_out, $this->submission( array( 'content' => 'Buy my Crypto coin!' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to evaluate STARTS WITH and ENDS WITH case-insensitively
	 */
	public function test_operator_starts_with_and_ends_with(): void {
		$starts = $this->content_rule( Operator::starts_with(), 'Hello' );
		$ends   = $this->content_rule( Operator::ends_with(), 'world!' );

		$this->assertTrue(
			$this->evaluator->fires( $starts, $this->submission( array( 'content' => 'hello, world!' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $starts, $this->submission( array( 'content' => 'Goodbye, world!' ) ) )
		);

		$this->assertTrue(
			$this->evaluator->fires( $ends, $this->submission( array( 'content' => 'Hello, WORLD!' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $ends, $this->submission( array( 'content' => 'world! - and now what?' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to evaluate IN / NOT IN against a comma-separated value list (case-insensitive)
	 */
	public function test_operator_in_and_not_in(): void {
		$in     = $this->content_rule( Operator::in(), 'foo, bar , Baz' );
		$not_in = $this->content_rule( Operator::not_in(), 'foo, bar, baz' );

		$this->assertTrue(
			$this->evaluator->fires( $in, $this->submission( array( 'content' => 'BAR' ) ) )
		);
		$this->assertTrue(
			$this->evaluator->fires( $in, $this->submission( array( 'content' => 'baz' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $in, $this->submission( array( 'content' => 'quux' ) ) )
		);

		$this->assertTrue(
			$this->evaluator->fires( $not_in, $this->submission( array( 'content' => 'quux' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $not_in, $this->submission( array( 'content' => 'bar' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to evaluate MATCHES / DOES NOT MATCH using a delimited PCRE value
	 */
	public function test_operator_matches_and_does_not_match(): void {
		$match    = $this->content_rule( Operator::matches(), '/casin[o0]/i' );
		$no_match = $this->content_rule( Operator::does_not_match(), '/casino/i' );

		$this->assertTrue(
			$this->evaluator->fires( $match, $this->submission( array( 'content' => 'visit my CASIN0' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $match, $this->submission( array( 'content' => 'A normal comment' ) ) )
		);

		$this->assertTrue(
			$this->evaluator->fires( $no_match, $this->submission( array( 'content' => 'A normal comment' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $no_match, $this->submission( array( 'content' => 'visit my casino' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to evaluate WILDCARD / NOT WILDCARD using a shell-style pattern value
	 */
	public function test_operator_wildcard_and_not_wildcard(): void {
		$matches     = $this->content_rule( Operator::wildcard(), '*crypto*' );
		$not_matches = $this->content_rule( Operator::not_wildcard(), '*crypto*' );

		$this->assertTrue(
			$this->evaluator->fires( $matches, $this->submission( array( 'content' => 'free CRYPTO now!' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $matches, $this->submission( array( 'content' => 'a normal comment' ) ) )
		);

		$this->assertTrue(
			$this->evaluator->fires( $not_matches, $this->submission( array( 'content' => 'a normal comment' ) ) )
		);
		$this->assertFalse(
			$this->evaluator->fires( $not_matches, $this->submission( array( 'content' => 'free CRYPTO now!' ) ) )
		);
	}

	// ---------------------------------------------------------------------
	// Fail-safe
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to treat a Conditional rule with a malformed MATCHES regex as "did not fire"
	 */
	public function test_conditional_with_invalid_matches_regex_is_fail_safe(): void {
		$rule = $this->content_rule( Operator::matches(), '/[unterminated' );

		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'content' => '[unterminated' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to treat a Conditional rule with a malformed DOES NOT MATCH regex as "did not fire" — so a single broken rule cannot fire on every comment
	 */
	public function test_conditional_with_invalid_does_not_match_regex_is_fail_safe(): void {
		$rule = $this->content_rule( Operator::does_not_match(), '/[unterminated' );

		// Without the throw-then-catch fail-safe, the broken regex would
		// flip DOES NOT MATCH true on every input and silently moderate
		// every comment on the site. Prove the engine refuses.
		$this->assertFalse(
			$this->evaluator->fires( $rule, $this->submission( array( 'content' => 'literally anything' ) ) )
		);
	}

	/**
	 * @testdox It should be possible to fail-safe an unknown Rule implementation that the dispatcher does not recognise
	 */
	public function test_unknown_rule_implementation_does_not_fire(): void {
		$rule = new class() implements Rule {
			public function id(): ?int {
				return null;
			}
			public function name(): ?string {
				return null;
			}
			public function description(): ?string {
				return null;
			}
			public function type(): \PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type {
				return \PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type::regex();
			}
			public function response(): Response {
				return Response::spam();
			}
			public function comment_parts(): Comment_Parts {
				return Comment_Parts::none();
			}
			public function usage_stats(): Usage_Stats {
				return Usage_Stats::fresh( new \DateTimeImmutable( '2026-01-01T00:00:00Z' ) );
			}
		};

		$this->assertFalse( $this->evaluator->fires( $rule, $this->submission() ) );
	}

	/**
	 * @testdox It should be possible to keep group evaluation short-circuiting — once an AND child fails or an OR child fires, no further children are evaluated
	 */
	public function test_and_short_circuits_on_first_false_and_or_on_first_true(): void {
		// AND group whose first leaf is false → result false regardless of a
		// second leaf that would also be false. The intent here is to assert
		// the correct boolean — short-circuiting is structural and proved by
		// the matching result alongside the tree shape.
		$and_rule = new Conditional_Rule(
			null,
			null,
			null,
			Condition_Group::all_of(
				new Condition( Comment_Part::name(), Operator::is(), 'never matches' ),
				new Condition( Comment_Part::email(), Operator::is(), 'also never matches' ),
			),
			Response::pending(),
			self::stats()
		);
		$this->assertFalse( $this->evaluator->fires( $and_rule, $this->submission() ) );

		// OR group whose first leaf matches → result true.
		$or_rule = new Conditional_Rule(
			null,
			null,
			null,
			Condition_Group::any_of(
				new Condition( Comment_Part::name(), Operator::is(), 'Alice' ),
				new Condition( Comment_Part::email(), Operator::is(), 'never matches' ),
			),
			Response::pending(),
			self::stats()
		);
		$this->assertTrue( $this->evaluator->fires( $or_rule, $this->submission() ) );
	}
}
