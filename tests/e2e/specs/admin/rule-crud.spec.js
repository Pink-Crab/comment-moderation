// @ts-check
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Stage 26 — Jukebox-style end-to-end coverage of the rebuild spec's
 * full management flow (rebuild spec §3 + §4 + §5 + §6 + §7 + §8).
 *
 * Earlier specs in this folder prove individual surfaces: the Elm
 * mount + dashboard chrome (stage 22), the AJAX layer (stages 20 + 21),
 * Akismet co-operation (stage 24), and the pccm_-prefixed extensibility
 * hooks (stage 25). This spec is the Jukebox-pattern integration: walk
 * the administrator through the rebuild checklist end-to-end against a
 * live wp-env site, driving every rule type's create + edit + delete
 * path through the real Elm UI (no PHP shortcuts), verifying that:
 *
 *   1. Each of the four rule types (Regex, Wildcard, IP Range, and the
 *      Advanced Composite — including a nested 2-level OR-inside-AND
 *      group built through the recursive condition tree) round-trips
 *      via the admin form.
 *   2. Clicking Edit on a list block opens the form pre-filled with
 *      that rule's stored values (rebuild spec §6 "Direct link to a
 *      rule") — the SPA flavour of the deep-link-to-edit contract — and
 *      saving keeps the same row id (so accumulated usage stats are
 *      preserved at the persistence boundary, matching the integration
 *      test's stronger assertion).
 *   3. Delete removes a single rule with the success banner the AJAX
 *      controller emits, and Clear All Rules wires the browser confirm
 *      dialog (the inline onclick the Elm app attaches) so the
 *      destructive action only fires on confirmation.
 *   4. Search + multi-criteria filter narrow the list and the meta-row
 *      counter reflects the visible vs. total numbers (rebuild spec §7).
 *   5. Pagination caps the list at PAGE_SIZE (25) and the "Show More
 *      Rules" button appends the next page in place rather than
 *      replacing it (rebuild spec §8).
 *
 * Shared bootstrap: every test resets the rules table through the
 * already-localised admin-ajax `clear` action so each case owns its
 * state, mirroring the pattern stage 22 introduced.
 */

const ADMIN_URL =
	'/wp-admin/options-general.php?page=pinkcrab-comment-moderation';

const SCREENSHOT_PATH = path.resolve(
	__dirname,
	'../../../../.karkinos/shots/stage-26.png'
);

/**
 * Resolve the admin-ajax bootstrap the PHP page localises onto window.
 * Every helper that needs to talk straight to admin-ajax goes through
 * this so the nonce + actions are taken from the live page, not hard
 * coded.
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
 * Reset the rules table by calling the signed clear endpoint. Returns
 * the bootstrap so callers can reuse it for further AJAX calls.
 *
 * @param {import('@playwright/test').Page} page
 */
async function resetRules( page ) {
	const bootstrap = await readBootstrap( page );
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
	return bootstrap;
}

/**
 * Create one rule directly through admin-ajax so list-state tests can
 * seed bulk fixtures without rebuilding the form for every entry.
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

test.describe( 'comment moderation admin — rule management (stage 26)', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( ADMIN_URL );
		await resetRules( page );
		// Re-open so the Elm app initialises against the cleared table.
		await page.goto( ADMIN_URL );
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Rules', level: 2 } )
		).toBeVisible();
	} );

	test( 'Stage 26: every rule type round-trips through the Elm UI with stats preserved on edit, and delete wipes a single block', async ( {
		page,
	} ) => {
		const dropdown = page.locator( '#pccm-type-to-add' );

		// 1) ---------- Regex rule create + edit (stats preserved) ----------
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
		await page.fill( '#pccm-rule-name', 'E2E Regex Casino' );
		await page.fill(
			'#pccm-rule-desc',
			'Catches the word casino in comment bodies.'
		);
		await page.fill( '#pccm-rule-pattern', '/casino/i' );
		await page.locator( '#pccm-response-spam' ).check();
		await page.locator( '#pccm-part-email' ).check();
		await page
			.getByRole( 'button', { name: 'Save Rule', exact: true } )
			.click();
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'Rule created'
		);

		const regexBlock = page.locator( '.pccm-rule', {
			hasText: 'E2E Regex Casino',
		} );
		await expect( regexBlock ).toBeVisible();
		const regexRuleId = await regexBlock.getAttribute( 'data-rule-id' );
		expect( regexRuleId ).toMatch( /^\d+$/ );
		await expect( regexBlock.locator( '[data-response="spam"]' ) ).toBeVisible();

		// Open the details panel so the assertion below sees the freshly
		// rendered usage stats line (rebuild spec §5 stats display).
		await regexBlock
			.getByRole( 'button', { name: 'Show rule details' } )
			.click();
		await expect( regexBlock.locator( '.pccm-rule__details' ) ).toContainText(
			'Used 0 times'
		);

		// Edit: clicking Edit on the block opens the same editor with the
		// stored values pre-filled — the SPA realisation of "direct link
		// to a rule" (rebuild spec §6).
		await regexBlock.getByRole( 'button', { name: 'Edit' } ).click();
		await expect(
			page.getByRole( 'heading', {
				name: /Edit Regular Expression Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await expect( page.locator( '#pccm-rule-name' ) ).toHaveValue(
			'E2E Regex Casino'
		);
		await expect( page.locator( '#pccm-rule-pattern' ) ).toHaveValue(
			'/casino/i'
		);
		await expect( page.locator( '#pccm-part-email' ) ).toBeChecked();
		await expect( page.locator( '#pccm-part-content' ) ).toBeChecked();
		await expect( page.locator( '#pccm-response-spam' ) ).toBeChecked();

		// Mutate a couple of fields and persist. The endpoint's update
		// path is the one the integration test pins for stats
		// preservation; this asserts the UI uses that path (id stays
		// constant + the stats display does not regress to a "new"
		// looking row).
		await page.fill( '#pccm-rule-name', 'E2E Regex Casino & Gambling' );
		await page.fill( '#pccm-rule-pattern', '/(casino|gambling)/i' );
		await page
			.getByRole( 'button', { name: 'Update Rule', exact: true } )
			.click();
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'Rule updated'
		);

		const updatedRegexBlock = page.locator(
			`.pccm-rule[data-rule-id="${ regexRuleId }"]`
		);
		await expect( updatedRegexBlock.locator( '.pccm-rule__title' ) ).toContainText(
			'E2E Regex Casino & Gambling'
		);
		await expect( updatedRegexBlock.locator( '.pccm-rule__summary' ) ).toContainText(
			'/(casino|gambling)/i'
		);
		// The details panel toggle state persists across the list reload
		// the editor save triggers (model.detailsOpen survives GotSaveResult),
		// so the freshly-rendered details row is the right place to assert
		// that times_used did not regress to a "new row" reading — i.e. the
		// AJAX layer reused the existing id rather than recreating the rule.
		await expect(
			updatedRegexBlock.locator( '.pccm-rule__details' )
		).toContainText( 'Used 0 times' );

		// 2) ---------- Wildcard rule create + edit ----------
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
		await page.fill( '#pccm-rule-name', 'E2E Wildcard Spam Domain' );
		await page.fill( '#pccm-rule-pattern', '*@spam-domain.tld' );
		// Content is pre-ticked for wildcard; swap to email-only.
		await page.locator( '#pccm-part-content' ).uncheck();
		await page.locator( '#pccm-part-email' ).check();
		await page.locator( '#pccm-response-trash' ).check();
		await page
			.getByRole( 'button', { name: 'Save Rule', exact: true } )
			.click();
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'Rule created'
		);
		const wildcardBlock = page.locator( '.pccm-rule', {
			hasText: 'E2E Wildcard Spam Domain',
		} );
		await expect( wildcardBlock ).toBeVisible();
		const wildcardRuleId = await wildcardBlock.getAttribute( 'data-rule-id' );
		expect( wildcardRuleId ).toMatch( /^\d+$/ );
		await expect(
			wildcardBlock.locator( '[data-response="trash"]' )
		).toBeVisible();
		const wildcardOnChips = await wildcardBlock
			.locator( '.pccm-part-chip--on' )
			.allTextContents();
		expect( wildcardOnChips ).toEqual( [ 'Email' ] );

		// 3) ---------- IP Range rule create ----------
		await dropdown.selectOption( 'ip_range' );
		await page
			.getByRole( 'button', { name: 'Add Rule', exact: true } )
			.click();
		await expect(
			page.getByRole( 'heading', {
				name: /Add IP Range Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await page.fill( '#pccm-rule-name', 'E2E IP Range Quarantine' );
		await page.fill( '#pccm-start-ip', '203.0.113.10' );
		await page.fill( '#pccm-end-ip', '203.0.113.40' );
		await page.locator( '#pccm-response-pending' ).check();
		await page
			.getByRole( 'button', { name: 'Save Rule', exact: true } )
			.click();
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'Rule created'
		);
		const ipBlock = page.locator( '.pccm-rule', {
			hasText: 'E2E IP Range Quarantine',
		} );
		await expect( ipBlock ).toBeVisible();
		await expect( ipBlock.locator( '.pccm-rule__summary' ) ).toContainText(
			'IP Range Between 203.0.113.10 and 203.0.113.40'
		);
		await expect(
			ipBlock.locator( '[data-response="pending"]' )
		).toBeVisible();

		// 4) ---------- Conditional rule create (nested 2-level builder) ----
		await dropdown.selectOption( 'conditional' );
		await page
			.getByRole( 'button', { name: 'Add Rule', exact: true } )
			.click();
		await expect(
			page.getByRole( 'heading', {
				name: /Add Conditional Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await page.fill( '#pccm-rule-name', 'E2E Conditional Gmail Crypto' );
		await page.fill(
			'#pccm-rule-desc',
			'Pending: email matches gmail AND content mentions crypto.'
		);
		await page.locator( '#pccm-response-pending' ).check();

		const builder = page.locator( '.pccm-builder-group' ).first();

		// First condition: email contains "@gmail.com".
		const firstRow = builder.locator( '.pccm-builder-condition' ).first();
		await firstRow
			.getByRole( 'combobox', { name: 'Comment part' } )
			.selectOption( 'email' );
		await firstRow
			.getByRole( 'combobox', { name: 'Operator' } )
			.selectOption( 'contains' );
		await firstRow
			.getByRole( 'textbox', { name: 'Condition value' } )
			.fill( '@gmail.com' );

		// Add a nested OR group with two sibling conditions inside (so the
		// tree is actually recursive — rebuild spec §4.5 "composite rule
		// built from one or more conditions" + stage 9's recursive group
		// tree). The validator only requires ≥1 leaf, but a 2-level mixed
		// AND/OR tree is the most realistic shape and the one §12 worked
		// scenario C resembles when extended.
		await builder
			.getByRole( 'button', { name: '+ Group', exact: true } )
			.click();
		const nestedGroup = page.locator( '.pccm-builder-group' ).nth( 1 );
		await nestedGroup
			.getByRole( 'button', { name: 'Toggle combinator' } )
			.first()
			.click(); // AND → OR

		const nestedFirst = nestedGroup
			.locator( '.pccm-builder-condition' )
			.first();
		await nestedFirst
			.getByRole( 'combobox', { name: 'Comment part' } )
			.selectOption( 'content' );
		await nestedFirst
			.getByRole( 'combobox', { name: 'Operator' } )
			.selectOption( 'contains' );
		await nestedFirst
			.getByRole( 'textbox', { name: 'Condition value' } )
			.fill( 'crypto' );

		await nestedGroup
			.getByRole( 'button', { name: '+ Condition', exact: true } )
			.click();
		const nestedSecond = nestedGroup
			.locator( '.pccm-builder-condition' )
			.nth( 1 );
		await nestedSecond
			.getByRole( 'combobox', { name: 'Comment part' } )
			.selectOption( 'content' );
		await nestedSecond
			.getByRole( 'combobox', { name: 'Operator' } )
			.selectOption( 'contains' );
		await nestedSecond
			.getByRole( 'textbox', { name: 'Condition value' } )
			.fill( 'NFT' );

		await page
			.getByRole( 'button', { name: 'Save Rule', exact: true } )
			.click();
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'Rule created'
		);
		const conditionalBlock = page.locator( '.pccm-rule', {
			hasText: 'E2E Conditional Gmail Crypto',
		} );
		await expect( conditionalBlock ).toBeVisible();
		const conditionalRuleId = await conditionalBlock.getAttribute(
			'data-rule-id'
		);
		expect( conditionalRuleId ).toMatch( /^\d+$/ );
		await expect(
			conditionalBlock.locator( '.pccm-rule__summary' )
		).toContainText( 'All conditions must match' );

		// Edit the conditional rule to prove the recursive tree round-trips:
		// the form should re-open with the same nested group structure.
		await conditionalBlock.getByRole( 'button', { name: 'Edit' } ).click();
		await expect(
			page.getByRole( 'heading', {
				name: /Edit Conditional Rule/i,
				level: 2,
			} )
		).toBeVisible();
		// Two builder-groups means the nested group survived the round-trip.
		await expect( page.locator( '.pccm-builder-group' ) ).toHaveCount( 2 );
		await expect(
			page.locator( '.pccm-builder-group' ).nth( 1 ).locator(
				'.pccm-builder-group__combinator'
			)
		).toContainText( 'OR' );
		await page.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( page.locator( '.pccm-editor' ) ).toHaveCount( 0 );

		// 5) ---------- Delete a single rule ----------
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 4 );
		await ipBlock.getByRole( 'button', { name: 'Delete' } ).click();
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'Rule Deleted'
		);
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 3 );
		await expect(
			page.locator( '.pccm-rule', { hasText: 'E2E IP Range Quarantine' } )
		).toHaveCount( 0 );
	} );

	test( 'Stage 26: Clear All Rules wires the browser confirm dialog so it only clears on accept (and a deep-linked Edit click opens the form pre-filled)', async ( {
		page,
	} ) => {
		// Seed three rules through admin-ajax so the clear-all flow has
		// something to clear and the edit-deep-link assertions have a
		// known target.
		const bootstrap = await readBootstrap( page );
		const seed1 = await createRuleViaAjax( page, bootstrap, {
			type: 'regex',
			name: 'Deep link target',
			description: 'Anchor rule the test edits via the list block.',
			pattern: '/spam-target/i',
			'comment_parts[]': [ 'content' ],
			response: 'pending',
		} );
		expect( seed1.success ).toBe( true );
		const deepLinkId = String( seed1.data.rule.id );

		await createRuleViaAjax( page, bootstrap, {
			type: 'wildcard',
			name: 'Disposable',
			pattern: '*@throwaway.tld',
			'comment_parts[]': [ 'email' ],
			response: 'trash',
		} );
		await createRuleViaAjax( page, bootstrap, {
			type: 'ip_range',
			name: 'Quarantine block',
			start_ip: '198.51.100.1',
			end_ip: '198.51.100.50',
			response: 'spam',
		} );

		// Re-open the screen so the Elm app picks up the seeded rules.
		await page.goto( ADMIN_URL );
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 3 );

		// Deep-link-to-edit (rebuild spec §6): clicking Edit on the
		// target rule should open the editor with that rule's stored
		// values pre-filled — same UX a `?pccm_edit={id}` URL deep-link
		// produces once the SPA mounts. We hit the same code path here
		// (the EditRuleClicked message) and assert every field is
		// pre-populated from the persisted row.
		const targetBlock = page.locator(
			`.pccm-rule[data-rule-id="${ deepLinkId }"]`
		);
		await expect( targetBlock ).toBeVisible();
		await targetBlock.getByRole( 'button', { name: 'Edit' } ).click();
		await expect(
			page.getByRole( 'heading', {
				name: /Edit Regular Expression Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await expect( page.locator( '#pccm-rule-name' ) ).toHaveValue(
			'Deep link target'
		);
		await expect( page.locator( '#pccm-rule-pattern' ) ).toHaveValue(
			'/spam-target/i'
		);
		await expect( page.locator( '#pccm-response-pending' ) ).toBeChecked();
		await page.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( page.locator( '.pccm-editor' ) ).toHaveCount( 0 );

		// Clear All — dismiss the confirm first; the rules must survive.
		page.once( 'dialog', ( dialog ) => {
			expect( dialog.message() ).toBe(
				'Are you sure you want to Clear All Rules?'
			);
			dialog.dismiss();
		} );
		await page
			.getByRole( 'button', { name: 'Clear All Rules', exact: true } )
			.click();
		// Give the dialog a tick to resolve; the list must still hold three.
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 3 );

		// Accept the confirm — the AJAX clear endpoint should run and the
		// success banner show.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await page
			.getByRole( 'button', { name: 'Clear All Rules', exact: true } )
			.click();
		await expect( page.locator( '.pccm-banner--success' ) ).toContainText(
			'All rules cleared'
		);
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 0 );
		await expect( page.locator( '.pccm-meta-row__count' ) ).toContainText(
			'Showing 0 of 0 rules'
		);
	} );

	test( 'Stage 26: search + multi-criteria filters narrow the list (rebuild spec §7)', async ( {
		page,
	} ) => {
		const bootstrap = await readBootstrap( page );
		await createRuleViaAjax( page, bootstrap, {
			type: 'regex',
			name: 'Casino keyword',
			pattern: '/casino/i',
			'comment_parts[]': [ 'content' ],
			response: 'spam',
		} );
		await createRuleViaAjax( page, bootstrap, {
			type: 'wildcard',
			name: 'Gmail trap',
			pattern: '*@gmail.com',
			'comment_parts[]': [ 'email' ],
			response: 'pending',
		} );
		await createRuleViaAjax( page, bootstrap, {
			type: 'ip_range',
			name: 'Block range',
			start_ip: '192.0.2.10',
			end_ip: '192.0.2.20',
			response: 'trash',
		} );

		await page.goto( ADMIN_URL );
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 3 );
		await expect( page.locator( '.pccm-meta-row__count' ) ).toContainText(
			'Showing 3 of 3 rules'
		);

		await page
			.getByRole( 'button', { name: 'Show Filters', exact: true } )
			.click();
		const filters = page.locator( '.pccm-filters' );
		await expect( filters ).toBeVisible();

		// Free-text search narrows to the name match.
		await filters.locator( '#pccm-filter-search' ).fill( 'Gmail' );
		await page
			.getByRole( 'button', { name: 'Apply Filters', exact: true } )
			.click();
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pccm-rule__title' ) ).toContainText(
			'Gmail trap'
		);
		await expect( page.locator( '.pccm-meta-row__count' ) ).toContainText(
			'Showing 1 of 1 rules'
		);

		// Clear Filters resets the visible list back to the full three.
		// The panel stays open across the reload (ClearFiltersClicked only
		// resets the draft + applied filters, not filtersOpen) so the next
		// step continues to drive the same fieldsets.
		await page
			.getByRole( 'button', { name: 'Clear Filters', exact: true } )
			.click();
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 3 );

		// Multi-criteria: tick the Wildcard type AND the Pending response
		// — only the Gmail trap row should survive both criteria.
		await expect( filters ).toBeVisible();
		await filters.getByRole( 'checkbox', { name: 'Wildcard' } ).check();
		await filters.getByRole( 'checkbox', { name: 'Pending' } ).check();
		await page
			.getByRole( 'button', { name: 'Apply Filters', exact: true } )
			.click();
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pccm-rule__title' ) ).toContainText(
			'Gmail trap'
		);
	} );

	test( 'Stage 26: Show More Rules pagination appends the next page rather than replacing it (rebuild spec §8)', async ( {
		page,
	} ) => {
		const bootstrap = await readBootstrap( page );
		// Twenty-seven seeded rules — two more than PAGE_SIZE — so the
		// list has to paginate. Sequential IDs keep the order stable for
		// the assertion below.
		for ( let i = 1; i <= 27; i++ ) {
			const padded = String( i ).padStart( 2, '0' );
			const result = await createRuleViaAjax( page, bootstrap, {
				type: 'wildcard',
				name: `Bulk seed ${ padded }`,
				pattern: `*bulk-${ padded }*`,
				'comment_parts[]': [ 'content' ],
				response: 'spam',
			} );
			expect( result.success, `seed ${ padded }` ).toBe( true );
		}

		await page.goto( ADMIN_URL );
		// PAGE_SIZE is 25 (Rule_Repository::PAGE_SIZE) — the first page
		// fits 25 of the 27 seeded rules; the meta-row mirrors that.
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 25 );
		await expect( page.locator( '.pccm-meta-row__count' ) ).toContainText(
			'Showing 25 of 27 rules'
		);
		const showMore = page.getByRole( 'button', {
			name: 'Show More Rules',
			exact: true,
		} );
		await expect( showMore ).toBeVisible();

		await showMore.click();
		// Append, not replace: the next two rules join the first 25.
		await expect( page.locator( '.pccm-rule' ) ).toHaveCount( 27 );
		await expect( page.locator( '.pccm-meta-row__count' ) ).toContainText(
			'Showing 27 of 27 rules'
		);
		await expect( showMore ).toHaveCount( 0 );

		// Stage screenshot — the paginated list view is the primary
		// changed screen the routine commits as evidence.
		await page.screenshot( { path: SCREENSHOT_PATH, fullPage: true } );
	} );
} );
