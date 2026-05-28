<?php
/**
 * Plugin_Config — typed accessor over Perique's App_Config.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Application\Settings
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Application\Settings;

use PinkCrab\Perique\Application\App_Config;
use Webmozart\Assert\Assert;

/**
 * Lightweight value object that wraps the shared App_Config instance.
 *
 * Inject this in your own classes instead of `App_Config` directly when you
 * want method names that read in your domain's language — e.g.
 * `$config->asset_url( 'scripts/admin.js' )` rather than
 * `$app_config->asset_url() . '/scripts/admin.js'`. The wrapper also
 * gives you a single place to add caching or memoisation later.
 *
 * Perique auto-wires `App_Config` (it's pre-registered for DI), so this
 * class also resolves with no rules in `config/di.php`.
 */
final class Plugin_Config {

	/**
	 * The plugin's text domain. Kept as a class constant so it's
	 * accessible without instantiating, and so phpstan can prove the value
	 * is always a string literal.
	 */
	public const TEXT_DOMAIN = 'pinkcrab-comment-moderation';

	/**
	 * Construct the typed accessor over the shared App_Config.
	 *
	 * @param App_Config $app_config The framework's shared config service.
	 */
	public function __construct(
		private App_Config $app_config
	) {}

	/**
	 * Plugin version (from `config/settings.php` → `plugin.version`).
	 *
	 * @return string
	 */
	public function version(): string {
		return $this->app_config->version();
	}

	/**
	 * Translation text domain. Constant — App_Config does not store this.
	 *
	 * @return string
	 */
	public function text_domain(): string {
		return self::TEXT_DOMAIN;
	}

	/**
	 * REST API namespace (from `config/settings.php` → `namespaces.rest`).
	 *
	 * @return string
	 */
	public function rest_namespace(): string {
		return $this->app_config->rest();
	}

	/**
	 * Absolute URL to a file inside the plugin directory (the value behind
	 * `url.plugin` in `config/settings.php`).
	 *
	 * @param string $relative Optional path appended to the plugin base URL.
	 *                         Leading slashes are stripped.
	 *
	 * @return string
	 */
	public function plugin_url( string $relative = '' ): string {
		$base = $this->app_config->url( 'plugin' );
		Assert::string( $base, 'App_Config "plugin" url must be configured as a string in config/settings.php.' );
		if ( '' === $relative ) {
			return $base;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Absolute filesystem path to a file inside the plugin directory (the
	 * value behind `path.plugin` in `config/settings.php`). Symmetrical with
	 * {@see self::plugin_url()} — used by code that needs a directory path
	 * (e.g. `wp_set_script_translations()` pointing at `languages/`) rather
	 * than a URL.
	 *
	 * @param string $relative Optional path appended to the plugin base path.
	 *                         Leading slashes are stripped.
	 *
	 * @return string
	 */
	public function plugin_path( string $relative = '' ): string {
		$base = $this->app_config->path( 'plugin' );
		Assert::string( $base, 'App_Config "plugin" path must be configured as a string in config/settings.php.' );
		if ( '' === $relative ) {
			return $base;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Absolute URL to a file inside `assets/build/`.
	 *
	 * @param string $relative Optional path appended to the assets base URL.
	 *                         Leading slashes are stripped.
	 *
	 * @return string
	 */
	public function asset_url( string $relative = '' ): string {
		$base = $this->app_config->url( 'assets' );
		Assert::string( $base, 'App_Config "assets" url must be configured as a string in config/settings.php.' );
		if ( '' === $relative ) {
			return $base;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Absolute filesystem path to a file inside `assets/build/`.
	 *
	 * @param string $relative Optional path appended to the assets base path.
	 *
	 * @return string
	 */
	public function asset_path( string $relative = '' ): string {
		$base = $this->app_config->path( 'assets' );
		Assert::string( $base, 'App_Config "assets" path must be configured as a string in config/settings.php.' );
		if ( '' === $relative ) {
			return $base;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Absolute filesystem path to the views directory.
	 *
	 * @return string
	 */
	public function view_path(): string {
		$view = $this->app_config->path( 'view' );
		Assert::string( $view, 'App_Config "view" path must be configured as a string in config/settings.php.' );
		return $view;
	}
}
