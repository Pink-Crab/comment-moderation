<?php

/**
 * Unit tests for the Rule Serializer
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Tests\Unit\Rule;

use DateTimeImmutable;
use DateTimeInterface;
use PinkCrab\Comment_Moderation\Rule\Rule;
use PinkCrab\Comment_Moderation\Rule\Condition\Group;
use PinkCrab\Comment_Moderation\Rule\Condition\Condition;

/**
 * Test_Rule tests the Rule Serializer.
 *
 * @group unit
 * @group condition
 * @group condition_serializer
 */
class Test_Rule_Serializer extends \WP_UnitTestCase {

	/**
	 * @testdox It should be possible to serialize a Rule to a json string.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::encode
	 */
	public function test_can_serialize_rule_to_json(): void {
		$rule = new Rule(
			1,
			'name',
			true,
			new Group(
				array(
					new Condition(
						Condition::TYPE_ENDS_WITH,
						function ( Condition $condition ) {
							return $condition
								->set_comment_content( true )
								->set_condition_value( 'something' );
						}
					),
				),
				Group::MATCH_ALL,
			),
			'spam',
		);

		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$json       = $serializer->encode( $rule->get_rule_conditions() );
		$this->assertJson( $json );

		$this->assertStringContainsString( '"condition_value":"something"', $json );
		$this->assertStringContainsString( '"condition_type":"ends_with"', $json );
		$this->assertStringContainsString( '"comment_content":true', $json );
		$this->assertStringStartsWith( '{"type":"group","conditions":[{"type":"condition"', $json );
	}

	/**
	 * @testdox It should be possible to unserialize a json string to a Rule.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
     * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_group
     * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 */
	public function test_can_unserialize_json_to_rule(): void {
		$json = '{"type":"group","conditions":[{"type":"condition","condition_type":"ends_with","condition_value":"something","comment_content":true,"comment_author":false,"comment_author_email":false,"comment_author_url":false,"comment_author_ip":false,"comment_agent":true}],"match_all":false}';

		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$conditions = $serializer->decode( $json );

		$this->assertInstanceOf( Group::class, $conditions );
		$this->assertFalse( $conditions->is_match_all() );
		$this->assertCount( 1, $conditions->get_conditions() );
		$this->assertInstanceOf( Condition::class, $conditions->get_conditions()[0] );
		$this->assertEquals( 'ends_with', $conditions->get_conditions()[0]->get_condition_type() );
		$this->assertEquals( 'something', $conditions->get_conditions()[0]->get_condition_value() );
		$this->assertTrue( $conditions->get_conditions()[0]->is_comment_content() );
		$this->assertFalse( $conditions->get_conditions()[0]->is_comment_author() );
		$this->assertFalse( $conditions->get_conditions()[0]->is_comment_author_email() );
		$this->assertFalse( $conditions->get_conditions()[0]->is_comment_author_url() );
		$this->assertFalse( $conditions->get_conditions()[0]->is_comment_author_ip() );
		$this->assertTrue( $conditions->get_conditions()[0]->is_comment_agent() );
	}

	/**
	 * @testdox It should be possible to unserialize deep nested json string to a Rule.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::encode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 */
	public function test_can_unserialize_deep_nested_json_to_rule(): void {
		$json = '{"type":"group","conditions":[{"type":"group","conditions":[{"type":"condition","condition_type":"ends_with","condition_value":"something","comment_content":true,"comment_author":false,"comment_author_email":false,"comment_author_url":false,"comment_author_ip":false,"comment_agent":true}],"match_all":false}],"match_all":true}';

		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$conditions = $serializer->decode( $json );

		$this->assertInstanceOf( Group::class, $conditions );
		$this->assertTrue( $conditions->is_match_all() );
		$this->assertCount( 1, $conditions->get_conditions() );
		$this->assertInstanceOf( Group::class, $conditions->get_conditions()[0] );
		$this->assertFalse( $conditions->get_conditions()[0]->is_match_all() );
		$this->assertCount( 1, $conditions->get_conditions()[0]->get_conditions() );
		$this->assertInstanceOf( Condition::class, $conditions->get_conditions()[0]->get_conditions()[0] );
		$this->assertEquals( 'ends_with', $conditions->get_conditions()[0]->get_conditions()[0]->get_condition_type() );
		$this->assertEquals( 'something', $conditions->get_conditions()[0]->get_conditions()[0]->get_condition_value() );
		$this->assertTrue( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_content() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author_email() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author_url() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author_ip() );
		$this->assertTrue( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_agent() );
	}

	/**
	 * @testdox Decoding JSON that is empty or not a group should return null.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 *
	 * @return void
	 */
	public function test_decoding_empty_json_or_not_group_should_return_null(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->assertNull( $serializer->decode( '{}' ) );
		$this->assertNull( $serializer->decode( '[]' ) );
	}

	/**
	 * @testdox If the decoded JSON doesnt contain the type key, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 *
	 * @return void
	 */
	public function test_decoding_json_without_type_key_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$serializer->decode( '{"conditions":[]}' );
	}

	/**
	 * @testdox If the decoded JSON type is not group, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 *
	 * @return void
	 */
	public function test_decoding_json_with_type_not_group_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$serializer->decode( '{"type":"condition","conditions":[]}' );
	}

	/**
	* @testdox If a group is decoded without conditions, it should throw an exception.
	*
	* @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	* @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_group
	*
	* @return void
	*/
	public function test_decoding_group_without_conditions_should_return_empty_group(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/missing key: conditions/' );
		$serializer->decode( '{"type":"group", "match_all":true}' );
	}

	/**
	 * @testdox If a condition is decoded without condition_type, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_group
	 *
	 * @return void
	 */
	public function test_decoding_group_without_match_all_key(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/missing key: match_all/' );
		$serializer->decode( '{"type":"group", "conditions":[]}' );
	}

	/**
	 * @testdox If the conditions are not a valid type and exception should be thrown.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_group
	 *
	 * @return void
	 */
	public function test_decoding_invalid_conditions_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$serializer->decode( '{"type":"group", "conditions":[{"type":"wrong"}], "match_all":true}' );
	}

	/**
	 * @testdox If a condition is a group, it should be parsed as a group.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_group
	 *
	 * @return void
	 */
	public function test_decoding_group_condition_should_return_group(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$json       = '{"type":"group","conditions":[{"type":"group","conditions":[], "match_all":true}], "match_all":true}';
		$group      = $serializer->decode( $json );
		$this->assertInstanceOf( Group::class, $group );
		$this->assertCount( 1, $group->get_conditions() );
		$this->assertInstanceOf( Group::class, $group->get_conditions()[0] );
	}

	/**
	 * @testdox When the group is decoded, the match_all value should be cast to a boolean.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_group
	 *
	 * @return void
	 */
	public function test_decoding_group_should_cast_match_all_to_boolean(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$json       = '{"type":"group","conditions":[], "match_all":"true"}';
		$group      = $serializer->decode( $json );
		$this->assertTrue( $group->is_match_all() );

		$json  = '{"type":"group","conditions":[], "match_all":1}';
		$group = $serializer->decode( $json );
		$this->assertTrue( $group->is_match_all() );
	}

	/**
	 * @testdox If a condition is missing the condition_type, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_condition_type_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: condition_type' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['condition_type'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox If a condition is missing the condition_value, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_condition_value_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: condition_value' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['condition_value'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox If a condition is missing the comment_content, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_comment_content_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: comment_content' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['comment_content'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox If a condition is missing the comment_author, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_comment_author_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: comment_author' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['comment_author'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox If a condition is missing the comment_author_email, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_comment_author_email_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: comment_author_email' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['comment_author_email'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox If a condition is missing the comment_author_url, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_comment_author_url_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: comment_author_url' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['comment_author_url'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox If a condition is missing the comment_author_ip, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_comment_author_ip_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: comment_author_ip' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['comment_author_ip'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox If a condition is missing the comment_agent, it should throw an exception.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_without_comment_agent_should_throw_exception(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid condition, missing key: comment_agent' );
		$serializer->decode(
			'{"type":"group", "match_all":true, "conditions":[' . $this->get_condition_json(
				function ( array $mock ) {
					unset( $mock['comment_agent'] );
					return $mock;
				}
			) . ']}'
		);
	}

	/**
	 * @testdox All condition fields should be cast to boolean when decoded.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::parse_condition
	 *
	 * @return void
	 */
	public function test_decoding_condition_fields_should_be_cast_to_boolean(): void {
		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$json       = '{"type":"group","conditions":[' . $this->get_condition_json(
			function ( $values ) {
				// Iterate over all fields and set as 1 if starts with comment_
				foreach ( $values as $key => $value ) {
					if ( strpos( $key, 'comment_' ) === 0 ) {
						$values[ $key ] = 1;
					}
				}

				return $values;
			}
		) . '], "match_all":true}';
		$group      = $serializer->decode( $json );
		$condition  = $group->get_conditions()[0];

		$this->assertTrue( $condition->is_comment_content() );
		$this->assertTrue( $condition->is_comment_author() );
		$this->assertTrue( $condition->is_comment_author_email() );
		$this->assertTrue( $condition->is_comment_author_url() );
		$this->assertTrue( $condition->is_comment_author_ip() );
		$this->assertTrue( $condition->is_comment_agent() );
	}


	#############################
	#  Additional Test Methods  #
	#############################

	/**
	 * Get a Condition as JSON with all values, can modify to test specific values.
	 *
	 * @param \callable(array):array $modify
	 *
	 * @return string
	 */
	protected function get_condition_json( ?callable $modify = null ): string {
		$mock = array(
			'type'                 => 'condition',
			'condition_type'       => 'ends_with',
			'comment_content'      => true,
			'comment_author'       => true,
			'comment_author_email' => true,
			'comment_author_url'   => true,
			'comment_author_ip'    => true,
			'comment_agent'        => true,
			'condition_value'      => 'something',
		);

		return wp_json_encode( is_callable( $modify ) ? $modify( $mock ) : $mock );
	}
}
