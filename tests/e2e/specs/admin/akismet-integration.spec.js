// @ts-check
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Stage 24 — Akismet co-operation smoke.
 *
 * The Akismet co-operation (rebuild spec §10) is intentionally invisible
 * to the administrator: it writes a history note into Akismet's own
 * comment log and reports diverted comments to Akismet's training service.
 * There is no Comment Moderation UI for it — and the unit test in
 * `tests/Unit/Application/Integration/Akismet/Test_Akismet_Integration.php`
 * already exercises every branch of the dispatch table (Akismet-absent,
 * integrator-disabled, Spam/Trash/Pending/Approved outcomes, message
 * format).
 *
 * This Playwright spec is the no-regression check the same flow other
 * stages run: load the Settings → Comment Moderation screen with the new
 * Akismet_Integration registered, confirm the Elm app still mounts and
 * the rules card still renders, then capture the screenshot the routine
 * commits for the stage. If wiring the listener through DI broke
 * registration, this would page-error before the heading appears.
 */
test.describe( 'comment moderation admin — Akismet integration (stage 24)', () => {
	test( 'Stage 24: admin screen still renders cleanly with the Akismet listener registered', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);

		// WordPress wrapper still renders the page title — proves the Settings
		// → Comment Moderation menu entry survived the registration changes.
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();

		// The Elm app still mounts onto its root node, proving the bootstrap
		// data localised by Admin_Page is intact and the new
		// Akismet_Integration Hookable did not break Perique registration.
		await expect(
			page.locator( 'div#pccm-admin-root.pccm-app' )
		).toHaveCount( 1 );

		// Rules card heading is what the spec calls "the heading 'Rules'"
		// — still rendered by Elm.
		await expect(
			page.getByRole( 'heading', { name: 'Rules', level: 2 } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-24.png'
			),
			fullPage: true,
		} );
	} );
} );
