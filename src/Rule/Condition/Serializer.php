<?php

/**
 * Utility class used to serialize and unserialize Rule Conditions.
 *
 * @package PinkCrab\Comment_Moderation\Rule\Condition
 *
 * @since 0.1.0
 */
declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Rule\Condition;

use PinkCrab\Comment_Moderation\Rule\Condition\Condition;

/**
 * Utility class used to serialize and unserialize Rule Conditions.
 */
class Serializer {

	/**
	 * Encode a Condition to a JSON string.
	 *
	 * @param Group $group The group to encode.
	 *
	 * @return string
	 */
	public function encode( Group $group ): string {
		// Encode the conditions, but ensure we only include valid groups.
		return wp_json_encode( $group );
	}

	/**
	 * Decode a JSON string into an array of Conditions.
	 *
	 * @param string $json The JSON string to decode.
	 *
	 * @return Group|null
	 */
	public function decode( string $json ): ?Group {
		$decoded = json_decode( $json, true );

		// If we dont have no data, return null.
		if ( is_null( $decoded ) || empty( $decoded ) ) {
			return null;
		}

		// If we dont have type = group, throw exception.
		if ( ! is_array( $decoded ) || ! array_key_exists( 'type', $decoded ) || $decoded['type'] !== 'group' ) {
			throw new \InvalidArgumentException( 'Invalid group, type must be group' );
		}

		// Parse the group.
		return $this->parse_group( $decoded );
	}

	/**
	 * Parse simple group to Group object.
	 *
	 * @param array $group The group to parse.
	 *
	 * @return Group
	 * @throws \InvalidArgumentException If the group is not valid.
	 */
	private function parse_group( array $group ): Group {
		// Check we have the required keys.
		$keys = array( 'conditions', 'match_all', 'type' );
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $group ) ) {
				throw new \InvalidArgumentException( sprintf( 'Invalid group, missing key: %s', $key ) );
			}
		}

		// If the type is not group
		if ( $group['type'] !== 'group' ) {
			throw new \InvalidArgumentException( 'Invalid group, type must be group' );
		}

		// Parse the conditions.
		$conditions = array_map(
			function ( $condition ) {
				// If we have a group, parse it.
				if ( $condition['type'] === 'group' ) {
					return $this->parse_group( $condition );
				}
				// If we have a condition, parse it.
				if ( $condition['type'] === 'condition' ) {
					return $this->parse_condition( $condition );
				}
				// If we have an invalid type, throw exception.
				throw new \InvalidArgumentException( 'Invalid condition type' );
			},
			$group['conditions']
		);

		// Return the new group.
		return new Group( $conditions, \boolval( $group['match_all'] ) );
	}

	/**
	 * Parse simple condition to Condition object.
	 *
	 * @param array $condition The condition to parse.
	 *
	 * @return Condition
	 * @throws \InvalidArgumentException If the condition is not valid.
	 */
	private function parse_condition( array $condition ): Condition {
		// Check we have the required keys.
		$keys = array( 'rule_type', 'rule_value', 'comment_content', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_ip', 'comment_agent' );
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $condition ) ) {
				throw new \InvalidArgumentException( sprintf( 'Invalid condition, missing key: %s', $key ) );
			}
		}

		// Return the new condition.
		return new Condition(
			esc_html( $condition['rule_type'] ),
			function ( Condition $model ) use ( $condition ): Condition {
				// Set the values.
				return $model->set_rule_value( $condition['rule_value'] )
					->set_comment_content( (bool) $condition['comment_content'] )
					->set_comment_author( (bool) $condition['comment_author'] )
					->set_comment_author_email( (bool) $condition['comment_author_email'] )
					->set_comment_author_url( (bool) $condition['comment_author_url'] )
					->set_comment_author_ip( (bool) $condition['comment_author_ip'] )
					->set_comment_agent( (bool) $condition['comment_agent'] );
			}
		);
	}
}
