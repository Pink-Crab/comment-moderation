<?php

/**
 * Unit tests for the Rule model
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Tests\Unit\Rule;

use PinkCrab\Comment_Moderation\Rule\Rule;

/**
 * Test_Rule tests the Rule model.
 *
 * @group unit
 * @group rule
 */
class Test_Rule extends \WP_UnitTestCase {

	/**
	 * @testdox It should be possible to create a new Rule with a defined id and retrieve it.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
	 * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_id
	 */
	public function test_can_create_rule_with_id(): void {
		$rule = new Rule(
			1,
			'name',
			'contains',
			'value',
			true,
			array(),
			'spam',
		);
		$this->assertEquals( 1, $rule->get_id() );
	}

    /**
     * @testdox It should be possible to create a new Rule without a defined id and retrieve it.
     *
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_id
     */
    public function test_can_create_rule_without_id(): void {
        $rule = new Rule(
            null,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        $this->assertNull( $rule->get_id() );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined name and retrieve it.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_name
     */
    public function test_can_create_rule_with_name(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        $this->assertEquals( 'name', $rule->get_rule_name() );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined rule type and retrieve it.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_type
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::validate_rule_type
     */
    public function test_can_create_rule_with_rule_type(  ): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        $this->assertEquals( 'contains', $rule->get_rule_type() );
    }
    
    /**
     * @testdox Attempting to use an invalid rule type should throw an exception.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_type
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::validate_rule_type
     * @expectedException \InvalidArgumentException
     */
    public function test_invalid_rule_type_throws_exception(): void {
        $this->expectException( \InvalidArgumentException::class );
        
        $rule = new Rule(
            1,
            'name',
            'invalid',
            'value',
            true,
            array(),
            'spam',
        );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined rule value and retrieve it.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_value
     */
    public function test_can_create_rule_with_rule_value(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        $this->assertEquals( 'value', $rule->get_rule_value() );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined rule enabled and retrieve it.[TRUE]
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_enabled
     */
    public function test_can_create_rule_with_rule_enabled(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        $this->assertTrue( $rule->get_rule_enabled() );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined rule enabled and retrieve it.[FALSE]
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_rule_enabled
     */
    public function test_can_create_rule_with_rule_disabled(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            false,
            array(),
            'spam',
        );
        $this->assertFalse( $rule->get_rule_enabled() );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined fields and retrieve it.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_fields
     * @covers \PinkCrab\Comment_Moderation\Util\Rule_Helper::normalize_fields
     */
    public function test_can_create_rule_with_fields(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
             array( 'comment_agent' => true ),
            'spam',
        );

        $fields = $rule->get_fields();

        $this->assertTrue( $fields['comment_agent'] );
        $this->assertFalse( $fields['comment_author'] );
        $this->assertFalse( $fields['comment_author_email'] );
        $this->assertFalse( $fields['comment_author_url'] );
        $this->assertFalse( $fields['comment_content'] );
        $this->assertFalse( $fields['comment_author_IP'] );
    }

    /**
     * @testdox When fields are added only allowed fields are added.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_fields
     */
    public function test_only_allowed_fields_added(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
             array( 'comment_agent' => true, 'invalid' => true ),
            'spam',
        );

        $fields = $rule->get_fields();

        $this->assertArrayNotHasKey( 'invalid', $fields );
        $this->assertTrue( $fields['comment_agent'] );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined outcome and retrieve it.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_outcome
     */
    public function test_can_create_rule_with_outcome(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        $this->assertEquals( 'spam', $rule->get_outcome() );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined created date and retrieve it.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_created
     */
    public function test_can_create_rule_with_created_date(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
            new \DateTimeImmutable('2021-01-01 00:00:00')
        );
        
        $this->assertEquals( '2021-01-01', $rule->get_created()->format('Y-m-d') );
    }

    /**
     * @testdox It should be possible to create a new Rule without a defined created date and it be set using now and retrieved.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_created
     */
    public function test_can_create_rule_without_created_date(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        
        $this->assertEquals( date('Y-m-d'), $rule->get_created()->format('Y-m-d') );
    }

    /**
     * @testdox It should be possible to create a new Rule with a defined updated date and retrieve it.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_updated
     */
    public function test_can_create_rule_with_updated_date(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
            new \DateTimeImmutable('2021-01-01 00:00:00'),
            new \DateTimeImmutable('2021-01-02 00:00:00')
        );
        
        $this->assertEquals( '2021-01-02', $rule->get_updated()->format('Y-m-d') );
    }

    /**
     * @testdox It should be possible to create a new Rule without a defined updated date and it be set using now and retrieved.
     * 
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::__construct
     * @covers \PinkCrab\Comment_Moderation\Rule\Rule::get_updated
     */
    public function test_can_create_rule_without_updated_date(): void {
        $rule = new Rule(
            1,
            'name',
            'contains',
            'value',
            true,
            array(),
            'spam',
        );
        
        $this->assertEquals( date('Y-m-d'), $rule->get_updated()->format('Y-m-d') );
    }

}
