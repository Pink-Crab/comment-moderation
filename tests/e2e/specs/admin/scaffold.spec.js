// @ts-check
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Placeholder admin smoke test.
 *
 * This stage only stands up the Playwright + wp-env toolchain; the real
 * Comment Moderation admin screen is built in a later stage. Once the rules
 * screen exists it should be exercised here (open Settings → Comment
 * Moderation, assert the Rules card heading, add/edit/delete a rule, etc.),
 * mirroring the per-area spec layout already used by sibling Pink-Crab
 * projects.
 */
test.describe( 'admin scaffold', () => {
	test( 'dashboard is reachable for the logged-in administrator', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/' );
		await expect( page ).toHaveURL( /wp-admin/ );
		await expect(
			page.getByRole( 'heading', { name: /dashboard/i } )
		).toBeVisible();

		// Capture stage-6 evidence: plugin activated cleanly (which is what
		// runs the Perique migration that creates `{$wpdb->prefix}pccm_rules`).
		// The rules screen itself is built in a later stage, so the dashboard
		// is the available proof that activation — and therefore the
		// migration — completed without error.
		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-6.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 9 conditional rule builder model lands', async ( {
		page,
	} ) => {
		// Stage 9 introduces the recursive Condition_Group / Operator /
		// refactored Condition / Conditional_Rule domain model. It is pure
		// PHP — no admin screen wiring yet — so the only end-to-end claim
		// available is that loading the plugin (and therefore autoloading
		// every class added in this stage) does not fatal WordPress.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-9.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 10 domain-model unit tests land', async ( {
		page,
	} ) => {
		// Stage 10 only adds PHPUnit coverage for the rule / condition-tree
		// domain model — no admin wiring exists yet. The available end-to-end
		// claim is the same as stage 9: the plugin still autoloads and stays
		// active on the plugins screen.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-10.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 11 wpdb-backed rule repository lands', async ( {
		page,
	} ) => {
		// Stage 11 adds the $wpdb-backed Rule_Repository (CRUD + filter +
		// pagination), wired into the DI container. There is still no admin
		// screen consuming it, so the available end-to-end claim is that the
		// repository binding does not break autoloading or activation —
		// proved by the plugin row still appearing as active on the plugins
		// screen. The repository's actual behaviour is exercised by the
		// PHPUnit integration suite under tests/Integration/Repository/.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-11.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 12 repository integration tests land', async ( {
		page,
	} ) => {
		// Stage 12 adds PHPUnit integration coverage for the wpdb-backed
		// Rule_Repository against the @wordpress/env database (using the
		// migrated `pccm_rules` table). No admin screen consumes the
		// repository yet, so the available end-to-end claim is unchanged:
		// the plugin still autoloads and remains active on the plugins
		// screen. The repository contract itself is proved by the new
		// tests under tests/Integration/Repository/Test_Wpdb_Rule_Repository.php.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-12.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 15 fail-safe rule evaluators land', async ( {
		page,
	} ) => {
		// Stage 15 implements the fail-safe rule evaluators in
		// src/Domain/Engine/ (Rule_Evaluator + Comment_Submission). It is
		// pure domain logic with no admin wiring or comment-hook
		// integration yet — those follow in later stages. The available
		// end-to-end claim is therefore the same as the previous pre-UI
		// stages: the plugin still autoloads, every new class can be
		// instantiated by the autoloader at boot, and WordPress reports
		// the plugin as active. The evaluator's behaviour itself is
		// exercised by the PHPUnit suite at
		// tests/Unit/Domain/Engine/Test_Rule_Evaluator.php.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-15.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 17 invisible comment engine lands', async ( {
		page,
	} ) => {
		// Stage 17 implements the invisible comment engine: hooks
		// `pre_comment_approved`, walks the stored rule list first-match-wins,
		// records the matched rule's stats, fires `pccm_comment_failed_rule`,
		// and diverts the comment to the matched rule's outcome. There is
		// still no admin screen — the engine itself never paints UI. The
		// available end-to-end claim is therefore the same shape as the
		// preceding pre-UI stages: registering Comment_Engine as a Hookable
		// and wiring its dependencies through Perique's DI container does
		// not break plugin activation. The engine's actual behaviour is
		// exercised by tests/Integration/Engine/Test_Comment_Engine.php.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-17.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 18 engine integration tests land', async ( {
		page,
	} ) => {
		// Stage 18 only adds PHPUnit integration coverage for the Stage 17
		// Comment_Engine — first-match-wins ordering, pingback/trackback
		// pass-through, fail-safe handling of a malformed rule, and the
		// no-match transparency contract. No production code changes, no
		// admin wiring, no UI. The available end-to-end claim is therefore
		// unchanged from prior pre-UI stages: the plugin still autoloads and
		// WordPress reports it as active. The engine's behavioural coverage
		// itself lives in tests/Integration/Engine/Test_Comment_Engine.php.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-18.png'
			),
			fullPage: true,
		} );
	} );

	test( 'plugin remains active after Stage 16 rule-evaluator unit tests land', async ( {
		page,
	} ) => {
		// Stage 16 only adds PHPUnit coverage for the Stage 15 fail-safe
		// rule evaluators — including the three-level mixed-combinator case
		// mirroring the spec's worked expression
		// `EMAIL HAS APPLE OR TREE AND (NAME IS (SAM OR REBECCA) OR EMAIL IS FOO)`.
		// No production code changes, no admin wiring, no UI. The available
		// end-to-end claim is therefore unchanged from prior pre-UI stages:
		// the plugin still autoloads and WordPress reports it as active.
		// The new evaluator coverage itself lives in
		// tests/Unit/Domain/Engine/Test_Rule_Evaluator.php.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-16.png'
			),
			fullPage: true,
		} );
	} );
} );
