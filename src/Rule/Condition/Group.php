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
class Group implements \JsonSerializable {

	public const MATCH_ALL = true;
	public const MATCH_ANY = false;

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
		$this->conditions = $this->filter_conditions( $conditions );
		$this->match_all  = $match_all;
	}

	/**
	 * Filter the conditions on construct.
	 *
	 * @param array<mixed> $conditions The conditions to filter.
	 *
	 * @return array<Group|Condition>
	 */
	private function filter_conditions( array $conditions ): array {
		return array_filter(
			$conditions,
			function ( $condition ) {
				return $condition instanceof Group || $condition instanceof Condition;
			}
		);
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

	/**
	 * Json serializable.
	 *
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		return array(
			'type'       => 'group',
			'conditions' => $this->conditions,
			'match_all'  => $this->match_all,
		);
	}
}
