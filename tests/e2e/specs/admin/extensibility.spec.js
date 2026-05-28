// @ts-check
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Stage 25 — pccm_-prefixed extensibility hooks (rebuild spec §11).
 *
 * The PHP unit suite proves each hook fires with the right arguments and
 * lets an integrator replace the default behaviour. This spec is the
 * end-to-end mirror: a wp-env mu-plugin
 * (`tests/e2e/mu-plugins/pccm-extensibility-probe.php`) registers a
 * filter against every spec §11 hook, but each filter is gated on a
 * request-scoped `PCCM_EXTENSIBILITY_PROBE_ACTIVE` cookie so unrelated
 * specs that share the same wp-env site continue to see the unmodified
 * admin shell.
 *
 *   - `pccm_admin_page_html` (replace/restyle the screen): the probe
 *     prepends a banner div; the spec asserts the banner appears inside
 *     the wp-admin shell.
 *   - `pccm_edit_rule_link` (customise the Edit Rule deep-link markup):
 *     the probe rewrites the default `<a>` to a `<span>`; the spec hits a
 *     probe AJAX endpoint and asserts both the default markup and the
 *     filtered markup come back as documented.
 *   - `pccm_required_capability` (change who may access the screen): the
 *     probe drops it to `read`; the spec confirms the same probe endpoint
 *     reads the filtered value.
 *   - `pccm_akismet_enabled` (turn the Akismet co-operation off): the
 *     probe returns false; the spec confirms it.
 */
test.describe( 'comment moderation admin — pccm_ extensibility hooks (stage 25)', () => {
	test( 'Stage 25: every documented pccm_-prefixed extensibility hook fires and is filterable', async ( {
		page,
		context,
	} ) => {
		// Activate the probe by dropping the cookie its mu-plugin reads.
		const baseUrl = page.context().request._options?.baseURL || '';
		const url = new URL( baseUrl || 'http://localhost' );
		await context.addCookies( [
			{
				name: 'PCCM_EXTENSIBILITY_PROBE_ACTIVE',
				value: '1',
				domain: url.hostname,
				path: '/',
			},
		] );

		// Load the admin screen with the probe active. The pccm_admin_page_html
		// filter must wrap the rendered shell in the probe banner — this proves
		// the filter is called with the real, rendered markup before it is
		// echoed inside the wp-admin <div class="wrap"> shell.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();

		// pccm_admin_page_html filter ran — banner is on the page.
		const banner = page.locator( '[data-pccm-probe="page-html"]' );
		await expect( banner ).toHaveCount( 1 );
		await expect( banner ).toContainText( 'PCCM_PROBE_PAGE_HTML' );

		// The Elm mount is still present too — the filter wraps, doesn't
		// destroy. Proves the default render flow still happens before the
		// filter is applied.
		await expect( page.locator( 'div#pccm-admin-root' ) ).toHaveCount( 1 );

		// Pull the bootstrap so we can hit the probe AJAX endpoint.
		const bootstrap = await page.evaluate(
			() => /** @type {any} */ ( window ).pccmAdminData || null
		);
		expect( bootstrap ).not.toBeNull();

		// Read the probe state through the dedicated AJAX endpoint the
		// mu-plugin exposes. This drives `pccm_edit_rule_link`,
		// `pccm_required_capability`, and `pccm_akismet_enabled` end-to-end.
		const probe = await page.evaluate( async ( data ) => {
			const params = new URLSearchParams();
			params.set( 'action', 'pccm_probe_state' );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return response.json();
		}, bootstrap );

		expect( probe.success ).toBe( true );

		// pccm_required_capability — probe filter swapped manage_options for read.
		expect( probe.data.capability ).toBe( 'read' );

		// pccm_akismet_enabled — probe filter forced the integration off.
		expect( probe.data.akismet_enabled ).toBe( false );

		// pccm_edit_rule_link — the default markup is the documented
		// pccm-edit-rule-link <a>; the filtered markup is the probe's <span>
		// rewrite with the rule id and resolved URL it received as arguments.
		expect( probe.data.edit_link_default ).toContain(
			'class="pccm-edit-rule-link"'
		);
		expect( probe.data.edit_link_default ).toContain( 'pccm_edit=123' );
		expect( probe.data.edit_link_filtered ).toContain(
			'data-pccm-probe="edit-link"'
		);
		expect( probe.data.edit_link_filtered ).toContain(
			'data-rule-id="123"'
		);
		expect( probe.data.edit_link_filtered ).toContain( 'Edit Rule</span>' );

		// Stage screenshot — primary changed screen with the probe banner
		// visible above the Elm mount. The routine commits this as evidence
		// that the pccm_admin_page_html filter is firing on the real page.
		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-25.png'
			),
			fullPage: true,
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		// Best-effort cookie cleanup so a stale probe cookie does not bleed
		// into any spec that runs after this file in the same wp-env session.
		const ctx = await browser.newContext();
		await ctx.clearCookies();
		await ctx.close();
	} );
} );
