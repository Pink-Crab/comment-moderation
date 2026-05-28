<?php
/**
 * Template for Admin_Page (Settings → Comment Moderation).
 *
 * The PHP shell prints a `wp-admin`-styled wrapper containing only the
 * Elm-mount node. Everything inside that node is rendered by the Elm app
 * (assets/js/admin.js) — the spec is explicit that this is "a webpage
 * inside wp-admin, not a standalone app".
 *
 * The bootstrap REST root / namespace / nonce arrive via `wp_localize_script`
 * (see Admin_Page::enqueue) so this template does not echo any data at all;
 * it only emits the mount node, escaped.
 *
 * @package PinkCrab\Comment_Moderation\Presentation\Page
 *
 * @var string $mount_id The Elm mount DOM id (constant, set by Admin_Page).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pinkcrab-comment-moderation">
	<h1 class="wp-heading-inline">
		<?php echo esc_html__( 'Comment Moderation', 'pinkcrab-comment-moderation' ); ?>
	</h1>
	<hr class="wp-header-end" />
	<div id="<?php echo esc_attr( $mount_id ); ?>"></div>
</div>
