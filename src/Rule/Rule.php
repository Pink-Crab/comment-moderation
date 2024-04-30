<?php

/**
 * Model for a Rule.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 *
 * @property int $id
 * @property string $rule_name
 * @property string $rule_type
 * @property string $rule_value
 * @property int $rule_enabled
 * @property array $fields
 * @property \DateTimeImmutable $created
 * @property \DateTimeImmutable $updated
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Rule;

/**
 * Model for a Rule.
 */
class Rule {

	public const RULE_TYPE_CONTAINS    = 'contains';
	public const RULE_TYPE_NOT_CONTAIN = 'not_contain';
	public const RULE_TYPE_EQUALS      = 'equals';
	public const RULE_TYPE_NOT_EQUALS  = 'not_equals';
	public const RULE_TYPE_REGEX       = 'regex';
	public const RULE_TYPE_WILDCARD    = 'wildcard';

	protected const ALL_RULES = array(
		self::RULE_TYPE_CONTAINS,
		self::RULE_TYPE_NOT_CONTAIN,
		self::RULE_TYPE_EQUALS,
		self::RULE_TYPE_NOT_EQUALS,
		self::RULE_TYPE_REGEX,
		self::RULE_TYPE_WILDCARD,
	);


	/**
	 * The ID of the rule.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * The name of the rule.
	 *
	 * @var string
	 */
	protected $rule_name;

	/**
	 * The type of the rule.
	 *
	 * @var string
	 */
	protected $rule_type;

	/**
	 * The value of the rule.
	 *
	 * @var string
	 */
	protected $rule_value;

	/**
	 * If the rule is enabled.
	 *
	 * @var boolean
	 */
	protected $rule_enabled;

	/**
	 * The fields the rule applies to.
	 *
	 * @var array<string>
	 */
	protected $fields;

	/**
	 * The date the rule was created.
	 *
	 * @var \DateTimeImmutable
	 */
	protected $created;

	/**
	 * The date the rule was last updated.
	 *
	 * @var \DateTimeImmutable
	 */
	protected $updated;

	/**
	 * Create a new instance of the Rule.
	 *
	 * @param integer                 $id           The rule ID.
	 * @param string                  $rule_name    The name of the rule.
	 * @param string                  $rule_type    The type of the rule.
	 * @param string                  $rule_value   The value of the rule.
	 * @param boolean                 $rule_enabled If the rule is enabled.
	 * @param array<string>           $fields       The fields the rule applies to.
	 * @param \DateTimeImmutable|null $created      The date the rule was created.
	 * @param \DateTimeImmutable|null $updated      The date the rule was last updated.
	 */
	public function __construct(
		int $id,
		string $rule_name,
		string $rule_type,
		string $rule_value,
		bool $rule_enabled,
		array $fields,
		?\DateTimeImmutable $created = null,
		?\DateTimeImmutable $updated = null
	) {
		$this->id           = absint( $id );
		$this->rule_name    = esc_html( $rule_name );
		$this->rule_type    = $this->validate_rule_type( $rule_type );
		$this->rule_value   = esc_attr( $rule_value );
		$this->rule_enabled = $rule_enabled;
		$this->fields       = array_map( 'esc_attr', $fields );
		$this->created      = $created ?? new \DateTimeImmutable();
		$this->updated      = $updated ?? new \DateTimeImmutable();
	}

	/**
	 * Sets the rule type.
	 *
	 * Filtered to ensure only valid types are set.
	 *
	 * @param string $type The rule type.
	 *
	 * @return string
	 */
	private function validate_rule_type( string $type ): string {
		// If rules in not in the defined list, throw an exception.
		if ( ! in_array( $type, self::ALL_RULES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					// translators: 1: Rule Type, 2: Valid Rule Types
					'Invalid Rule Type: %s, Valid Types: %s',
					esc_attr( $type ),
					esc_attr( implode( ', ', self::ALL_RULES ) )
				)
			);
		}

		return esc_attr( $type );
	}

	/**
	 * Get the ID of the rule.
	 *
	 * @return integer
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get the name of the rule.
	 *
	 * @return string
	 */
	public function get_rule_name(): string {
		return $this->rule_name;
	}

	/**
	 * Get the type of the rule.
	 *
	 * @return string
	 */
	public function get_rule_type(): string {
		return $this->rule_type;
	}

	/**
	 * Get the value of the rule.
	 *
	 * @return string
	 */
	public function get_rule_value(): string {
		return $this->rule_value;
	}

	/**
	 * Get if the rule is enabled.
	 *
	 * @return boolean
	 */
	public function get_rule_enabled(): bool {
		return $this->rule_enabled;
	}

	/**
	 * Get the fields the rule applies to.
	 *
	 * @return array<string>
	 */
	public function get_fields(): array {
		return $this->fields;
	}

	/**
	 * Get the date the rule was created.
	 *
	 * @return \DateTimeImmutable
	 */
	public function get_created(): \DateTimeImmutable {
		return $this->created;
	}

	/**
	 * Get the date the rule was last updated.
	 *
	 * @return \DateTimeImmutable
	 */
	public function get_updated(): \DateTimeImmutable {
		return $this->updated;
	}
}
