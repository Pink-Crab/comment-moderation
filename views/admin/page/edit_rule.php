<?php

/**
 * Template used to render the add/edit rule page.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 *
 * @var Rule|null $pc_cm_rule If set editing a rule.
 * @var Rules_Page $pc_cm_page The page object.
 * @var Rule_Form_Handler $pc_cm_form_handler The form handler.
 * @var array<string, string[]> $pc_cm_rule_types Array of errors.
 */

use PinkCrab\Comment_Moderation\Util\Rule_Helper;
?>
<div class="wrap">
	<h1><?php echo esc_html( $pc_cm_page->page_title() ); ?></h1>

	<?php
	if ( $pc_cm_form_handler->has_form_errors() ) :
		?>
		<div class="notice notice-error">
			<?php foreach ( $pc_cm_form_handler->get_form_errors() as $pc_cm_form_error ) : ?>
				<p><?php echo esc_html( $pc_cm_form_error ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php echo $pc_cm_form_handler->nonce_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="page" value="<?php echo esc_html( $pc_cm_page->slug() ); ?>">
		<input type="hidden" name="action" value="save_rule">
		<?php if ( $pc_cm_rule ) : ?>
			<input type="hidden" name="rule_id" value="<?php echo esc_attr( $pc_cm_rule->get_id() ); ?>">
		<?php endif; ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="rule_name"><?php esc_html_e( 'Rule Name', 'pc-cm' ); ?></label>
					</th>
					<td>
						<input type="text" name="rule_name" id="rule_name" value="<?php echo esc_html( $pc_cm_form_handler->get_field_value( 'rule_name' ) ); ?>" class="regular-text<?php echo $pc_cm_form_handler->has_field_errors( 'rule_name' ) ? ' field-error' : ''; ?>">
						<?php if ( $pc_cm_form_handler->has_field_errors( 'rule_name' ) ) : ?>
							<?php foreach ( $pc_cm_form_handler->get_field_errors( 'rule_name' ) as $pc_cm_field_error ) : ?>
								<p class="description error"><?php echo esc_html( $pc_cm_field_error ); ?></p>
							<?php endforeach; ?>
						<?php endif; ?>                    
						</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rule_type"><?php esc_html_e( 'Rule Type', 'pc-cm' ); ?></label>
					</th>
					<td>
						<select name="rule_type" id="rule_type" class="<?php echo $pc_cm_form_handler->has_field_errors( 'rule_type' ) ? ' field-error' : ''; ?>">
							<?php foreach ( $pc_cm_rule_types as $pc_cm_rule_type => $pc_cm_rule_label ) : ?>
								<option value="<?php echo esc_attr( $pc_cm_rule_type ); ?>" <?php selected( $pc_cm_rule ? $pc_cm_rule->get_rule_type() : '', $pc_cm_rule_type ); ?>><?php echo esc_html( $pc_cm_rule_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( $pc_cm_form_handler->has_field_errors( 'rule_type' ) ) : ?>
							<?php foreach ( $pc_cm_form_handler->get_field_errors( 'rule_type' ) as $pc_cm_field_error ) : ?>
								<p class="description error"><?php echo esc_html( $pc_cm_field_error ); ?></p>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rule_value"><?php esc_html_e( 'Rule Value', 'pc-cm' ); ?></label>
					</th>
					<td>
						<input type="text" name="rule_value" id="rule_value" value="<?php echo esc_attr( $pc_cm_rule ? $pc_cm_rule->get_rule_value() : '' ); ?>" class="regular-text<?php echo $pc_cm_form_handler->has_field_errors( 'rule_type' ) ? ' field-error' : ''; ?> ">
						<?php if ( $pc_cm_form_handler->has_field_errors( 'rule_value' ) ) : ?>
							<?php foreach ( $pc_cm_form_handler->get_field_errors( 'rule_value' ) as $pc_cm_field_error ) : ?>
								<p class="description error"><?php echo esc_html( $pc_cm_field_error ); ?></p>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Fields', 'pc-cm' ); ?></label>
					</th>
					<td>
						<fieldset>
							<label for="rule_field_comment_content">
								<?php esc_html_e( 'Comment', 'pc-cm' ); ?>
								<input type="checkbox" 
									name="rule_field[comment_content]" id="rule_field_comment_content" 
									value="1" 
									<?php echo esc_attr( Rule_Helper::array_checked( 'comment_content', $pc_cm_form_handler->get_field_value( 'rule_field' ) ) ); ?> 
								>
							</label>
							<label for="rule_field_comment_author">
								<?php esc_html_e( 'Author', 'pc-cm' ); ?>
								<input type="checkbox" 
									name="rule_field[comment_author]" id="rule_field_comment_author" 
									value="1" 
									<?php echo esc_attr( Rule_Helper::array_checked( 'comment_author', $pc_cm_form_handler->get_field_value( 'rule_field' ) ) ); ?> 
								>
							</label>
							<label for="rule_field_comment_email">
								<?php esc_html_e( 'Email', 'pc-cm' ); ?>
								<input type="checkbox" 
									name="rule_field[comment_author_email]" id="rule_field_comment_email" 
									value="1"
									<?php echo esc_attr( Rule_Helper::array_checked( 'comment_author_email', $pc_cm_form_handler->get_field_value( 'rule_field' ) ) ); ?> 
								>
							</label>
							<label for="rule_field_comment_url">
								<?php esc_html_e( 'URL', 'pc-cm' ); ?>
								<input type="checkbox" 
									name="rule_field[comment_author_url]" id="rule_field_comment_url" 
									value="1"
									<?php echo esc_attr( Rule_Helper::array_checked( 'comment_author_url', $pc_cm_form_handler->get_field_value( 'rule_field' ) ) ); ?> 
								>
							</label>
							<label for="rule_field_comment_agent">
								<?php esc_html_e( 'User Agent', 'pc-cm' ); ?>
								<input type="checkbox" 
									name="rule_field[comment_agent]" id="rule_field_comment_agent" 
									value="1"
									<?php echo esc_attr( Rule_Helper::array_checked( 'comment_agent', $pc_cm_form_handler->get_field_value( 'rule_field' ) ) ); ?> 
								>
							</label>
							<label for="rule_field_comment_ip">
								<?php esc_html_e( 'IP Address', 'pc-cm' ); ?>
								<input type="checkbox" 
									name="rule_field[comment_author_IP]" id="rule_field_comment_ip" 
									value="1"
									<?php echo esc_attr( Rule_Helper::array_checked( 'comment_author_IP', $pc_cm_form_handler->get_field_value( 'rule_field' ) ) ); ?> 
								>
							</label>
						</fieldset>
						<?php if ( $pc_cm_form_handler->has_field_errors( 'rule_field' ) ) : ?>
							<?php foreach ( $pc_cm_form_handler->get_field_errors( 'rule_field' ) as $pc_cm_field_error ) : ?>
								<p class="description error"><?php echo esc_html( $pc_cm_field_error ); ?></p>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rule_enabled"><?php esc_html_e( 'Enabled', 'pc-cm' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="rule_enabled" id="rule_enabled" value="1" <?php checked( $pc_cm_rule ? $pc_cm_rule->get_rule_enabled() : 0, 1 ); ?>>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rule_outcome"><?php esc_html_e( 'Outcome', 'pc-cm' ); ?></label>
					</th>
					<td>
						<select name="rule_outcome" id="rule_outcome">
							<option value="approve" <?php selected( $pc_cm_rule ? $pc_cm_rule->rule_outcome : '', 'approve' ); ?>><?php esc_html_e( 'Approve', 'pc-cm' ); ?></option>
							<option value="spam" <?php selected( $pc_cm_rule ? $pc_cm_rule->rule_outcome : '', 'spam' ); ?>><?php esc_html_e( 'Spam', 'pc-cm' ); ?></option>
							<option value="trash" <?php selected( $pc_cm_rule ? $pc_cm_rule->rule_outcome : '', 'trash' ); ?>><?php esc_html_e( 'Trash', 'pc-cm' ); ?></option>
					</td>
				</tr>
			</tbody>

		</table>
		<?php submit_button( $pc_cm_rule ? __( 'Update Rule', 'pc-cm' ) : __( 'Add Rule', 'pc-cm' ) ); ?>
	</form>
</div>