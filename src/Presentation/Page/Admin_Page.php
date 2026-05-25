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
 * Three extensibility points are exposed here for integrators (rebuild spec
 * §11 "Replace or restyle the management screen, and change which users may
 * access it. … Customise the 'Edit Rule' deep-link markup used elsewhere in
 * the admin."):
 *
 *   - `pccm_required_capability` (filter): swap the default `manage_options`
 *     for a narrower or different capability so e.g. anyone who can
 *     `edit_comments` can manage rules.
 *   - `pccm_admin_page_html` (filter): receives the fully rendered admin
 *     screen markup before it is echoed, with the Admin_Page instance as a
 *     second argument. Returning a different string replaces the screen
 *     entirely; mutating the string restyles it.
 *   - `pccm_edit_rule_link` (filter): receives the deep-link `<a>` markup
 *     that {@see Admin_Page::edit_link()} produces, plus the rule id, label
 *     and URL, so other parts of an integration (e.g. a "Edit Rule" link in
 *     an Akismet comment row) can swap the default anchor for their own.
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
	 * Filter applied to the rendered admin-page HTML just before it is
	 * echoed inside the wp-admin shell (rebuild spec §11 "Replace or
	 * restyle the management screen"). The default screen is the small
	 * wrapper around the Elm mount node; an integrator can mutate the
	 * markup (restyle) or replace it entirely.
	 */
	public const FILTER_PAGE_HTML = 'pccm_admin_page_html';

	/**
	 * Filter applied to the "Edit Rule" deep-link anchor markup that
	 * {@see self::edit_link()} produces (rebuild spec §11 "Customise the
	 * 'Edit Rule' deep-link markup used elsewhere in the admin"). The
	 * filter receives the default `<a>` HTML, the rule id, the resolved
	 * label, and the deep-link URL.
	 */
	public const FILTER_EDIT_RULE_LINK = 'pccm_edit_rule_link';

	/**
	 * Query var the page reads to pre-open the add/edit pane for a
	 * specific rule. Kept as a public constant so external callers
	 * building their own deep-links use the same name.
	 */
	public const EDIT_QUERY_VAR = 'pccm_edit';

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

		wp_enqueue_style(
			self::ASSET_HANDLE,
			$this->config->plugin_url( 'assets/css/admin.css' ),
			array(),
			$this->config->version()
		);

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

	/**
	 * Render callback registered with the admin-menu module. Wraps the base
	 * `Menu_Page::render_view()` so the rendered admin-shell markup passes
	 * through the `pccm_admin_page_html` filter before it is echoed
	 * (rebuild spec §11 "Replace or restyle the management screen").
	 *
	 * Integrators can:
	 *  - **Restyle** by returning the original HTML with wrapper class or
	 *    inline tweaks applied,
	 *  - **Replace** by returning a different string entirely.
	 *
	 * The default template already escapes every dynamic value before
	 * output (see `views/pages/admin-page.php`); the filtered result is
	 * echoed as the HTML it is now expected to be.
	 *
	 * @return callable
	 */
	public function render_view(): callable {
		$default = parent::render_view();

		return function () use ( $default ): void {
			ob_start();
			$default();
			$html = (string) ob_get_clean();

			/**
			 * Filters the rendered admin-page markup before it is echoed.
			 *
			 * Hook name resolves to `pccm_admin_page_html`; the constant is
			 * indirected so callers can reference it as a stable PHP symbol.
			 *
			 * @param string     $html The rendered markup (already escaped).
			 * @param Admin_Page $page The Admin_Page instance, for context.
			 */
			$filtered = apply_filters( self::FILTER_PAGE_HTML, $html, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant value carries the pccm_ prefix.

			// The default template escapes every dynamic value; the filtered
			// output is HTML by contract (the integrator chose to replace or
			// restyle the markup). Echoing it directly is intentional.
			echo is_string( $filtered ) ? $filtered : $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produced by the page template (already escaped) or by an integrator who opted in via the documented pccm_admin_page_html filter.
		};
	}

	/**
	 * Build a deep-link URL that opens the management screen with the given
	 * rule pre-selected for editing (rebuild spec §6 "Direct link to a
	 * rule"). The URL is the screen's own slug with the `pccm_edit` query
	 * var carrying the rule id — kept symmetrical with the constants the
	 * Elm app reads so external callers do not have to re-derive it.
	 *
	 * @param integer $rule_id Rule id to deep-link to.
	 *
	 * @return string
	 */
	public static function edit_url( int $rule_id ): string {
		return add_query_arg(
			array(
				'page'               => self::PAGE_SLUG,
				self::EDIT_QUERY_VAR => $rule_id,
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Build the "Edit Rule" deep-link anchor markup that other parts of an
	 * integration can drop next to a comment row to open this screen on the
	 * matching rule (rebuild spec §6 + §11). The default markup is an
	 * escaped `<a>` with a stable `pccm-edit-rule-link` class so integrators
	 * can style it without rewriting it; passing the result through the
	 * `pccm_edit_rule_link` filter lets them replace it entirely if they
	 * want (e.g. a button, an icon, additional attributes).
	 *
	 * @param integer     $rule_id Rule id to deep-link to.
	 * @param string|null $label   Optional anchor text. Falls back to a
	 *                             translated "Edit Rule" when null or empty.
	 *
	 * @return string
	 */
	public static function edit_link( int $rule_id, ?string $label = null ): string {
		$resolved_label = null === $label || '' === trim( $label )
			? __( 'Edit Rule', 'pinkcrab-comment-moderation' )
			: $label;
		$url            = self::edit_url( $rule_id );

		$default = sprintf(
			'<a class="pccm-edit-rule-link" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $resolved_label )
		);

		/**
		 * Filters the "Edit Rule" deep-link anchor markup so other parts of
		 * an integration can swap the default `<a>` for their own
		 * representation (button, icon, extra attributes).
		 *
		 * Hook name resolves to `pccm_edit_rule_link`; the constant is
		 * indirected so callers can reference it as a stable PHP symbol.
		 *
		 * @param string  $markup  Default `<a>` markup (already escaped).
		 * @param integer $rule_id Rule id the link targets.
		 * @param string  $label   Resolved anchor text.
		 * @param string  $url     Deep-link URL (already escaped via esc_url in the default markup).
		 */
		$filtered = apply_filters( self::FILTER_EDIT_RULE_LINK, $default, $rule_id, $resolved_label, $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant value carries the pccm_ prefix.

		return is_string( $filtered ) ? $filtered : $default;
	}
}
