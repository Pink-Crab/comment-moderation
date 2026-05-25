<?php
/**
 * Plugin helper functions.
 *
 * Loaded BEFORE perique-bootstrap.php so every helper defined here is
 * available to classes in `src/`, the three `config/*.php` files, and any
 * code hooked later.
 *
 * Add your own prefixed helpers here. Prefix EVERY function with
 * `##FUNCTION_PREFIX##` so phpcs's PrefixAllGlobals rule passes.
 *
 * @since   ##PLUGIN_VERSION##
 * @package ##NAMESPACE##
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Sample helper — uncomment to use, copy the pattern for your own helpers.
// ---------------------------------------------------------------------------

// if ( ! function_exists( '##FUNCTION_PREFIX##say_hello' ) ) {
//     /**
//      * Return a friendly greeting. Replace me with something useful.
//      *
//      * @param string $name Who to greet.
//      */
//     function ##FUNCTION_PREFIX##say_hello( string $name = 'World' ): string {
//         return sprintf( 'Hello, %s!', $name );
//     }
// }
