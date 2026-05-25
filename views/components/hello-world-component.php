<?php
/**
 * Template for Hello_World_Component.
 *
 * Resolved by Perique via kebab-case class-name lookup:
 *   PinkCrab\Comment_Moderation\Presentation\View\Component\Hello_World_Component
 *     → views/components/hello-world-component.php
 *
 * Inside this template Perique's PHP_Engine has extracted every component
 * property (any visibility) into a local variable of the same name, so
 * `$name` is the public `Hello_World_Component::$name`.
 *
 * @package PinkCrab\Comment_Moderation\Presentation\View\Component
 *
 * @var string $name Set by Hello_World_Component.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pinkcrab-comment-moderation-hello-world">
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: name to greet */
			__( 'Hello, %s!', 'pinkcrab-comment-moderation' ),
			$name
		)
	);
	?>
</div>
