<?php

/**
 * Validation Failure.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Rule;

/**
 * Validation Failure.
 */
class Validation_Error extends \Exception {

	/**
	 * Creates an instance of the Validation Error.
	 *
	 * @param string      $field   The field which failed validation.
	 * @param mixed       $value   The value which failed validation.
	 * @param string|null $message The error message.
	 *
	 * @return Validation_Error
	 */
	public static function create( string $field, $value, ?string $message = null ): Validation_Error {
		$message = \sprintf(
			// translators: 1. Field name, 2. Field value, 3. Error message.
			__( 'Validation Error for field: %1$s with value: %2$s. %3$s', 'pc-cm' ),
			\esc_html( $field ),
			\esc_attr( \print_r( $value, true ) ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r,
			$message ? \esc_html( $message ) : ''
		);

		return new Validation_Error( $message );
	}
}
