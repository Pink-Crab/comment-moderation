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

use cli\Tree;
use PinkCrab\Comment_Moderation\Util\Rule_Helper;
use PinkCrab\Comment_Moderation\Rule\Condition\Group;

/**
 * Model for a Rule.
 */
class Rule {

	public const SPAM    = 'spam';
	public const HOLD    = 'hold';
	public const APPROVE = 'approve';
	public const TRASH   = 'trash';

	/**
	 * The ID of the rule.
	 *
	 * @var int|null
	 */
	protected $id;

	/**
	 * The name of the rule.
	 *
	 * @var string
	 */
	protected $rule_name;

	/**
	 * If the rule is enabled.
	 *
	 * @var boolean
	 */
	protected $rule_enabled;

	/**
	 * The rule conditions.
	 *
	 * @var Group
	 */
	protected $conditions;

	/**
	 * The outcome of the rule.
	 *
	 * @var string
	 */
	protected $outcome;

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
	 * @param integer|null            $id           The rule ID.
	 * @param string                  $rule_name    The name of the rule.
	 * @param boolean                 $rule_enabled If the rule is enabled.
	 * @param Group                   $conditions   The rule conditions.
	 * @param string                  $outcome      The outcome of the rule.
	 * @param \DateTimeImmutable|null $created      The date the rule was created.
	 * @param \DateTimeImmutable|null $updated      The date the rule was last updated.
	 */
	public function __construct(
		?int $id,
		string $rule_name,
		bool $rule_enabled,
		Group $conditions,
		string $outcome,
		?\DateTimeImmutable $created = null,
		?\DateTimeImmutable $updated = null
	) {
		$this->id           = $id ? absint( $id ) : null;
		$this->rule_name    = esc_html( $rule_name );
		$this->rule_enabled = $rule_enabled;
		$this->conditions   = $conditions;
		$this->outcome      = esc_attr( $outcome );
		$this->created      = $created ?? new \DateTimeImmutable();
		$this->updated      = $updated ?? new \DateTimeImmutable();
	}


	/**
	 * Get the ID of the rule.
	 *
	 * @return integer|null
	 */
	public function get_id(): ?int {
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
	 * Get if the rule is enabled.
	 *
	 * @return boolean
	 */
	public function get_rule_enabled(): bool {
		return $this->rule_enabled;
	}

	/**
	 * Get the rule conditions.
	 *
	 * @return Group
	 */
	public function get_rule_conditions(): Group {
		return $this->conditions;
	}

	/**
	 * Get the outcome of the rule.
	 *
	 * @return string
	 */
	public function get_outcome(): string {
		return $this->outcome;
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
