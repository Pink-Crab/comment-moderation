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

/**
 * Lightweight value object that wraps the shared App_Config instance.
 *
 * Inject this in your own classes instead of `App_Config` directly when you
 * want method names that read in your domain's language — e.g.
 * `$config->asset_url( 'scripts/hello-world.js' )` rather than
 * `$app_config->asset_url() . '/scripts/hello-world.js'`. The wrapper also
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

	public function __construct(
		private App_Config $app_config
	) {}

	/**
	 * Plugin version (from `config/settings.php` → `plugin.version`).
	 */
	public function version(): string {
		return $this->app_config->version();
	}

	/**
	 * Translation text domain. Constant — App_Config does not store this.
	 */
	public function text_domain(): string {
		return self::TEXT_DOMAIN;
	}

	/**
	 * REST API namespace (from `config/settings.php` → `namespaces.rest`).
	 */
	public function rest_namespace(): string {
		return (string) $this->app_config->rest();
	}

	/**
	 * Absolute URL to a file inside `assets/build/`.
	 *
	 * @param string $relative Optional path appended to the assets base URL.
	 *                         Leading slashes are stripped.
	 */
	public function asset_url( string $relative = '' ): string {
		$base = (string) $this->app_config->url( 'assets' );
		if ( '' === $relative ) {
			return $base;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Absolute filesystem path to a file inside `assets/build/`.
	 *
	 * @param string $relative Optional path appended to the assets base path.
	 */
	public function asset_path( string $relative = '' ): string {
		$base = (string) $this->app_config->path( 'assets' );
		if ( '' === $relative ) {
			return $base;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Absolute filesystem path to the views directory.
	 */
	public function view_path(): string {
		return (string) $this->app_config->path( 'view' );
	}
}
