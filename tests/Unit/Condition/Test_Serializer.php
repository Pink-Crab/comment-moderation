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
 * @group rule
 * @group rule_serializer
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
								->set_rule_value( 'something' );
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

		$this->assertStringContainsString( '"rule_value":"something"', $json );
		$this->assertStringContainsString( '"rule_type":"ends_with"', $json );
		$this->assertStringContainsString( '"comment_content":true', $json );
		$this->assertStringStartsWith( '{"type":"group","conditions":[{"type":"condition"', $json );
	}

	/**
	 * @testdox It should be possible to unserialize a json string to a Rule.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Condition\Serializer::decode
	 */
	public function test_can_unserialize_json_to_rule(): void {
		$json = '{"type":"group","conditions":[{"type":"condition","rule_type":"ends_with","rule_value":"something","comment_content":true,"comment_author":false,"comment_author_email":false,"comment_author_url":false,"comment_author_ip":false,"comment_agent":true}],"match_all":false}';

		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$conditions = $serializer->decode( $json );

		$this->assertInstanceOf( Group::class, $conditions );
		$this->assertFalse( $conditions->is_match_all() );
		$this->assertCount( 1, $conditions->get_conditions() );
		$this->assertInstanceOf( Condition::class, $conditions->get_conditions()[0] );
		$this->assertEquals( 'ends_with', $conditions->get_conditions()[0]->get_rule_type() );
		$this->assertEquals( 'something', $conditions->get_conditions()[0]->get_rule_value() );
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
		$json = '{"type":"group","conditions":[{"type":"group","conditions":[{"type":"condition","rule_type":"ends_with","rule_value":"something","comment_content":true,"comment_author":false,"comment_author_email":false,"comment_author_url":false,"comment_author_ip":false,"comment_agent":true}],"match_all":false}],"match_all":true}';

		$serializer = new \PinkCrab\Comment_Moderation\Rule\Condition\Serializer();
		$conditions = $serializer->decode( $json );

		$this->assertInstanceOf( Group::class, $conditions );
		$this->assertTrue( $conditions->is_match_all() );
		$this->assertCount( 1, $conditions->get_conditions() );
		$this->assertInstanceOf( Group::class, $conditions->get_conditions()[0] );
		$this->assertFalse( $conditions->get_conditions()[0]->is_match_all() );
		$this->assertCount( 1, $conditions->get_conditions()[0]->get_conditions() );
		$this->assertInstanceOf( Condition::class, $conditions->get_conditions()[0]->get_conditions()[0] );
		$this->assertEquals( 'ends_with', $conditions->get_conditions()[0]->get_conditions()[0]->get_rule_type() );
		$this->assertEquals( 'something', $conditions->get_conditions()[0]->get_conditions()[0]->get_rule_value() );
		$this->assertTrue( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_content() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author_email() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author_url() );
		$this->assertFalse( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_author_ip() );
		$this->assertTrue( $conditions->get_conditions()[0]->get_conditions()[0]->is_comment_agent() );
	}
}
