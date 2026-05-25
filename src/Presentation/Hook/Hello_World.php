<?php
/**
 * Hello_World — example Hookable.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Presentation\Hook
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Presentation\Hook;

use PinkCrab\Loader\Hook_Loader;
use PinkCrab\Perique\Interfaces\Hookable;
use PinkCrab\Perique\Services\View\View;
use Webmozart\Assert\Assert;
use PinkCrab\Comment_Moderation\Application\Settings\Plugin_Config;
use PinkCrab\Comment_Moderation\Presentation\View\Component\Hello_World_Component;

/**
 * The one example feature: a `[pinkcrab_comment_moderation_hello name="…"]` shortcode
 * that renders the Hello_World_Component template, plus matching front-end
 * script + style enqueueing.
 *
 * Demonstrates, in one class:
 *   - Implementing PinkCrab\Perique\Interfaces\Hookable (the core
 *     middleware contract — no module needed).
 *   - DI of pre-registered services (View) and project-owned classes
 *     (Plugin_Config, which itself wraps App_Config).
 *   - Hook_Loader's `shortcode()` and `front_action()` helpers.
 *   - Rendering a Component via the injected View service (never the static
 *     App::view() facade).
 *
 * Registered through config/registration.php — see there for how to add
 * more Hookables.
 */
final class Hello_World implements Hookable {

	private const SHORTCODE_TAG = 'pinkcrab_comment_moderation_hello';
	private const ASSET_HANDLE  = 'pinkcrab-comment-moderation-hello-world';

	/**
	 * Construct the Hookable with its injected dependencies.
	 *
	 * @param View          $view   Injected view service for rendering Components.
	 * @param Plugin_Config $config Injected wrapper around App_Config.
	 */
	public function __construct(
		private View $view,
		private Plugin_Config $config
	) {}

	/**
	 * Register WP hooks via the loader. Called by Perique on `init`.
	 *
	 * @param Hook_Loader $loader Perique hook loader.
	 *
	 * @return void
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->shortcode( self::SHORTCODE_TAG, array( $this, 'render_shortcode' ) );
		$loader->front_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Shortcode callback. Builds a Component and renders it via the
	 * injected View service. Returns the markup so WordPress can splice it
	 * into the surrounding content.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes (WP passes '' when there are none).
	 *
	 * @return string
	 */
	public function render_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'name' => 'World' ),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE_TAG
		);

		// Validate via the bundled, scoped third-party — proves the build's
		// scoper rewrote `Webmozart\Assert\Assert` to
		// `PinkCrab\Comment_Moderation\Vendor\Webmozart\Assert\Assert` in dist/ without
		// breaking anything here in src/.
		Assert::string( $atts['name'] );

		$component = new Hello_World_Component( $atts['name'] );

		return (string) $this->view->component( $component, View::RETURN_VIEW );
	}

	/**
	 * Enqueue the built script + style for the shortcode. The asset
	 * paths/urls come from Plugin_Config which reads them from App_Config
	 * (configured in config/settings.php as `path.assets` / `url.assets`,
	 * pointing at `assets/build/`).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_script(
			self::ASSET_HANDLE,
			$this->config->asset_url( 'scripts/hello-world.js' ),
			array(),
			$this->config->version(),
			true
		);
		wp_enqueue_style(
			self::ASSET_HANDLE,
			$this->config->asset_url( 'styles/hello-world.css' ),
			array(),
			$this->config->version()
		);
	}
}
