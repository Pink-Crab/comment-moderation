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
 * @since   ##PLUGIN_VERSION##
 * @package ##NAMESPACE##
 */

declare( strict_types = 1 );

const ##CONSTANT_PREFIX##BASENAME = '';
const ##CONSTANT_PREFIX##PATH     = '';
const ##CONSTANT_PREFIX##URL      = '';
const ##CONSTANT_PREFIX##VERSION  = '';
const ##CONSTANT_PREFIX##MINIMUM_VERSIONS = array(
	'wp'  => '',
	'php' => '',
);
