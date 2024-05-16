<?php

/**
 * Collection of helper functions for working with rules.
 *
 * @package PinkCrab\Comment_Moderation\Util
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Util;

use PinkCrab\Comment_Moderation\Rule\Rule;

/**
 * Collection of helper functions for working with rules.
 */
class Rule_Helper {

	/**
	 * All fields which can be checked against.
	 *
	 * @var string[]
	 */
	protected const ALLOWED_FIELDS = array(
		'comment_author',
		'comment_author_email',
		'comment_author_url',
		'comment_content',
		'comment_author_IP',
		'comment_agent',
	);

		/**
	 * Normalizes the fields
	 *
	 * @param array<string> $fields The fields to normalize.
	 *
	 * @return array<string>
	 */
	public static function normalize_fields( array $fields ): array {
		// Set all values to boolean.
		$fields = array_map( 'boolval', $fields );

		// Remove any invalid fields.
		$fields = array_filter(
			$fields,
			function ( string $field ): bool {
				return in_array( $field, self::ALLOWED_FIELDS, true );
			},
			\ARRAY_FILTER_USE_KEY
		);

		return \wp_parse_args(
			$fields,
			array_fill_keys( self::ALLOWED_FIELDS, false )
		);
	}

	/**
	 * Checks if a key is set and true in an array or values.
	 * Works like wp core checked() function.
	 *
	 * @param string                      $key    The key to check.
	 * @param array<string, boolean>|null $values The values to check.
	 *
	 * @return string
	 */
	public static function array_checked( string $key, ?array $values ): string {
		// If values is null, return empty string.
		if ( is_null( $values ) ) {
			return '';
		}

		return array_key_exists( $key, $values ) && true === $values[ $key ]
			? 'checked'
			: '';
	}
}
