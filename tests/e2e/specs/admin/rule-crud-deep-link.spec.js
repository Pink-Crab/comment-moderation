// @ts-check
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Stage 11 — Direct-link-to-edit (`?pccm_edit={id}`) end-to-end.
 *
 * The sibling `rule-crud.spec.js` (stage 26) exercises edit-via-Edit-button
 * inside the Elm SPA. This spec pins the rebuild spec §6 "Direct link to a
 * rule" contract from the opposite side: a fresh page load whose URL
 * carries `?pccm_edit={id}` must open that rule's editor pre-filled — with
 * no in-app Edit click on the list block — so other parts of an
 * integration (e.g. an "Edit Rule" link in the spam queue) can deep-link
 * straight to the editor.
 *
 * The rule is seeded straight through the signed admin-ajax create
 * endpoint (the same helper pattern the stage-26 spec uses) so the test
 * does not depend on the UI add-form being driven, then the editor surface
 * is asserted after a hard navigation to the deep-link URL.
 */

const ADMIN_URL =
	'/wp-admin/options-general.php?page=pinkcrab-comment-moderation';

const SCREENSHOT_PATH = path.resolve(
	__dirname,
	'../../../../.karkinos/shots/stage-11.png'
);

// Stage 12 (the verify-end-to-end stage) re-runs this same spec as proof
// the deep-link wiring still holds, and needs its own screenshot artifact
// to file alongside stage-11's. Capturing both from the one run keeps the
// fixtures truthful: the stage-12 image is the *same* successful editor
// surface stage-11 asserted on, not a stale or unrelated re-shot.
const STAGE_12_SCREENSHOT_PATH = path.resolve(
	__dirname,
	'../../../../.karkinos/shots/stage-12.png'
);

/**
 * Resolve the admin-ajax bootstrap the PHP page localises onto window.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<any>}
 */
async function readBootstrap( page ) {
	await page.waitForFunction(
		() => /** @type {any} */ ( window ).pccmAdminData
	);
	const bootstrap = await page.evaluate(
		() => /** @type {any} */ ( window ).pccmAdminData
	);
	expect( bootstrap, 'pccmAdminData should be localised' ).toBeTruthy();
	return bootstrap;
}

/**
 * Reset the rules table through the signed clear endpoint so the seeded
 * row is the only one we have to reason about.
 *
 * @param {import('@playwright/test').Page} page
 * @param {any} bootstrap
 */
async function clearRules( page, bootstrap ) {
	await page.evaluate( async ( data ) => {
		const params = new URLSearchParams();
		params.set( 'action', data.ajaxActions.clear );
		params.set( '_wpnonce', data.ajaxNonce );
		await fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'include',
			body: params,
		} );
	}, bootstrap );
}

/**
 * Seed a rule through the signed admin-ajax create endpoint and return
 * the parsed JSON response so the test can pick up the new id.
 *
 * @param {import('@playwright/test').Page} page
 * @param {any} bootstrap
 * @param {Record<string, string|string[]>} fields
 */
async function createRuleViaAjax( page, bootstrap, fields ) {
	return page.evaluate(
		async ( { data, payload } ) => {
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.create );
			params.set( '_wpnonce', data.ajaxNonce );
			for ( const [ key, value ] of Object.entries( payload ) ) {
				if ( Array.isArray( value ) ) {
					for ( const single of value ) {
						params.append( key, single );
					}
				} else {
					params.set( key, value );
				}
			}
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return response.json();
		},
		{ data: bootstrap, payload: fields }
	);
}

test.describe( 'comment moderation admin — deep-link to edit (stage 11)', () => {
	test( 'Stage 11: ?pccm_edit={id} URL opens the seeded rule editor pre-filled — without any in-app Edit click', async ( {
		page,
	} ) => {
		// 1) Land on the screen so the bootstrap (ajax url + nonce + action
		//    map) is localised, then reset the rules table so the assertions
		//    below own its state.
		await page.goto( ADMIN_URL );
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();
		const bootstrap = await readBootstrap( page );
		await clearRules( page, bootstrap );

		// 2) Seed a rule through the signed admin-ajax create endpoint —
		//    the same path the SPA uses — so the deep-link target exists
		//    independently of any UI add-form interaction.
		const seeded = await createRuleViaAjax( page, bootstrap, {
			type: 'regex',
			name: 'Stage 11 deep-link target',
			description: 'Seeded by stage 11 to verify the URL deep-link.',
			pattern: '/(casino|gambling)/i',
			'comment_parts[]': [ 'content', 'email' ],
			response: 'spam',
		} );
		expect( seeded.success, 'create_rule should succeed' ).toBe( true );
		const deepLinkId = seeded.data.rule.id;
		expect( typeof deepLinkId ).toBe( 'number' );

		// 3) Hard-navigate to the deep-link URL — no in-app click, no SPA
		//    state carried over from the seed call's page. The editor must
		//    mount open with every stored field of the seeded rule
		//    pre-filled (rebuild spec §6 "Direct link to a rule").
		await page.goto( `${ ADMIN_URL }&pccm_edit=${ deepLinkId }` );
		await expect(
			page.getByRole( 'heading', {
				name: /Edit Regular Expression Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await expect( page.locator( '#pccm-rule-name' ) ).toHaveValue(
			'Stage 11 deep-link target'
		);
		await expect( page.locator( '#pccm-rule-desc' ) ).toHaveValue(
			'Seeded by stage 11 to verify the URL deep-link.'
		);
		await expect( page.locator( '#pccm-rule-pattern' ) ).toHaveValue(
			'/(casino|gambling)/i'
		);
		await expect( page.locator( '#pccm-part-content' ) ).toBeChecked();
		await expect( page.locator( '#pccm-part-email' ) ).toBeChecked();
		await expect( page.locator( '#pccm-response-spam' ) ).toBeChecked();

		// 4) Sanity: the rule list block also exists on the same screen
		//    (the editor doesn't replace the list), and its row id matches
		//    the deep-linked id — proves we deep-linked into the real
		//    persisted row, not a fresh "Add Rule" form.
		await expect(
			page.locator( `.pccm-rule[data-rule-id="${ deepLinkId }"]` )
		).toBeVisible();

		// 5) Negative control: the same admin URL without `?pccm_edit` must
		//    NOT auto-open the editor — proves the editor was driven by the
		//    deep-link query arg, not by any always-on initial state.
		await page.goto( ADMIN_URL );
		await expect(
			page.getByRole( 'heading', { name: 'Rules', level: 2 } )
		).toBeVisible();
		await expect( page.locator( '.pccm-editor' ) ).toHaveCount( 0 );

		// 6) Re-open with the deep-link so the screenshot captures the
		//    pre-filled editor — the primary changed surface for this stage.
		await page.goto( `${ ADMIN_URL }&pccm_edit=${ deepLinkId }` );
		await expect(
			page.getByRole( 'heading', {
				name: /Edit Regular Expression Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await page.screenshot( { path: SCREENSHOT_PATH, fullPage: true } );
		await page.screenshot( {
			path: STAGE_12_SCREENSHOT_PATH,
			fullPage: true,
		} );
	} );
} );
