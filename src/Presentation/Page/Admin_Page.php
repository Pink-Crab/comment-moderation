<?php
/**
 * Admin_Page — Settings → Comment Moderation.
 *
 * Registers the single-screen admin page under WordPress's Settings menu
 * (rebuild spec §3). The page itself does not render the rule UI; it prints
 * a single escaped Elm-mount `<div>` and localises the REST/nonce data the
 * Elm app needs to talk back to WordPress. The Elm app (built into
 * `assets/js/admin.js`) takes over from there.
 *
 * Capability defaults to `manage_options` (administrators only — rebuild
 * spec §2) and is exposed through the `pccm_required_capability` filter so
 * integrations can relax or change the requirement.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Presentation\Page
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Presentation\Page;

use PinkCrab\Comment_Moderation\Application\Settings\Plugin_Config;
use PinkCrab\Comment_Moderation\Presentation\Ajax\Rule_Ajax_Controller;
use PinkCrab\Perique_Admin_Menu\Page\Menu_Page;
use PinkCrab\Perique_Admin_Menu\Page\Page;

/**
 * Settings sub-page that hosts the Elm-driven Comment Moderation admin app.
 *
 * @SuppressWarnings("PHPMD.CamelCasePropertyName")
 */
final class Admin_Page extends Menu_Page {

	/**
	 * The menu slug used to deep-link to the page and in the localized
	 * front-end data. Kept as a class constant so other code (e.g. an
	 * "Edit rule" link from a comment moderation row) can reference it
	 * without a typo.
	 */
	public const PAGE_SLUG = 'pinkcrab-comment-moderation';

	/**
	 * DOM id of the Elm mount node printed by the page view. Constant so
	 * the e2e suite and the Elm bootstrap script can both reference it.
	 */
	public const MOUNT_ID = 'pccm-admin-root';

	/**
	 * Asset handle used when enqueuing the bundled Elm app and when
	 * localizing the REST/nonce payload onto it.
	 */
	public const ASSET_HANDLE = 'pinkcrab-comment-moderation-admin';

	/**
	 * JavaScript global the Elm app reads its bootstrap data from
	 * (REST root, namespace, nonce, mount id, etc.). Kept distinct from
	 * the asset handle so the e2e suite can assert it directly.
	 */
	public const LOCALIZE_OBJECT = 'pccmAdminData';

	/**
	 * Capability filter — exposed so an integration can swap
	 * `manage_options` for a narrower or different capability (rebuild
	 * spec §2 "A developer integrating the plugin can relax or change
	 * that requirement").
	 */
	public const FILTER_CAPABILITY = 'pccm_required_capability';

	/**
	 * Live under Settings (Settings → Comment Moderation — rebuild spec §2).
	 *
	 * @var string|null
	 */
	protected ?string $parent_slug = 'options-general.php';

	/**
	 * Menu and option slug.
	 *
	 * @var string
	 */
	protected string $page_slug = self::PAGE_SLUG;

	/**
	 * View template (under `views/`) printed inside the wp-admin shell.
	 * The template prints only the escaped Elm mount node.
	 *
	 * @var string
	 */
	protected string $view_template = 'pages/admin-page';

	/**
	 * Construct the page, capturing the typed config wrapper for use by
	 * `enqueue()`. The static menu/labels are resolved lazily in their
	 * accessors so translation calls happen after WordPress has finished
	 * loading textdomains (i.e. on `admin_menu`, not at autoload).
	 *
	 * @param Plugin_Config $config Injected typed wrapper over App_Config.
	 */
	public function __construct( private Plugin_Config $config ) {
		$this->view_data = array(
			'mount_id' => self::MOUNT_ID,
		);
	}

	/**
	 * Lazy menu title — uses the plugin's text domain.
	 *
	 * @return string
	 */
	public function menu_title(): string {
		return __( 'Comment Moderation', 'pinkcrab-comment-moderation' );
	}

	/**
	 * Lazy page title (browser tab + H1 in the admin shell).
	 *
	 * @return string
	 */
	public function page_title(): string {
		return __( 'Comment Moderation', 'pinkcrab-comment-moderation' );
	}

	/**
	 * Capability gate. Defaults to `manage_options` (administrators) and
	 * is filterable so an integration can broaden it to e.g. anyone who
	 * can `edit_comments` (rebuild spec §2).
	 *
	 * @return string
	 */
	public function capability(): string {
		/**
		 * Filters the capability required to access the Comment Moderation
		 * admin page. Defaults to `manage_options` (administrators only).
		 *
		 * Hook name resolves to `pccm_required_capability`; the constant is
		 * indirected so callers can reference it as a stable PHP symbol.
		 *
		 * @param string $capability The WordPress capability required.
		 */
		$filtered = apply_filters( self::FILTER_CAPABILITY, 'manage_options' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant value carries the pccm_ prefix.
		return is_string( $filtered ) && '' !== $filtered ? $filtered : 'manage_options';
	}

	/**
	 * Enqueue the Elm app + admin stylesheet, and localize the bootstrap
	 * payload (REST root, namespace, nonce, mount id) onto the script so
	 * the Elm runtime can authenticate its REST calls back to WordPress.
	 *
	 * @param Page $page Current page being rendered.
	 *
	 * @return void
	 */
	public function enqueue( Page $page ): void {
		unset( $page );

		wp_enqueue_script(
			self::ASSET_HANDLE,
			$this->config->plugin_url( 'assets/js/admin.js' ),
			array(),
			$this->config->version(),
			true
		);

		wp_localize_script(
			self::ASSET_HANDLE,
			self::LOCALIZE_OBJECT,
			array(
				'mountId'       => self::MOUNT_ID,
				'restRoot'      => esc_url_raw( rest_url() ),
				'restNamespace' => $this->config->rest_namespace(),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'pageSlug'      => self::PAGE_SLUG,
				// Bootstrap for the stage-20 admin AJAX endpoints — the Elm app
				// posts each request to `ajaxUrl` with `_wpnonce: ajaxNonce` so
				// the controller's nonce + capability preflight can verify it.
				'ajaxUrl'       => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'ajaxNonce'     => Rule_Ajax_Controller::create_nonce(),
				'ajaxActions'   => array(
					'list'   => Rule_Ajax_Controller::ACTION_LIST,
					'get'    => Rule_Ajax_Controller::ACTION_GET,
					'create' => Rule_Ajax_Controller::ACTION_CREATE,
					'update' => Rule_Ajax_Controller::ACTION_UPDATE,
					'delete' => Rule_Ajax_Controller::ACTION_DELETE,
					'clear'  => Rule_Ajax_Controller::ACTION_CLEAR,
				),
			)
		);
	}
}
