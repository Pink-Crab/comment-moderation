<?php
/**
 * Rule_Validator unit tests.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Unit\Application\Services
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Unit\Application\Services;

use PinkCrab\Comment_Moderation\Application\Services\Rule_Validator;
use PinkCrab\Comment_Moderation\Application\Services\Validation_Result;
use WP_UnitTestCase;

/**
 * Proves Rule_Validator emits the rebuild spec §6 human-readable error
 * messages for every input failure mode the spec calls out, and approves
 * well-formed input for each of the four rule types.
 *
 * @group unit
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class Test_Rule_Validator extends WP_UnitTestCase {

	/**
	 * Fresh validator per test — the service holds no state, so this is
	 * solely for test isolation.
	 */
	private Rule_Validator $validator;

	/**
	 * Build a fresh validator for each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new Rule_Validator();
	}

	// ---------------------------------------------------------------------
	// Type routing
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to reject an input with no rule type
	 */
	public function test_missing_type_fails(): void {
		$result = $this->validator->validate( array() );

		$this->assertFalse( $result->is_valid() );
		$this->assertSame( 'Unknown rule type.', $result->first_error() );
	}

	/**
	 * @testdox It should be possible to reject an input whose rule type is not one of the four supported values
	 */
	public function test_unknown_type_fails(): void {
		$result = $this->validator->validate( array( 'type' => 'cidr' ) );

		$this->assertFalse( $result->is_valid() );
		$this->assertSame( 'Unknown rule type.', $result->first_error() );
	}

	// ---------------------------------------------------------------------
	// Regex
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to accept a well-formed regex rule with a valid PCRE and at least one part ticked
	 */
	public function test_regex_valid_input_passes(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'regex',
				'pattern'       => '/casin[o0]/i',
				'comment_parts' => array( 'name', 'content' ),
			)
		);

		$this->assertInstanceOf( Validation_Result::class, $result );
		$this->assertTrue( $result->is_valid() );
		$this->assertSame( array(), $result->errors() );
	}

	/**
	 * @testdox It should be possible to reject a regex rule whose pattern is empty
	 */
	public function test_regex_empty_pattern_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'regex',
				'pattern'       => '',
				'comment_parts' => array( 'name' ),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Pattern must not be empty.', $result->errors() );
	}

	/**
	 * @testdox It should be possible to reject a regex rule whose pattern does not compile as PCRE
	 */
	public function test_regex_invalid_pattern_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'regex',
				'pattern'       => '/[unterminated/',
				'comment_parts' => array( 'name' ),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertSame(
			'"/[unterminated/" is not a valid regex expression.',
			$result->first_error()
		);
	}

	/**
	 * @testdox It should be possible to reject a regex rule with no comment parts ticked using the spec's exact message
	 */
	public function test_regex_no_parts_fails_with_spec_message(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'regex',
				'pattern'       => '/foo/',
				'comment_parts' => array(),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Regex rules has no fields selected.', $result->errors() );
	}

	/**
	 * @testdox It should be possible to reject a regex rule whose ticked comment parts include an unknown token
	 */
	public function test_regex_unknown_comment_part_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'regex',
				'pattern'       => '/foo/',
				'comment_parts' => array( 'name', 'banana' ),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Unknown comment part: "banana".', $result->errors() );
	}

	// ---------------------------------------------------------------------
	// Wildcard
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to accept a well-formed wildcard rule with a non-empty pattern and at least one part ticked
	 */
	public function test_wildcard_valid_input_passes(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'wildcard',
				'pattern'       => '*@spam-domain.tld',
				'comment_parts' => array( 'email' ),
			)
		);

		$this->assertTrue( $result->is_valid() );
	}

	/**
	 * @testdox It should be possible to reject a wildcard rule whose pattern is empty
	 */
	public function test_wildcard_empty_pattern_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'wildcard',
				'pattern'       => '',
				'comment_parts' => array( 'email' ),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Pattern must not be empty.', $result->errors() );
	}

	/**
	 * @testdox It should be possible to reject a wildcard rule with no comment parts ticked
	 */
	public function test_wildcard_no_parts_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'wildcard',
				'pattern'       => '*foo*',
				'comment_parts' => array(),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Wildcard rules has no fields selected.', $result->errors() );
	}

	/**
	 * @testdox It should be possible to reject a wildcard rule whose ticked comment parts include an unknown token
	 */
	public function test_wildcard_unknown_comment_part_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'          => 'wildcard',
				'pattern'       => '*foo*',
				'comment_parts' => array( 'email', 'banana' ),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Unknown comment part: "banana".', $result->errors() );
	}

	// ---------------------------------------------------------------------
	// IP Range
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to accept an IP range with two valid IPs sharing the first three octets, start <= end
	 */
	public function test_ip_range_valid_input_passes(): void {
		$result = $this->validator->validate(
			array(
				'type'     => 'ip_range',
				'start_ip' => '203.0.113.10',
				'end_ip'   => '203.0.113.40',
			)
		);

		$this->assertTrue( $result->is_valid() );
	}

	/**
	 * @testdox It should be possible to reject an IP range whose start IP is not a valid IPv4 address
	 */
	public function test_ip_range_invalid_start_ip_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'     => 'ip_range',
				'start_ip' => 'not-an-ip',
				'end_ip'   => '203.0.113.40',
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Start IP address is not a valid IPv4 address.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject an IP range whose end IP is not a valid IPv4 address
	 */
	public function test_ip_range_invalid_end_ip_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'     => 'ip_range',
				'start_ip' => '203.0.113.10',
				'end_ip'   => '999.0.0.0',
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'End IP address is not a valid IPv4 address.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject an IP range whose first three octets do not match using the spec's exact message
	 */
	public function test_ip_range_first_three_octets_mismatch_fails_with_spec_message(): void {
		$result = $this->validator->validate(
			array(
				'type'     => 'ip_range',
				'start_ip' => '203.0.113.10',
				'end_ip'   => '203.0.200.10',
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertSame(
			'The network of the IP address (first 3 parts) must match.',
			$result->first_error()
		);
	}

	/**
	 * @testdox It should be possible to reject an IP range whose start is greater than its end
	 */
	public function test_ip_range_reversed_range_fails(): void {
		$result = $this->validator->validate(
			array(
				'type'     => 'ip_range',
				'start_ip' => '203.0.113.200',
				'end_ip'   => '203.0.113.10',
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Start IP address must be less than or equal to End IP address.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject an IP range with no start or end IP set
	 */
	public function test_ip_range_missing_fields_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'ip_range',
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Start IP address is not a valid IPv4 address.',
			$result->errors()
		);
		$this->assertContains(
			'End IP address is not a valid IPv4 address.',
			$result->errors()
		);
	}

	// ---------------------------------------------------------------------
	// Conditional
	// ---------------------------------------------------------------------

	/**
	 * @testdox It should be possible to accept a conditional rule whose root group carries at least one well-formed condition
	 */
	public function test_conditional_valid_flat_tree_passes(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'email',
							'operator' => 'wildcard',
							'value'    => '*@gmail.com',
						),
						array(
							'part'     => 'content',
							'operator' => 'contains',
							'value'    => 'crypto',
						),
					),
				),
			)
		);

		$this->assertTrue( $result->is_valid() );
	}

	/**
	 * @testdox It should be possible to accept a conditional rule whose tree contains nested groups
	 */
	public function test_conditional_valid_nested_tree_passes(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'content',
							'operator' => 'contains',
							'value'    => 'crypto',
						),
						array(
							'combinator' => 'or',
							'children'   => array(
								array(
									'part'     => 'email',
									'operator' => 'wildcard',
									'value'    => '*@gmail.com',
								),
								array(
									'part'     => 'url',
									'operator' => 'ends_with',
									'value'    => '.ru',
								),
							),
						),
					),
				),
			)
		);

		$this->assertTrue( $result->is_valid() );
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose root carries no conditions
	 */
	public function test_conditional_missing_root_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertSame(
			'Conditional rules must contain at least one condition.',
			$result->first_error()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose root has an empty children array
	 */
	public function test_conditional_empty_root_children_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Every condition group must contain at least one condition.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose nested sub-group is empty
	 */
	public function test_conditional_nested_empty_group_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'email',
							'operator' => 'contains',
							'value'    => 'gmail',
						),
						array(
							'combinator' => 'or',
							'children'   => array(),
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Every condition group must contain at least one condition.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose leaf condition has an unknown comment part
	 */
	public function test_conditional_unknown_part_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'banana',
							'operator' => 'contains',
							'value'    => 'foo',
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Condition #1: must have a valid comment part.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose leaf condition has an unknown operator
	 */
	public function test_conditional_unknown_operator_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'email',
							'operator' => 'frobnicates',
							'value'    => 'foo',
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Condition #1: must have a valid operator.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose leaf condition has an empty value
	 */
	public function test_conditional_empty_value_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'email',
							'operator' => 'contains',
							'value'    => '',
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Condition #1: value must not be empty.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose MATCHES leaf carries an uncompilable regex
	 */
	public function test_conditional_matches_value_must_be_valid_regex(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'content',
							'operator' => 'matches',
							'value'    => '/[broken/',
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Condition #1: "/[broken/" is not a valid regex expression.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose DOES NOT MATCH leaf carries an uncompilable regex
	 */
	public function test_conditional_does_not_match_value_must_be_valid_regex(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'content',
							'operator' => 'does_not_match',
							'value'    => '/[broken/',
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Condition #1: "/[broken/" is not a valid regex expression.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to reject a conditional rule whose child node is not an array
	 */
	public function test_conditional_malformed_child_node_fails(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						'not-an-array',
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Condition #1: malformed condition entry.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to accept a conditional rule whose MATCHES leaf carries a compilable regex
	 */
	public function test_conditional_matches_value_with_valid_regex_passes(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'content',
							'operator' => 'matches',
							'value'    => '/casin[o0]/i',
						),
					),
				),
			)
		);

		$this->assertTrue( $result->is_valid() );
	}

	/**
	 * @testdox It should be possible to number conditions across the whole tree so nested leaves get unique error positions
	 */
	public function test_conditional_numbers_conditions_across_whole_tree(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'email',
							'operator' => 'contains',
							'value'    => 'gmail',
						),
						array(
							'combinator' => 'or',
							'children'   => array(
								array(
									'part'     => 'content',
									'operator' => 'matches',
									'value'    => '/[unterminated/',
								),
							),
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains(
			'Condition #2: "/[unterminated/" is not a valid regex expression.',
			$result->errors()
		);
	}

	/**
	 * @testdox It should be possible to collect every condition error in one pass rather than stopping at the first failure
	 */
	public function test_conditional_collects_all_errors_in_one_pass(): void {
		$result = $this->validator->validate(
			array(
				'type' => 'conditional',
				'root' => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => 'banana',
							'operator' => 'contains',
							'value'    => 'foo',
						),
						array(
							'part'     => 'email',
							'operator' => 'frobnicates',
							'value'    => '',
						),
					),
				),
			)
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Condition #1: must have a valid comment part.', $result->errors() );
		$this->assertContains( 'Condition #2: must have a valid operator.', $result->errors() );
		$this->assertContains( 'Condition #2: value must not be empty.', $result->errors() );
	}
}
