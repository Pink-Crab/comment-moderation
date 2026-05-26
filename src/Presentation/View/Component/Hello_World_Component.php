<?php
/**
 * Hello_World_Component — a minimal Perique Component.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Presentation\View\Component
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Presentation\View\Component;

use PinkCrab\Perique\Services\View\Component\Component;

/**
 * Demonstrates the View Component pattern.
 *
 * Template resolution (per Perique core docs), in order:
 *   1. Alias mapping via `Hooks::COMPONENT_ALIASES` filter.
 *   2. `@view` docblock annotation.
 *   3. `public function template(): ?string` method.
 *   4. The class name converted to kebab-case.
 *
 * This class uses option (4): the kebab-case of `Hello_World_Component` is
 * `hello-world-component`, so Perique looks for
 * `views/components/hello-world-component.php`.
 *
 * All properties (any visibility) are passed to the template; inside the
 * template `$this` is bound to the View (Renderable), so you can call
 * `$this->render(...)`, `$this->component(...)`, `$this->view_model(...)`
 * to nest other view elements.
 */
final class Hello_World_Component extends Component {

	/**
	 * The name to greet. Read inside the template as `$this->name`.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Construct the component with the name to be greeted.
	 *
	 * @param string $name The name to greet inside the template.
	 */
	public function __construct( string $name = 'World' ) {
		$this->name = $name;
	}
}
