// @ts-check
const { test, expect } = require( '@playwright/test' );

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
	} );
} );
