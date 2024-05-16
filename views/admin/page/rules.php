<?php

/**
 * Template used to render all the rules for the plugin.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 *
 * @var \PinkCrab\Comment_Moderation\Admin\Rules_Page $pc_cm_page The page object.
 * @var \PinkCrab\Comment_Moderation\Admin\Rules_Table $pc_cm_table The table object.
 */

// Unpack rules table.
$pc_cm_table->prepare_items();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Comment Moderation Rules', 'pc-cm' ); ?></h1>
	<?php $pc_cm_table->render_notices(); ?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc_cm_add_rule' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Rule', 'pc-cm' ); ?></a>
	<form method="post">
		<input type="hidden" name="page" value="pc_cm_rules">
		<?php $pc_cm_table->display(); ?>
	</form>
</div>
