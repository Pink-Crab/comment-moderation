<?php

/**
 * A group of conditions.
 *
 * @package PinkCrab\Comment_Moderation\Rule\Condition
 *
 * @since 0.1.0
 */
declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Rule\Condition;

use PinkCrab\Comment_Moderation\Rule\Condition\Condition;

/**
 * A group of conditions.
 */
class Group {

	/**
	 * The conditions in the group.
	 *
	 * @var array<Condition>
	 */
	protected $conditions = array();

	/**
	 * Is the group a match all or any.
	 *
	 * @var boolean
	 */
	protected $match_all = true;

	/**
	 * Construct
	 *
	 * @param array<Condition> $conditions The conditions in the group.
	 * @param boolean          $match_all  Is the group a match all or any.
	 */
	public function __construct( array $conditions = array(), bool $match_all = true ) {
		$this->conditions = $conditions;
		$this->match_all  = $match_all;
	}

	/**
	 * Get the conditions in the group.
	 *
	 * @return array<Condition>
	 */
	public function get_conditions(): array {
		return $this->conditions;
	}

	/**
	 * Is the group a match all or any.
	 *
	 * @return boolean
	 */
	public function is_match_all(): bool {
		return $this->match_all;
	}
}
