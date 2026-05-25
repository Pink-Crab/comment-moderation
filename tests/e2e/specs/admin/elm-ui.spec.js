// @ts-check
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Stage 22 — Elm admin UI smoke + flow test.
 *
 * Confirms the Elm app actually mounts onto `#pccm-admin-root` (Stage 19
 * already proved the PHP shell renders the empty mount node and bootstrap
 * data), renders the Stitch dashboard's hard-coded surfaces (the Rules
 * heading, the four rule-type dropdown options with no CIDR, the Add Rule
 * and Clear All Rules controls, the "Showing X of Y" counter and the
 * filter-toggle button), then drives an end-to-end create-flow through the
 * Regex form so we know the editor wiring + AJAX round-trip + list reload
 * all work in the live wp-env.
 *
 * The screenshot at the end is the artifact the routine commits for the
 * stage — the primary changed screen (list view with one freshly-created
 * rule).
 */
test.describe( 'comment moderation admin Elm UI', () => {
	test.beforeEach( async ( { page } ) => {
		// Reset the rules table so each run owns its state — uses the
		// already-localised AJAX bootstrap, not the PHP container.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await page.waitForFunction(
			() => /** @type {any} */ ( window ).pccmAdminData
		);
		await page.evaluate( async () => {
			const data = /** @type {any} */ ( window ).pccmAdminData;
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.clear );
			params.set( '_wpnonce', data.ajaxNonce );
			await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
		} );
	} );

	test( 'Stage 22: Elm app mounts, renders Stitch dashboard surfaces, and round-trips a regex rule through the UI', async ( {
		page,
	} ) => {
		// Re-open so the Elm app initialises against the freshly-cleared
		// list. (`beforeEach` already navigated once to wipe the table.)
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();

		// 1) Elm app mounted — the view function emits a div with both the
		//    mount id (re-applied so the PHP-side selector still works) and
		//    the .pccm-app class the dark-mode stylesheet targets.
		await expect( page.locator( 'div#pccm-admin-root.pccm-app' ) ).toHaveCount(
			1
		);

		// 2) Rules card heading is rendered by Elm (proves init + view).
		await expect(
			page.getByRole( 'heading', { name: 'Rules', level: 2 } )
		).toBeVisible();

		// 3) The type-to-add dropdown lists exactly the four implemented
		//    rule types — and no CIDR — matching the issue's mapping table.
		const dropdown = page.locator( '#pccm-type-to-add' );
		await expect( dropdown ).toBeVisible();
		const optionLabels = await dropdown.locator( 'option' ).allTextContents();
		expect( optionLabels ).toEqual( [
			'Select Rule Type',
			'Regular Expression',
			'Wildcard',
			'IP Range',
			'Conditional',
		] );

		// 4) Primary actions render.
		await expect(
			page.getByRole( 'button', { name: 'Add Rule', exact: true } )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Clear All Rules', exact: true } )
		).toBeVisible();

		// 5) "Showing X of Y rules" counter renders (zero state to start).
		await expect( page.locator( '.pccm-meta-row__count' ) ).toContainText(
			/Showing \d+ of \d+ rules/
		);

		// 6) Filter panel toggles in and out.
		await page
			.getByRole( 'button', { name: 'Show Filters', exact: true } )
			.click();
		await expect( page.locator( '.pccm-filters' ) ).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Hide Filters', exact: true } )
			.click();
		await expect( page.locator( '.pccm-filters' ) ).toHaveCount( 0 );

		// 7) End-to-end create flow: pick Regex, click Add Rule, fill the
		//    form, save, and confirm the list now contains the new rule
		//    with the colour-coded Spam badge.
		await dropdown.selectOption( 'regex' );
		await page
			.getByRole( 'button', { name: 'Add Rule', exact: true } )
			.click();

		await expect(
			page.getByRole( 'heading', {
				name: /Add Regular Expression Rule/i,
				level: 2,
			} )
		).toBeVisible();

		await page.fill( '#pccm-rule-name', 'E2E Stage 22 Regex' );
		await page.fill(
			'#pccm-rule-desc',
			'Created end-to-end through the Elm admin UI.'
		);
		await page.fill( '#pccm-rule-pattern', '/casino/i' );
		await page.locator( '#pccm-response-spam' ).check();

		// The Content part is pre-ticked for Regex; toggle Email on too so
		// we know the part checkboxes write back into the model.
		await page.locator( '#pccm-part-email' ).check();

		await page
			.getByRole( 'button', { name: 'Save Rule', exact: true } )
			.click();

		// Banner shows the success message from the AJAX layer, list
		// reloads with the new rule.
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'Rule created'
		);
		const ruleBlock = page.locator( '.pccm-rule', {
			hasText: 'E2E Stage 22 Regex',
		} );
		await expect( ruleBlock ).toBeVisible();
		await expect( ruleBlock.locator( '.pccm-rule__summary' ) ).toContainText(
			'/casino/i'
		);
		await expect(
			ruleBlock.locator( '[data-response="spam"]' )
		).toBeVisible();

		// Comment-part chips reflect what we ticked (Email + Content lit).
		const onChips = await ruleBlock
			.locator( '.pccm-part-chip--on' )
			.allTextContents();
		expect( onChips.sort() ).toEqual( [ 'Content', 'Email' ] );

		// Stats are visible after toggling the details panel.
		await ruleBlock
			.getByRole( 'button', { name: 'Show rule details' } )
			.click();
		await expect( ruleBlock.locator( '.pccm-rule__details' ) ).toContainText(
			'Used 0 times'
		);

		// 8) Cancel-button discards a freshly-opened editor without saving.
		await dropdown.selectOption( 'wildcard' );
		await page
			.getByRole( 'button', { name: 'Add Rule', exact: true } )
			.click();
		await expect(
			page.getByRole( 'heading', {
				name: /Add Wildcard Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await page.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( page.locator( '.pccm-editor' ) ).toHaveCount( 0 );

		// Capture the stage screenshot the routine commits.
		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-22.png'
			),
			fullPage: true,
		} );
	} );
} );
