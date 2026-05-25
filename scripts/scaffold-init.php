<?php
/**
 * Perique Plugin Scaffold — Initialisation Script.
 *
 * Reads .scaffold/manifest.json, gathers placeholder values (interactively
 * via STDIN or non-interactively via --answers=path.json), walks the tree
 * replacing every ##TOKEN## occurrence, applies the manifest's renames,
 * then deletes the scaffold-only files.
 *
 * Uses only PHP builtins so it can run BEFORE `composer install`.
 *
 * Usage:
 *   php scripts/scaffold-init.php                          # interactive
 *   php scripts/scaffold-init.php --answers=answers.json   # non-interactive (agent mode)
 *   php scripts/scaffold-init.php --dry-run                # show plan, write nothing
 *   php scripts/scaffold-init.php --keep-scaffold          # skip post_init.delete
 *   php scripts/scaffold-init.php --force                  # bypass already-initialised check
 *
 * @package PeriquePluginScaffold
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

// phpcs:ignoreFile
//
// Why: this file is a one-shot CLI bootstrap that runs BEFORE composer install
// and is DELETED by .scaffold/manifest.json's post_init step. It never ships
// into the generated plugin, never runs in a WordPress request, and never goes
// through the project's WordPress-Extra phpcs ruleset in production. The whole
// file is therefore intentionally exempt from the project coding standard.

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "scaffold-init.php must be run from the command line.\n" );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Constants & paths.
// ---------------------------------------------------------------------------

const ROOT          = __DIR__ . '/..';
const MANIFEST_PATH = ROOT . '/.scaffold/manifest.json';

/** Directories never walked during token replacement. */
const SKIP_DIRS = array(
	'.git',
	'.scaffold',
	'vendor',
	'node_modules',
	'wordpress',
	'assets/build',
);

/** Binary file extensions never opened for token replacement. */
const BINARY_EXTS = array(
	'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp', 'svg',
	'woff', 'woff2', 'ttf', 'eot', 'otf',
	'pdf', 'zip', 'tar', 'gz', 'tgz', 'bz2', '7z',
	'mo', 'pot',
	'lock',
	'phar',
	'mp3', 'mp4', 'wav', 'ogg', 'webm',
);

// ---------------------------------------------------------------------------
// Argv parsing.
// ---------------------------------------------------------------------------

$options = parse_argv( $argv );

if ( isset( $options['help'] ) || isset( $options['h'] ) ) {
	print_help();
	exit( 0 );
}

$is_dry_run       = (bool) ( $options['dry-run']       ?? false );
$is_keep_scaffold = (bool) ( $options['keep-scaffold'] ?? false );
$is_force         = (bool) ( $options['force']         ?? false );
$answers_path     = $options['answers'] ?? null;

// ---------------------------------------------------------------------------
// Manifest load.
// ---------------------------------------------------------------------------

if ( ! is_file( MANIFEST_PATH ) ) {
	if ( ! $is_force ) {
		fwrite(
			STDERR,
			"ERROR: .scaffold/manifest.json not found.\n" .
			"      Either this scaffold has already been initialised, or you're running\n" .
			"      from the wrong directory. Pass --force to re-run anyway.\n"
		);
		exit( 1 );
	}
	fwrite( STDERR, "WARNING: manifest missing but --force given. Nothing to do.\n" );
	exit( 0 );
}

$manifest = json_decode( (string) file_get_contents( MANIFEST_PATH ), true );
if ( ! is_array( $manifest ) ) {
	fwrite( STDERR, "ERROR: .scaffold/manifest.json is not valid JSON.\n" );
	exit( 1 );
}
if ( empty( $manifest['placeholders'] ) || ! is_array( $manifest['placeholders'] ) ) {
	fwrite( STDERR, "ERROR: manifest has no 'placeholders' array.\n" );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Gather raw answers.
// ---------------------------------------------------------------------------

$preset_answers = array();
if ( $answers_path !== null ) {
	if ( ! is_file( $answers_path ) ) {
		fwrite( STDERR, "ERROR: --answers file not found: {$answers_path}\n" );
		exit( 1 );
	}
	$decoded = json_decode( (string) file_get_contents( $answers_path ), true );
	if ( ! is_array( $decoded ) ) {
		fwrite( STDERR, "ERROR: --answers file is not a JSON object.\n" );
		exit( 1 );
	}
	$preset_answers = $decoded;
}

$is_interactive = ( $answers_path === null );

echo "\n";
echo "Perique Plugin Scaffold — Initialisation\n";
echo "----------------------------------------\n";
echo $is_interactive ? "Interactive mode.\n" : "Non-interactive mode (answers from {$answers_path}).\n";
echo $is_dry_run ? "DRY RUN — no files will be modified.\n" : '';
echo "\n";

$resolved = array();

foreach ( $manifest['placeholders'] as $spec ) {
	$token = $spec['token'] ?? null;
	if ( ! is_string( $token ) || $token === '' ) {
		fwrite( STDERR, "ERROR: placeholder entry missing 'token' field.\n" );
		exit( 1 );
	}

	// Skip placeholders that are themselves derived from another — they're filled in after the prompt loop.
	if ( isset( $spec['auto'] ) ) {
		$resolved[ $token ] = compute_auto( (string) $spec['auto'] );
		continue;
	}

	// Compute proposed default (may be empty).
	$default = compute_default( $spec, $resolved );

	if ( ! $is_interactive ) {
		$value = (string) ( $preset_answers[ $token ] ?? $default );
	} else {
		$value = prompt_for( $spec, $default );
	}

	// Validate.
	if ( ! validate_value( $spec, $value ) ) {
		if ( $is_interactive ) {
			// Re-prompt once.
			fwrite( STDERR, "Invalid value for {$token}. Try again.\n" );
			$value = prompt_for( $spec, $default );
			if ( ! validate_value( $spec, $value ) ) {
				fwrite( STDERR, "ERROR: invalid value for {$token} after retry. Aborting.\n" );
				exit( 1 );
			}
		} else {
			fwrite( STDERR, "ERROR: invalid value for {$token}: '{$value}'. Check --answers file.\n" );
			exit( 1 );
		}
	}

	// Required check (after defaulting + validation).
	if ( ! empty( $spec['required'] ) && trim( $value ) === '' ) {
		fwrite( STDERR, "ERROR: {$token} is required but no value was supplied.\n" );
		exit( 1 );
	}

	$resolved[ $token ] = $value;

	// Compute derived tokens declared on this placeholder.
	if ( ! empty( $spec['derived'] ) && is_array( $spec['derived'] ) ) {
		foreach ( $spec['derived'] as $derived_token => $derive_op ) {
			$resolved[ $derived_token ] = apply_derive( (string) $derive_op, $value );
		}
	}
}

// ---------------------------------------------------------------------------
// Plan summary.
// ---------------------------------------------------------------------------

echo "Resolved placeholders:\n";
foreach ( $resolved as $token => $value ) {
	$display = $value === '' ? '(empty)' : $value;
	printf( "  %-30s %s\n", $token, $display );
}
echo "\n";

// ---------------------------------------------------------------------------
// Walk + replace.
// ---------------------------------------------------------------------------

$root_real = realpath( ROOT );
if ( $root_real === false ) {
	fwrite( STDERR, "ERROR: could not resolve scaffold root path.\n" );
	exit( 1 );
}

$replacements_made = 0;
$files_touched     = 0;
$files_seen        = 0;

$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root_real, RecursiveDirectoryIterator::SKIP_DOTS ),
		static function ( $current, $key, $iterator ) use ( $root_real ): bool {
			$relative = ltrim( str_replace( $root_real, '', (string) $current->getPathname() ), DIRECTORY_SEPARATOR );
			$normalised = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
			foreach ( SKIP_DIRS as $skip ) {
				if ( $normalised === $skip || str_starts_with( $normalised, $skip . '/' ) ) {
					return false;
				}
			}
			return true;
		}
	)
);

/** @var SplFileInfo $file */
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}
	++$files_seen;

	$ext = strtolower( $file->getExtension() );
	if ( in_array( $ext, BINARY_EXTS, true ) ) {
		continue;
	}

	$path     = $file->getPathname();
	$contents = file_get_contents( $path );
	if ( $contents === false ) {
		continue;
	}
	if ( looks_binary( $contents ) ) {
		continue;
	}

	$new_contents = replace_tokens( $contents, $resolved );
	if ( $new_contents === $contents ) {
		continue;
	}

	$diff_count = substr_count( $contents, '##' ) - substr_count( $new_contents, '##' );
	$replacements_made += max( 0, $diff_count );
	++$files_touched;

	if ( $is_dry_run ) {
		echo "  [would rewrite] " . relative_path( $path, $root_real ) . "\n";
		continue;
	}

	if ( file_put_contents( $path, $new_contents ) === false ) {
		fwrite( STDERR, "ERROR: failed to write {$path}\n" );
		exit( 1 );
	}
}

echo "\n";
echo "Token replacement:\n";
echo "  files scanned : {$files_seen}\n";
echo "  files updated : {$files_touched}\n";
echo "  tokens replaced (approx) : {$replacements_made}\n";
echo "\n";

// ---------------------------------------------------------------------------
// Renames.
// ---------------------------------------------------------------------------

if ( ! empty( $manifest['renames'] ) && is_array( $manifest['renames'] ) ) {
	echo "Renames:\n";
	foreach ( $manifest['renames'] as $rename ) {
		$from_template = (string) ( $rename['from'] ?? '' );
		$to_template   = (string) ( $rename['to']   ?? '' );
		if ( $from_template === '' || $to_template === '' ) {
			continue;
		}
		// `from` is the LITERAL filesystem path — the file is named with the
		// placeholder still present (e.g. `##PLUGIN_SLUG##.php` on disk).
		// `to` is templated and gets resolved to the final filename.
		$from = $root_real . '/' . $from_template;
		$to   = $root_real . '/' . replace_tokens( $to_template, $resolved );

		if ( $from === $to ) {
			echo "  (no-op) {$from_template}\n";
			continue;
		}
		if ( ! file_exists( $from ) ) {
			fwrite( STDERR, "WARNING: rename source not found: {$from}\n" );
			continue;
		}
		echo "  " . relative_path( $from, $root_real ) . " → " . relative_path( $to, $root_real ) . "\n";
		if ( ! $is_dry_run && ! rename( $from, $to ) ) {
			fwrite( STDERR, "ERROR: rename failed.\n" );
			exit( 1 );
		}
	}
	echo "\n";
}

// ---------------------------------------------------------------------------
// Post-init: delete + rename.
// ---------------------------------------------------------------------------

if ( ! $is_keep_scaffold && ! empty( $manifest['post_init'] ) && is_array( $manifest['post_init'] ) ) {
	$post = $manifest['post_init'];

	// Order matters: DELETE first, then RENAME. If we renamed first, the
	// rename's destination (e.g. README.md) would collide with a file that's
	// in the delete list (the scaffold's old README.md) — the rename would
	// overwrite it on Linux/Unix, but then the delete step would remove the
	// freshly-renamed-into-place file. Deleting first avoids both the
	// collision and the order-dependent bug.
	if ( ! empty( $post['delete'] ) && is_array( $post['delete'] ) ) {
		echo "Post-init deletes:\n";
		foreach ( $post['delete'] as $target ) {
			$target_path = $root_real . '/' . (string) $target;
			if ( ! file_exists( $target_path ) ) {
				continue;
			}
			echo "  " . relative_path( $target_path, $root_real ) . "\n";
			if ( ! $is_dry_run ) {
				delete_recursive( $target_path );
			}
		}
		echo "\n";
	}

	if ( ! empty( $post['rename'] ) && is_array( $post['rename'] ) ) {
		echo "Post-init renames:\n";
		foreach ( $post['rename'] as $rename ) {
			$from = $root_real . '/' . (string) ( $rename['from'] ?? '' );
			$to   = $root_real . '/' . (string) ( $rename['to']   ?? '' );
			if ( ! file_exists( $from ) ) {
				fwrite( STDERR, "WARNING: post-init rename source missing: {$from}\n" );
				continue;
			}
			echo "  " . relative_path( $from, $root_real ) . " → " . relative_path( $to, $root_real ) . "\n";
			if ( ! $is_dry_run && ! rename( $from, $to ) ) {
				fwrite( STDERR, "ERROR: post-init rename failed.\n" );
				exit( 1 );
			}
		}
		echo "\n";
	}
}

// ---------------------------------------------------------------------------
// Auto-commit the initialisation (unless --no-commit, or there's no git repo).
// ---------------------------------------------------------------------------
//
// Why this is on by default: composer build (scripts/build.sh) snapshots
// the plugin with `git archive HEAD`, which only sees committed files. If
// scaffold-init runs but the substitutions are never committed, every later
// build snapshots the un-initialised scaffold and fails. Auto-committing here
// closes that gap so the agent flow is: clone → init → install → build (with
// no separate "remember to commit" step in the middle).
//
// Skip with --no-commit if you prefer to commit manually.

$is_no_commit = (bool) ( $options['no-commit'] ?? false );

if ( ! $is_dry_run && ! $is_no_commit && is_dir( ROOT . '/.git' ) ) {
	$prev_cwd = getcwd();
	chdir( ROOT );

	exec( 'git add -A 2>&1', $add_output, $add_exit );
	if ( 0 !== $add_exit ) {
		fwrite( STDERR, "WARNING: `git add -A` failed; skipping auto-commit:\n" );
		fwrite( STDERR, '  ' . implode( "\n  ", $add_output ) . "\n" );
	} else {
		exec( 'git diff --cached --quiet', $_, $diff_exit );
		if ( 0 === $diff_exit ) {
			echo "(No changes to commit.)\n\n";
		} else {
			echo "Committing initialisation:\n";
			exec( 'git commit -m "chore: initialize plugin from scaffold" 2>&1', $commit_output, $commit_exit );
			if ( 0 === $commit_exit ) {
				echo '  ' . implode( "\n  ", $commit_output ) . "\n\n";
			} else {
				fwrite( STDERR, "WARNING: `git commit` failed:\n" );
				fwrite( STDERR, '  ' . implode( "\n  ", $commit_output ) . "\n" );
				fwrite( STDERR, "         You must commit manually before `composer build` works,\n" );
				fwrite( STDERR, "         or re-run with --no-commit and handle commits yourself.\n" );
				fwrite( STDERR, "         (Common cause: git user.name / user.email not configured.)\n\n" );
			}
		}
	}

	chdir( $prev_cwd );
}

// ---------------------------------------------------------------------------
// Done.
// ---------------------------------------------------------------------------

if ( $is_dry_run ) {
	echo "Dry run complete — no files written.\n";
} else {
	echo "Scaffold initialised. Next steps:\n";
	echo "  1. composer install\n";
	echo "  2. npm install\n";
	echo "  3. npm run build\n";
	echo "  4. Activate the plugin in WordPress and visit a page with [" . ( $resolved['##FUNCTION_PREFIX##'] ?? 'plugin_prefix_' ) . "hello name=\"You\"]\n";
}

exit( 0 );

// ===========================================================================
// Helpers.
// ===========================================================================

/**
 * Parse a CLI argv array into a flat associative options array.
 * Supports: --flag, --key=value.
 *
 * @param array<int,string> $argv
 * @return array<string,string|bool>
 */
function parse_argv( array $argv ): array {
	$out = array();
	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( ! str_starts_with( $arg, '--' ) ) {
			continue;
		}
		$pair = substr( $arg, 2 );
		if ( str_contains( $pair, '=' ) ) {
			[ $k, $v ] = explode( '=', $pair, 2 );
			$out[ $k ] = $v;
		} else {
			$out[ $pair ] = true;
		}
	}
	return $out;
}

function print_help(): void {
	echo "Perique Plugin Scaffold — Initialisation\n";
	echo "\n";
	echo "Usage:\n";
	echo "  php scripts/scaffold-init.php [--answers=PATH] [--dry-run] [--keep-scaffold] [--force] [--no-commit]\n";
	echo "\n";
	echo "Flags:\n";
	echo "  --answers=PATH    Read placeholder values from a JSON file (non-interactive).\n";
	echo "  --dry-run         Print the plan; write nothing.\n";
	echo "  --keep-scaffold   Skip post_init.delete (keeps .scaffold/, scripts/, etc.).\n";
	echo "  --force           Run even if .scaffold/manifest.json is missing.\n";
	echo "  --no-commit       Skip the auto-commit at the end (default is to commit\n";
	echo "                    the initialisation as `chore: initialize plugin from\n";
	echo "                    scaffold` so `composer build` works without a manual\n";
	echo "                    commit in between).\n";
	echo "  --help            Show this message.\n";
}

/**
 * Compute the default value for a placeholder before prompting.
 * Order of precedence: default_from (with optional transform/suffix) → default → '' (empty).
 *
 * @param array<string,mixed>  $spec
 * @param array<string,string> $resolved Already-resolved tokens.
 */
function compute_default( array $spec, array $resolved ): string {
	if ( isset( $spec['default_from'] ) && is_array( $spec['default_from'] ) ) {
		$source_token = (string) ( $spec['default_from']['token'] ?? '' );
		$source       = $resolved[ $source_token ] ?? '';
		$transform    = (string) ( $spec['default_from']['transform'] ?? '' );
		$suffix       = (string) ( $spec['default_from']['suffix'] ?? '' );
		$value        = apply_transform( $transform, $source );
		return $value . $suffix;
	}
	if ( isset( $spec['default'] ) ) {
		return (string) $spec['default'];
	}
	return '';
}

/**
 * Apply one of the known string transforms.
 *
 * Supported: kebab, snake_underscore, upper, lower, pascal.
 */
function apply_transform( string $transform, string $value ): string {
	if ( $value === '' || $transform === '' ) {
		return $value;
	}
	switch ( $transform ) {
		case 'kebab':
			// "My Awesome Plugin" → "my-awesome-plugin"
			$out = strtolower( $value );
			$out = (string) preg_replace( '/[^a-z0-9]+/', '-', $out );
			$out = trim( $out, '-' );
			return $out;
		case 'snake_underscore':
			// "my-awesome-plugin" → "my_awesome_plugin_"
			$out = strtolower( $value );
			$out = (string) preg_replace( '/[^a-z0-9]+/', '_', $out );
			$out = trim( $out, '_' );
			return $out === '' ? '' : $out . '_';
		case 'upper':
			return strtoupper( $value );
		case 'lower':
			return strtolower( $value );
		case 'pascal':
			// "my-awesome-plugin" → "MyAwesomePlugin"
			$parts = preg_split( '/[^A-Za-z0-9]+/', $value ) ?: array();
			return implode( '', array_map( static fn( string $p ): string => ucfirst( strtolower( $p ) ), $parts ) );
		default:
			fwrite( STDERR, "WARNING: unknown transform '{$transform}'.\n" );
			return $value;
	}
}

/**
 * Apply a `derived` operation (recorded under a token's `derived` array).
 *
 * Supported: json_escape (doubles backslashes — for composer.json).
 */
function apply_derive( string $op, string $value ): string {
	switch ( $op ) {
		case 'json_escape':
			return str_replace( '\\', '\\\\', $value );
		default:
			fwrite( STDERR, "WARNING: unknown derive op '{$op}'.\n" );
			return $value;
	}
}

/** Compute an `auto` value (no prompt). */
function compute_auto( string $kind ): string {
	switch ( $kind ) {
		case 'current_year':
			return gmdate( 'Y' );
		default:
			fwrite( STDERR, "WARNING: unknown auto kind '{$kind}'.\n" );
			return '';
	}
}

/**
 * Prompt the user for one placeholder value.
 *
 * @param array<string,mixed> $spec
 */
function prompt_for( array $spec, string $default ): string {
	$label    = (string) ( $spec['label'] ?? $spec['token'] );
	$descr    = (string) ( $spec['description'] ?? '' );
	$example  = (string) ( $spec['example'] ?? '' );
	$required = ! empty( $spec['required'] );

	echo "\n";
	echo "{$label}";
	if ( $required ) {
		echo " (required)";
	}
	echo "\n";
	if ( $descr !== '' ) {
		echo "  {$descr}\n";
	}
	if ( $example !== '' ) {
		echo "  e.g. {$example}\n";
	}

	$prompt = $default === '' ? '> ' : "[{$default}] > ";
	echo $prompt;

	$line = fgets( STDIN );
	if ( $line === false ) {
		return $default;
	}
	$line = trim( $line );
	return $line === '' ? $default : $line;
}

/**
 * Validate a value against the placeholder's `validate` regex (if any).
 *
 * @param array<string,mixed> $spec
 */
function validate_value( array $spec, string $value ): bool {
	$pattern = (string) ( $spec['validate'] ?? '' );
	if ( $pattern === '' ) {
		return true;
	}
	if ( $value === '' && empty( $spec['required'] ) ) {
		return true;
	}
	$delimited = '#' . str_replace( '#', '\#', $pattern ) . '#';
	return (bool) preg_match( $delimited, $value );
}

/**
 * Replace every ##TOKEN## occurrence in a string with its resolved value.
 *
 * @param array<string,string> $resolved
 */
function replace_tokens( string $contents, array $resolved ): string {
	return str_replace( array_keys( $resolved ), array_values( $resolved ), $contents );
}

/**
 * Cheap binary-file sniff — true if the first 8KB contains a null byte.
 */
function looks_binary( string $contents ): bool {
	$head = substr( $contents, 0, 8192 );
	return str_contains( $head, "\0" );
}

/** Convert an absolute path into a project-relative path for display. */
function relative_path( string $path, string $root ): string {
	$norm_path = str_replace( DIRECTORY_SEPARATOR, '/', $path );
	$norm_root = str_replace( DIRECTORY_SEPARATOR, '/', $root );
	if ( str_starts_with( $norm_path, $norm_root . '/' ) ) {
		return substr( $norm_path, strlen( $norm_root ) + 1 );
	}
	return $norm_path;
}

/** Recursively delete a file or directory. */
function delete_recursive( string $path ): bool {
	if ( is_link( $path ) || is_file( $path ) ) {
		return unlink( $path );
	}
	if ( ! is_dir( $path ) ) {
		return true;
	}
	$entries = scandir( $path );
	if ( $entries === false ) {
		return false;
	}
	foreach ( $entries as $entry ) {
		if ( $entry === '.' || $entry === '..' ) {
			continue;
		}
		delete_recursive( $path . DIRECTORY_SEPARATOR . $entry );
	}
	return rmdir( $path );
}
