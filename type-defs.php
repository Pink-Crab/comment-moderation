<?php
/**
 * Type definitions for static analysis.
 *
 * Plugin-defined constants are declared here (with empty values) so PHPStan,
 * Psalm, IDE intellisense, etc. can resolve them without executing the main
 * plugin file. Referenced by .phpstan.neon via `parameters.bootstrapFiles`.
 *
 * Do NOT require this file at runtime — the real values are set by the main
 * plugin file's `define()` calls.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation
 */

declare( strict_types = 1 );

const PINKCRAB_COMMENT_MODERATION_BASENAME         = '';
const PINKCRAB_COMMENT_MODERATION_PATH             = '';
const PINKCRAB_COMMENT_MODERATION_URL              = '';
const PINKCRAB_COMMENT_MODERATION_VERSION          = '';
const PINKCRAB_COMMENT_MODERATION_MINIMUM_VERSIONS = array(
	'wp'  => '',
	'php' => '',
);
