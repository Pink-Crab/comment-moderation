<?php
/**
 * Template for Hello_World_Component.
 *
 * Resolved by Perique via kebab-case class-name lookup:
 *   ##NAMESPACE##\Presentation\View\Component\Hello_World_Component
 *     → views/components/hello-world-component.php
 *
 * Inside this template:
 *   - `$this` is the View (Renderable), NOT the Component. So you can call
 *     `$this->render(...)`, `$this->component(...)`, `$this->view_model(...)`
 *     to nest other view elements.
 *   - `$this->name` is the public property declared on Hello_World_Component
 *     — Perique passes all component properties (any visibility) to the
 *     template automatically. Don't pass them through a data array.
 *
 * @package ##NAMESPACE##\Presentation\View\Component
 *
 * @var \##NAMESPACE##\Presentation\View\Component\Hello_World_Component $this
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="##PLUGIN_SLUG##-hello-world">
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: name to greet */
			__( 'Hello, %s!', '##TEXT_DOMAIN##' ),
			$this->name
		)
	);
	?>
</div>
<?php
// Example of nesting another component / view from inside this template
// (uncomment + replace with a real class once you have one):
//
// $this->component( new \##NAMESPACE##\Presentation\View\Component\Some_Other_Component( $this->name ) );
// $this->render( 'partials/footer', array( 'year' => gmdate( 'Y' ) ) );
