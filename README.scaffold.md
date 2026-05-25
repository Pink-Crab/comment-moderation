# ##PLUGIN_NAME##

##PLUGIN_DESCRIPTION##

- **Requires WordPress:** ##MIN_WP_VERSION##
- **Requires PHP:** ##MIN_PHP_VERSION##
- **License:** ##LICENSE##

Built on the [PinkCrab Perique](https://perique.info/) framework.

## Installation

```bash
composer install
npm install
npm run build
```

Activate from the WordPress admin → Plugins screen.

## Development

```bash
npm run start         # wp-scripts dev server + SCSS watcher
composer test         # PHPUnit (requires MySQL — see Tests below)
composer lint:php     # phpcs + phpstan + phpmd
composer format:php   # phpcbf auto-fixer
npm run lint          # eslint + stylelint
```

## Build

### Assets

| Command          | Output                                          |
|------------------|-------------------------------------------------|
| `npm run build`  | `assets/build/scripts/`, `assets/build/styles/` |
| `npm run start`  | Same, in watch mode                             |

Source lives under [assets/src/](assets/src/) (`scripts/`, `styles/`).

### Release (with scoped vendor)

`composer build` produces a scoped, `--no-dev` release into `dist/`. Every
PHP class, function, and constant inside `vendor/` is prefixed with
`##NAMESPACE##\Vendor`, so this plugin's bundled dependencies cannot
collide with another plugin's at runtime — even if both ship the same
library at different versions.

```bash
composer build       # writes dist/, ready to zip
```

The exclude-list for WordPress globals is generated automatically from
`php-stubs/wordpress-stubs` via [pinkcrab/php-scoper-helper](https://github.com/Pink-Crab/PHPScoper-Helper),
so it stays in sync with WordPress as you upgrade. The scoping
configuration lives in [.php-scoper.inc.php](.php-scoper.inc.php); the
build orchestration is [scripts/build.sh](scripts/build.sh).

Only **production** dependencies are scoped (the script does
`composer install --no-dev` in an isolated work directory). Dev tools —
phpunit, php-scoper itself, the linters — never leak into `dist/`.

## Tests

PHPUnit + [wp-phpunit](https://github.com/wp-phpunit/wp-phpunit). Requires a
MySQL/MariaDB instance. Local dev: copy [tests/.env_sample](tests/.env_sample) to
`tests/.env` and fill in DB credentials — the test bootstrap loads it
automatically via vlucas/phpdotenv. CI sets the same env vars in the
workflow directly.

```bash
cp tests/.env_sample tests/.env
# edit tests/.env

composer test                           # all suites (unit + integration)
composer test -- --testsuite=unit       # fast unit tests only
composer test -- --testsuite=integration
composer coverage                       # writes clover.xml
```

Tests are split into `tests/Unit/` (pure PHP, mock-free thanks to App_Config
being constructable from a plain array) and `tests/Integration/` (full WP
boot via wp-phpunit, plugin activated, Perique fully booted — exercises
the shortcode end-to-end).

## Project structure

```
##PLUGIN_SLUG##/
├── ##PLUGIN_SLUG##.php       # WordPress plugin entry — header, constants, requirement checks
├── functions.php             # Prefixed helper functions (loaded BEFORE Perique boots)
├── perique-bootstrap.php     # App_Factory()->...->boot()
├── type-defs.php             # Constant stubs for phpstan
├── config/                   # App_Config, DI rules, registration class list
├── src/
│   ├── Application/          # Services + Settings (Plugin_Config)
│   ├── Domain/               # Entities, value objects, repository interfaces
│   ├── Infrastructure/       # Repository impls, integrations
│   └── Presentation/
│       ├── Hook/             # Hookables (the default Perique middleware)
│       └── View/
│           ├── Component/    # PHP component classes
│           └── Model/        # View_Model containers
├── views/                    # Default Perique view path
│   └── components/           # Component templates (auto-resolved from class names)
├── assets/
│   ├── src/                  # JS + SCSS source
│   └── build/                # Built output (gitignored)
├── tests/                    # PHPUnit (Unit + Integration)
└── migrations/               # DB migrations (enable perique-migration module — see README)
```

## Adding Perique modules

The scaffold ships **core Perique only** (`pinkcrab/perique-framework-core`).
To add e.g. registerables, route, admin-menu, settings-page, migrations:

1. `composer require pinkcrab/<package>`.
2. Add `->module( SomeModule::class )` in [perique-bootstrap.php](perique-bootstrap.php) between `registration_classes(...)` and `boot()`.
3. For module-typed classes (Post_Type, Route_Controller, Menu_Page, etc.), add the FQCN to [config/registration.php](config/registration.php).

See the Perique docs (`perique-modules.md`) or [perique.info](https://perique.info/)
for the catalogue.

## License

##LICENSE##. See [LICENSE](LICENSE).
