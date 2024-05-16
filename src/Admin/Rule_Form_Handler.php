<?php

/**
 * Form handler for New/Edit Rule forms.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Admin;

use PinkCrab\Nonce\Nonce;
use PinkCrab\Comment_Moderation\Rule\Rule;
use Psr\Http\Message\ServerRequestInterface;
use PinkCrab\Comment_Moderation\Util\Rule_Helper;

/**
 * Handles the form submission for the Rule form.
 */
class Rule_Form_Handler {

	private const NONCE_FIELD = 'rule_nonce';
	private const FIELDS      = array(
		'rule_id',
		'rule_name',
		'rule_type',
		'rule_value',
		'rule_enabled',
		'rule_field',
		'rule_outcome',
	);

	/**
	 * The forms nonce.
	 *
	 * @var Nonce
	 */
	private $nonce;

	/**
	 * The current Request.
	 *
	 * @var ServerRequestInterface
	 */
	private $request;

	/**
	 * Field Errors.
	 *
	 * @var array<string, string[]> Array of errors with field keys as the key.
	 */
	private $field_errors = array();

	/**
	 * Form Errors
	 *
	 * @var string[] Array of form level errors.
	 */
	private $form_errors = array();

	/**
	 * The form values.
	 *
	 * @var array<string, mixed>
	 */
	private $form_values = array(
		'rule_id'      => null,
		'rule_name'    => null,
		'rule_type'    => null,
		'rule_value'   => null,
		'rule_field'   => null,
		'rule_enabled' => null,
		'rule_outcome' => null,
	);

	/**
	 * Construct.
	 *
	 * @param ServerRequestInterface $request The current request.
	 */
	public function __construct( ServerRequestInterface $request ) {
		$this->nonce   = new Nonce( 'pc_cm_rule_nonce' );
		$this->request = $request;
	}

	/**
	 * Add a field value.
	 *
	 * @param string $field The field key.
	 * @param mixed  $value The value to set.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the field is not valid.
	 */
	public function add_field_value( string $field, $value ): void {
		// If the field doesnt exist, throw an exception.
		if ( ! in_array( $field, self::FIELDS, true ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Field %s is not a valid field.', $field ) ) );
		}
		$this->form_values[ $field ] = $value;
	}

	/**
	 * Gets a value from the form.
	 *
	 * @param string $field The field key.
	 *
	 * @return mixed
	 */
	public function get_field_value( string $field ) {
		$value = $this->form_values[ $field ];
		return '' !== $value ? $value : null;
	}

	/**
	 * Get the nonce field.
	 *
	 * @return string
	 */
	public function nonce_field(): string {
		return $this->nonce->nonce_field( self::NONCE_FIELD );
	}

	/**
	 * Checks if the form has been submitted.
	 *
	 * @return boolean
	 */
	public function is_submitted(): bool {
		return null !== $this->get_request_value( self::NONCE_FIELD );
	}

	/**
	 * Add a rule to the existing form values.
	 *
	 * @param Rule $rule The rule to add.
	 *
	 * @return void
	 */
	public function add_rule( Rule $rule ): void {
		$this->add_field_value( 'rule_id', absint( $rule->get_id() ) );
		$this->add_field_value( 'rule_name', esc_html( $rule->get_rule_name() ) );
		$this->add_field_value( 'rule_type', esc_attr( $rule->get_rule_type() ) );
		$this->add_field_value( 'rule_value', esc_html( $rule->get_rule_value() ) );
		$this->add_field_value( 'rule_enabled', (bool) $rule->get_rule_enabled() );
		$this->add_field_value( 'rule_field', Rule_Helper::normalize_fields( $rule->get_fields() ) );
		$this->add_field_value( 'rule_outcome', esc_attr( $rule->get_outcome() ) );
	}

		/**
		 * Verify the form submission and that the form values are valid..
		 *
		 * @return void
		 */
	public function verify_form_submission(): void {
		// Check the nonce.
		if ( ! $this->nonce->validate( \sanitize_text_field( $this->get_request_value( self::NONCE_FIELD ) ) ) ) {
			$this->add_form_error( 'Invalid form submission. Failed nonce check' );
			return;
		}

		// Validate the form.
		$this->validate_request_fields();
	}

		/**
		 * If the form is being processed for a new rule.
		 * Populate the form values with those submitted, even if not saved.
		 *
		 * @return void
		 */
	public function populate_form_values(): void {
		$this->add_field_value( 'rule_id', $this->get_request_value( 'rule_id' ) );
		$this->add_field_value( 'rule_name', \sanitize_text_field( $this->get_request_value( 'rule_name' ) ) );
		$this->add_field_value( 'rule_type', \sanitize_text_field( $this->get_request_value( 'rule_type' ) ) );
		$this->add_field_value( 'rule_value', \sanitize_text_field( $this->get_request_value( 'rule_value' ) ) );
		$this->add_field_value( 'rule_enabled', (bool) $this->get_request_value( 'rule_enabled' ) );
		$this->add_field_value( 'rule_field', Rule_Helper::normalize_fields( (array) $this->get_request_value( 'rule_field' ) ) );
		$this->add_field_value( 'rule_outcome', \sanitize_text_field( $this->get_request_value( 'rule_outcome' ) ) );
	}

		/**
		 * Validate the form.
		 *
		 * @return void
		 */
	private function validate_request_fields(): void {
		// Validate the rule name.
		if ( empty( $this->get_request_value( 'rule_name' ) ) ) {
			$this->add_field_error( 'rule_name', 'Rule name is required.' );
		}

		// Validate the rule type.
		if ( empty( $this->get_request_value( 'rule_type' ) ) ) {
			$this->add_field_error( 'rule_type', 'Rule type is required.' );
		}

		// Validate the rule value.
		if ( empty( $this->get_request_value( 'rule_value' ) ) ) {
			$this->add_field_error( 'rule_value', 'Rule value is required.' );
		}

		// Validate the rule field.
		if ( empty( $this->get_request_value( 'rule_field' ) ) ) {
			$this->add_field_error( 'rule_field', 'At least one rule field is required' );
		}

		// Validate the rule outcome.
		if ( empty( $this->get_request_value( 'rule_outcome' ) ) ) {
			$this->add_field_error( 'rule_outcome', 'Rule outcome is required.' );
		}

		// If we have any field errors, add a form level error.
		if ( ! empty( $this->field_errors ) ) {
			$this->add_form_error( 'Please correct the errors below.' );
		}
	}

		/**
		 * Gets a value from the request if set.
		 *
		 * @param string $key The key to get.
		 *
		 * @return mixed
		 */
	public function get_request_value( string $key ) {
		return $this->request->getParsedBody()[ $key ] ?? null;
	}

		/**
		 * Add a field error.
		 *
		 * @param string $field The field key.
		 * @param string $error The error message.
		 *
		 * @return void
		 */
	public function add_field_error( string $field, string $error ): void {
		$this->field_errors[ esc_attr( $field ) ][] = esc_html( $error );
	}

		/**
		 * Checks if a field has any errors.
		 *
		 * @param string $field The field key.
		 *
		 * @return boolean
		 */
	public function has_field_errors( string $field ): bool {
		return ! empty( $this->field_errors[ esc_attr( $field ) ] );
	}

		/**
		 * Gets any errors for a given field.
		 *
		 * @param string $field The field key.
		 *
		 * @return string[]
		 */
	public function get_field_errors( string $field ): array {
		return $this->field_errors[ esc_attr( $field ) ] ?? array();
	}

		/**
		 * Add a form level error.
		 *
		 * @param string $error The error message.
		 *
		 * @return void
		 */
	public function add_form_error( string $error ): void {
		$this->form_errors[] = esc_html( $error );
	}

		/**
		 * Checks if the form has any errors.
		 *
		 * @return boolean
		 */
	public function has_form_errors(): bool {
		return ! empty( $this->form_errors );
	}

		/**
		 * Get all form level errors.
		 *
		 * @return string[]
		 */
	public function get_form_errors(): array {
		return $this->form_errors;
	}

	/**
	 * Get the rule bsaed on the form values.
	 *
	 * @return Rule
	 */
	public function get_rule(): Rule {
		return new Rule(
			$this->get_field_value( 'rule_id' ),
			$this->get_field_value( 'rule_name' ),
			$this->get_field_value( 'rule_type' ),
			$this->get_field_value( 'rule_value' ),
			$this->get_field_value( 'rule_enabled' ),
			Rule_Helper::normalize_fields( $this->get_field_value( 'rule_field' ) ),
			$this->get_field_value( 'rule_outcome' ),
		);
	}
}
