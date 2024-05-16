<?php

/**
 * Template used to render an error page.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 *
 * @var string[] $pc_cm_errors Any errors that have been generated.
 * @var Rules_Page $pc_cm_page The page object.
 */
?>
<div class="wrap">
	<h1><?php echo esc_html( $pc_cm_page->page_title() ); ?></h1>
	<p><?php esc_html_e( 'An error has occurred, please see the details below.', 'pc-cm' ); ?></p>
	<ul>
		<?php foreach ( $pc_cm_errors ?? array() as $pc_cm_error ) : ?>
			<li><?php echo esc_html( $pc_cm_error ); ?></li>
		<?php endforeach; ?>
	</ul>
</div>