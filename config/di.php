<?php
/**
 * Perique Dependency Injection rules.
 *
 * Returns an associative array consumed by `App_Factory::di_rules()`. DICE
 * autowires concrete class type-hints out of the box, so this file only
 * needs rules for:
 *
 *   - binding an interface or abstract to a concrete implementation
 *   - marking a class as shared (singleton)
 *   - overriding an interface binding for a specific consumer
 *   - supplying scalar / pre-built constructor arguments
 *
 * Examples are commented below — uncomment, adapt, or remove as needed.
 * See https://perique.info/ → DI for the full rule reference.
 *
 * @since   ##PLUGIN_VERSION##
 * @package ##NAMESPACE##
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

return array(

	// 1. Interface / abstract binding (global).
	// \##NAMESPACE##\Domain\Some_Interface::class => array(
	//     'instanceOf' => \##NAMESPACE##\Infrastructure\Some_Implementation::class,
	// ),

	// 2. Shared (singleton).
	// \##NAMESPACE##\Application\Services\Cache_Service::class => array(
	//     'shared' => true,
	// ),

	// 3. Per-consumer substitution — overrides global interface binding for
	//    only this one class.
	// \##NAMESPACE##\Application\Services\Foo_Service::class => array(
	//     'substitutions' => array(
	//         \##NAMESPACE##\Domain\Some_Interface::class
	//             => \##NAMESPACE##\Infrastructure\Other_Implementation::class,
	//     ),
	// ),

	// 4. Constructor arguments (mix of scalars + container references).
	// \##NAMESPACE##\Application\Services\Api_Client::class => array(
	//     'shared'          => true,
	//     'constructParams' => array(
	//         'https://api.example.com',
	//         array( \Dice\Dice::CONSTANT => '##CONSTANT_PREFIX##VERSION' ),
	//     ),
	// ),

);
