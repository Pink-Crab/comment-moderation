# Perique Plugin Scaffold

Opinionated starter template for WordPress plugins built on the
[PinkCrab Perique](https://perique.info/) framework (v2.1.\*).

> This README is the scaffold's own. After you run `scripts/scaffold-init.php`
> it is deleted, and `README.scaffold.md` is renamed in its place as your new
> plugin's README.

## What you get

| Area              | What's wired up                                                                                                  |
|-------------------|------------------------------------------------------------------------------------------------------------------|
| Bootstrap         | `App_Factory` wiring in `perique-bootstrap.php`, pre-autoload requirement checks inline in the main plugin file. |
| Architecture      | Clean-layered `src/Application`, `src/Domain`, `src/Infrastructure`, `src/Presentation`.                         |
| Example feature   | `[pinkcrab_comment_moderation_hello]` shortcode → Hookable → Component → template. Exercises DI, View, and asset pipeline. |
| Configs           | `config/{settings,di,registration}.php` triplet.                                                                 |
| Quality           | `team51-configs` extension for phpstan, phpcs, phpmd. `type-defs.php` constant stubs for static analysis.        |
| Tests             | PHPUnit + WP-PHPUnit bootstrap, one passing example unit test with `@testdox`.                                    |
| Build             | `wp-scripts` for JS, PostCSS + SCSS for styles, output to `assets/build/`.                                       |
| CI                | Three GitHub workflows (PHP quality, PHP tests across PHP/WP matrix, JS build).                                  |
| Core Perique only | `pinkcrab/perique-framework-core: ^2.1`. Add other modules per-project.                                          |

## How the scaffold becomes your plugin

The scaffold ships with `##NAME##`-style placeholders throughout the tree.
`scripts/scaffold-init.php` reads `.scaffold/manifest.json`, gathers values,
walks the tree replacing every `##TOKEN##`, applies the renames, then deletes
itself + `.scaffold/`.

### Interactive

```bash
git clone git@github.com:Pink-Crab/plugin-scaffold.git my-plugin
cd my-plugin
rm -rf .git && git init
php scripts/scaffold-init.php
```

The script prompts for each placeholder, validates against the manifest's
regex rules, and shows derived defaults you can accept or override.

### Non-interactive (agent / CI)

```bash
git clone git@github.com:Pink-Crab/plugin-scaffold.git my-plugin
cd my-plugin
rm -rf .git && git init
# Write answers.json based on .scaffold/manifest.json (see .scaffold/example-answers.json).
php scripts/scaffold-init.php --answers=answers.json
```

`.scaffold/example-answers.json` is committed as a worked example — copy it,
edit, point `--answers=` at it.

### After init

```bash
composer install
npm install
npm run build
```

Activate the plugin in WordPress. Visit any page with
`[pinkcrab_comment_moderation_hello name="…"]` in its content to confirm the full
pipeline (shortcode → Hookable → Component → template + script + style)
works end-to-end.

## Pre-init IDE warnings

Because the scaffold uses `##TOKEN##` placeholders inside PHP **identifiers**
(function names, namespaces, constants), the templated PHP files do **not**
parse before `scripts/scaffold-init.php` runs. Errors like "syntax error,
unexpected token" in the IDE are expected pre-init and clear automatically
once token substitution has happened. The setup script itself uses
`// phpcs:ignoreFile` for the same reason.

## Placeholders

Defined in [.scaffold/manifest.json](.scaffold/manifest.json). See the
manifest's inline `label` / `description` / `example` / `validate` fields.

Required: `PinkCrab Comment Moderation`, `pinkcrab-comment-moderation`, `Rule-based comment moderation engine for WordPress.`,
`PinkCrab\Comment_Moderation`, `pinkcrab/comment-moderation`, `PinkCrab`.

Auto-derived (no prompt): `PinkCrab\\Comment_Moderation` (composer.json's `\\`
form), `2026` (current year).

Defaulted from another token: `pinkcrab-comment-moderation` (kebab of name),
`pinkcrab-comment-moderation` (= slug), `pinkcrab_comment_moderation_` (slug snake_case + `_`),
`PINKCRAB_COMMENT_MODERATION_` (function prefix uppercased), `pinkcrab-comment-moderation/v1`
(slug + `/v1`).

## License

GPL-2.0-or-later.
