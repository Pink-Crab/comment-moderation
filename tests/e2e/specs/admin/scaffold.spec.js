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
} );
