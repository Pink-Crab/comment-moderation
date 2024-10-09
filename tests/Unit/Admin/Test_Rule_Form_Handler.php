<?php

/**
 * Test the Rule Form Handler.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Tests\Unit\Admin;

use WP_UnitTestCase;

use PinkCrab\Comment_Moderation\Rule\Rule;

use Psr\Http\Message\ServerRequestInterface;
use PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler;

/**
 * Test the Rule Form Handler.
 *
 * @group unit
 * @group admin
 */
class Test_Rule_Form_Handler extends WP_UnitTestCase {

	/**
	 * Get a mocked request.
	 *
	 * @param array<string, mixed> $body The body of the request.
	 * @return ServerRequestInterface
	 */
	protected function get_mock_request( array $body = array() ): ServerRequestInterface {
		$request = $this->createMock( ServerRequestInterface::class );
		$request->method( 'getParsedBody' )->willReturn( $body );
		return $request;
	}

	/**
	 * @testdox It should be possible to get the nonce field.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::nonce_field
	 */
	public function test_can_get_nonce_field(): void {
		$handler = new Rule_Form_Handler( $this->get_mock_request() );
		$this->assertStringContainsString( 'name="rule_nonce"', $handler->nonce_field() );
	}

	/**
	 * @testdox It should be possible to add a field value if its valid.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_field_value
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_field_value
	 */
	public function test_can_add_field_value(): void {
		$handler = new Rule_Form_Handler( $this->get_mock_request() );

		$handler->add_field_value( 'rule_name', 'test' );
		$this->assertEquals( 'test', $handler->get_field_value( 'rule_name' ) );
	}

	/**
	 * @testdox Attempting to add a field value that is not allowed should throw an InvalidArgumentException.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_field_value
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function test_adding_invalid_field_value_should_throw_exception(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '#invalid_field#' );
		$handler = new Rule_Form_Handler( $this->get_mock_request() );
		$handler->add_field_value( 'invalid_field', 'test' );
	}

	/**
	 * @testdox The handler should be able to check in the request body if the form has been submitted.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::is_submitted
	 *
	 * @global array $_POST['rule_nonce]
	 */
	public function test_can_check_if_form_has_been_submitted(): void {
		$handler = new Rule_Form_Handler( $this->get_mock_request( array( 'rule_nonce' => 'doesnt matter' ) ) );
		$this->assertTrue( $handler->is_submitted() );

		// With no nonce, should return false.
		$handler2 = new Rule_Form_Handler( $this->get_mock_request() );
		$this->assertFalse( $handler2->is_submitted() );
	}

	/**
	 * @testdox It should be possible to add a rule to the form values.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_rule
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_field_value
	 */
	public function test_can_add_rule_to_form_values(): void {
        // Skip test.
        $this->markTestSkipped('Test not yet implemented');


		$handler = new Rule_Form_Handler( $this->get_mock_request() );
		$rule    = new Rule(
			12,
			'test_rule',
			'contains',
			'test_value',
			true,
			array(
				'comment_author'       => true,
				'comment_author_email' => false,
				'comment_author_url'   => true,
				'comment_content'      => false,
				'comment_author_IP'    => true,
				'comment_agent'        => false,
			),
			'spam'
		);

		$handler->add_rule( $rule );
		$this->assertEquals( 12, $handler->get_field_value( 'rule_id' ) );
		$this->assertEquals( 'test_rule', $handler->get_field_value( 'rule_name' ) );
		$this->assertEquals( 'contains', $handler->get_field_value( 'rule_type' ) );
		$this->assertEquals(
			array(
				'comment_author'       => true,
				'comment_author_email' => false,
				'comment_author_url'   => true,
				'comment_content'      => false,
				'comment_author_IP'    => true,
				'comment_agent'        => false,
			),
			$handler->get_field_value( 'rule_field' )
		);

		$this->assertEquals( 'spam', $handler->get_field_value( 'rule_outcome' ) );
		$this->assertTrue( $handler->get_field_value( 'rule_enabled' ) );
	}

	/**
	 * @testdox Attempting to submit the form with an invalid nonce, will see an error added to the form and not procesed.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::verify_form_submission
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_form_error
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_form_errors
	 */
	public function test_invalid_nonce_should_add_error_and_not_verify_form_submission(): void {
		$handler = new Rule_Form_Handler( $this->get_mock_request() );
		$handler->verify_form_submission();
		$this->assertContains( 'Invalid form submission. Failed nonce check', $handler->get_form_errors() );
	}

	/**
	 * @testdox Attempting to submit a form with a valid nonce, should see the verify_form_submission method called.
	 *
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::verify_form_submission
	 * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_form_errors
	 */
	public function test_valid_nonce_should_verify_form_submission(): void {
		$handler = new Rule_Form_Handler( $this->get_mock_request( array( 'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ) ) ) );
		$handler->verify_form_submission();
		$form_errors = $handler->get_form_errors();

        $this->assertNotContains( 'Invalid form submission. Failed nonce check', $form_errors );
	}

    /**
     * @testdox When attempting to submit a form, any required fields that are empty should see a form error added to the form.
     * 
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::verify_form_submission
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::validate_request_fields
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_field_error
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_field_errors
     * 
     * @dataProvider formFieldValidationProvider
     */
    public function test_empty_required_fields_should_add_form_error( ServerRequestInterface $request, array $expected_fails ): void {
        $handler = new Rule_Form_Handler($request );
	    $handler->verify_form_submission();

        $rule_field = $handler->get_field_errors( 'rule_field' );
        $rule_name = $handler->get_field_errors( 'rule_name' );
        $rule_type = $handler->get_field_errors( 'rule_type' );
        $rule_value = $handler->get_field_errors( 'rule_value' );
        $rule_outcome = $handler->get_field_errors( 'rule_outcome' );

        // Iterate over the expected fails and check they are in the form errors.
        foreach( $expected_fails as $fail ) {
            $this->assertNotEmpty( $$fail);
        }
    }

    /**
     * Data provider for test_empty_required_fields_should_add_form_error
     *
     * @return array<string, array{request: ServerRequestInterface, expected_fields: string[]}>
     */
    public function formFieldValidationProvider(): array {
        return array(
            'missing_all' => array(
                'request' => $this->get_mock_request( array( 'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ) ) ),
                'expected_fails' => array( 'rule_name', 'rule_type', 'rule_value', 'rule_field', 'rule_outcome' ),
            ),
            'missing_name' => array(
                'request' => $this->get_mock_request( array( 'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ), 'rule_type' => 'contains', 'rule_value' => 'test_value', 'rule_field' => array( 'comment_author' => true ), 'rule_outcome' => 'spam' ) ),
                'expected_fails' => array( 'rule_name' ),
            ),
            'missing_type' => array(
                'request' => $this->get_mock_request( array( 'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ), 'rule_name' => 'test_rule', 'rule_value' => 'test_value', 'rule_field' => array( 'comment_author' => true ), 'rule_outcome' => 'spam' ) ),
                'expected_fails' => array( 'rule_type' ),
            ),
            'missing_value' => array(
                'request' => $this->get_mock_request( array( 'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ), 'rule_name' => 'test_rule', 'rule_type' => 'contains', 'rule_field' => array( 'comment_author' => true ), 'rule_outcome' => 'spam' ) ),
                'expected_fails' => array( 'rule_value' ),
            ),
            'missing_field' => array(
                'request' => $this->get_mock_request( array( 'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ), 'rule_name' => 'test_rule', 'rule_type' => 'contains', 'rule_value' => 'test_value', 'rule_outcome' => 'spam' ) ),
                'expected_fails' => array( 'rule_field' ),
            ),
            'missing_outcome' => array(
                'request' => $this->get_mock_request( array( 'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ), 'rule_name' => 'test_rule', 'rule_type' => 'contains', 'rule_value' => 'test_value', 'rule_field' => array( 'comment_author' => true ) ) ),
                'expected_fails' => array( 'rule_outcome' ),
            )
        );
    }

    /**
     * @testdox It should be possible to populate the form values from the request.
     * 
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::populate_form_values
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_field_value
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_request_value
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_field_value
     * @covers \PinkCrab\Comment_Moderation\Util\Rule_Helper::normalize_fields
     */
    public function test_can_populate_form_values(): void {
        $request = $this->get_mock_request( array( 
            'rule_nonce' => wp_create_nonce( 'pc_cm_rule_nonce' ),
            'rule_id' => 12,
            'rule_name' => 'test_rule',
            'rule_type' => 'contains',
            'rule_value' => 'test_value',
            'rule_enabled' => true,
            'rule_field' => array( 'comment_author' => true, 'comment_author_email' => false, 'comment_author_url' => true, 'comment_content' => false, 'comment_author_IP' => true, 'comment_agent' => false ),
            'rule_outcome' => 'spam'
        ) );

        $handler = new Rule_Form_Handler( $request );
        $handler->populate_form_values();

        $this->assertEquals( 12, $handler->get_field_value( 'rule_id' ) );
        $this->assertEquals( 'test_rule', $handler->get_field_value( 'rule_name' ) );
        $this->assertEquals( 'contains', $handler->get_field_value( 'rule_type' ) );
        $this->assertEquals( 'test_value', $handler->get_field_value( 'rule_value' ) );
        $this->assertTrue( $handler->get_field_value( 'rule_enabled' ) );
        $this->assertEquals(
            array(
                'comment_author'       => true,
                'comment_author_email' => false,
                'comment_author_url'   => true,
                'comment_content'      => false,
                'comment_author_IP'    => true,
                'comment_agent'        => false,
            ),
            $handler->get_field_value( 'rule_field' )
        );
        $this->assertEquals( 'spam', $handler->get_field_value( 'rule_outcome' ) );
    }

    /**
     * @testdox When attempting to get a value from the request, if the value is not set, it should return null.
     * 
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_request_value
     */
    public function test_get_request_value_should_return_null_if_not_set(): void {
        $handler = new Rule_Form_Handler( $this->get_mock_request(['some_key' => 12]) );
        $this->assertEquals( 12, $handler->get_request_value( 'some_key' ) );
        $this->assertNull( $handler->get_request_value( 'test' ) );
    }

    /**
     * @testdox It should be possible to check if there are any field errors based on the key.
     * 
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_field_error
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::has_field_errors
     */
    public function test_can_check_if_there_are_field_errors(): void {
        $handler = new Rule_Form_Handler( $this->get_mock_request() );
        $handler->add_field_error( 'test', 'error' );
        $this->assertTrue( $handler->has_field_errors( 'test' ) );
        $this->assertFalse( $handler->has_field_errors( 'test2' ) );
    }

    /**
     * @testdox It should be possible to check if there are any form errors.
     * 
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_form_error
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::has_form_errors
     */
    public function test_can_check_if_there_are_form_errors(): void {
        $handler = new Rule_Form_Handler( $this->get_mock_request() );
        $handler->add_form_error( 'error' );
        $this->assertTrue( $handler->has_form_errors() );
    }

    /**
     * @testdox It should be possible to get a populated Rule based off the field values.
     * 
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::__construct
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::add_field_value
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_field_value
     * @covers \PinkCrab\Comment_Moderation\Admin\Rule_Form_Handler::get_rule
     */
    public function test_can_get_populated_rule(): void {
        // Skip test.
        $this->markTestSkipped('Test not yet implemented');

        
        $handler = new Rule_Form_Handler( $this->get_mock_request() );
        $handler->add_field_value( 'rule_id', 12 );
        $handler->add_field_value( 'rule_name', 'test_rule' );
        $handler->add_field_value( 'rule_type', 'contains' );
        $handler->add_field_value( 'rule_value', 'test_value' );
        $handler->add_field_value( 'rule_enabled', true );
        $handler->add_field_value( 'rule_field', array( 'comment_author' => true, 'comment_author_email' => false, 'comment_author_url' => true, 'comment_content' => false, 'comment_author_IP' => true, 'comment_agent' => false ) );
        $handler->add_field_value( 'rule_outcome', 'spam' );

        $rule = $handler->get_rule();
        $this->assertInstanceOf( Rule::class, $rule );
        $this->assertEquals( 12, $rule->get_id() );
        $this->assertEquals( 'test_rule', $rule->get_rule_name() );
        $this->assertEquals( 'contains', $rule->get_rule_type() );
        $this->assertEquals( 'test_value', $rule->get_rule_value() );
        $this->assertTrue( $rule->get_rule_enabled() );
        $this->assertEquals(
            array(
                'comment_author'       => true,
                'comment_author_email' => false,
                'comment_author_url'   => true,
                'comment_content'      => false,
                'comment_author_IP'    => true,
                'comment_agent'        => false,
            ),
            $rule->get_fields()
        );
        $this->assertEquals( 'spam', $rule->get_outcome() );
    }


}
