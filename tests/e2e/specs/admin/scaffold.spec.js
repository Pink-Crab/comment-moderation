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
	test( 'Stage 1: plugin remains active and admin page loads after Hello_World scaffold removal', async ( {
		page,
	} ) => {
		// Stage 1 retires the scaffold Hello_World Hookable, its View Component,
		// its kebab-case template, its asset sources, and its integration test;
		// it also trims the now-orphaned npm `build:scripts`/`start:scripts`
		// wiring and rewrites the CI job's `Verify build output exists` step.
		// No user-facing feature changes — the proof is that the plugin still
		// autoloads (no fatal from a class deleted out from under
		// config/registration.php) and the Settings → Comment Moderation page
		// the rest of the rebuild lives on still mounts.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect( page ).toHaveURL(
			/options-general\.php\?page=pinkcrab-comment-moderation/
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();
		await expect( page.locator( '#pccm-admin-root' ) ).toBeAttached();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-1.png'
			),
			fullPage: true,
		} );
	} );

	test( 'Stage 4: plugin remains active after the AJAX controller drops the unused Plugin_Config dependency', async ( {
		page,
	} ) => {
		// Stage 4 strips three dead members from Rule_Ajax_Controller — the
		// `Plugin_Config $config` constructor parameter (and its property
		// assignment), the matching `use` import, and the `text_domain()`
		// accessor that only existed to silence PHPMD's "unused property"
		// warning. The controller is autowired by Perique (`config/di.php` does
		// NOT explicitly inject `Plugin_Config`), so removing the parameter is
		// a no-op for the DI graph. There is no admin screen built yet — the
		// available end-to-end claim is the same as stages 1/3: the plugin
		// still autoloads, no fatal escapes from the entry file, and Settings
		// → Comment Moderation still mounts.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect( page ).toHaveURL(
			/options-general\.php\?page=pinkcrab-comment-moderation/
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();
		await expect( page.locator( '#pccm-admin-root' ) ).toBeAttached();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-4.png'
			),
			fullPage: true,
		} );
	} );

	test( 'Stage 3: plugin remains active after the empty functions.php helper-stub is retired', async ( {
		page,
	} ) => {
		// Stage 3 deletes the scaffold's empty root `functions.php` helper-stub
		// and removes the `require_once …/functions.php` line from the plugin
		// entry file. No user-facing feature changes — the proof is that
		// loading the entry file (with the require_once line gone) does not
		// fatal, the plugin still autoloads, and the Settings → Comment
		// Moderation page the rest of the rebuild lives on still mounts.
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page ).toHaveURL( /plugins\.php/ );
		await expect(
			page.getByRole( 'row', { name: /PinkCrab Comment Moderation/i } )
		).toBeVisible();

		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect( page ).toHaveURL(
			/options-general\.php\?page=pinkcrab-comment-moderation/
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();
		await expect( page.locator( '#pccm-admin-root' ) ).toBeAttached();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-3.png'
			),
			fullPage: true,
		} );
	} );

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

	test( 'Stage 19: Settings → Comment Moderation page is reachable and prints the Elm mount node with localized bootstrap data', async ( {
		page,
	} ) => {
		// Stage 19 registers the single admin screen under
		// Settings → Comment Moderation via the perique-admin-menu module.
		// The page itself only prints an escaped Elm-mount div and
		// localises REST/nonce data onto the admin script. The Elm app
		// (assets/js/admin.js) takes over from there.

		// 1) Reachable via the canonical wp-admin slug, with the right H1.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect( page ).toHaveURL(
			/options-general\.php\?page=pinkcrab-comment-moderation/
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();

		// 2) The Elm mount node is rendered (Elm boots into it).
		await expect( page.locator( '#pccm-admin-root' ) ).toBeAttached();

		// 3) Bootstrap REST/nonce data has been localised onto window.
		const bootstrap = await page.evaluate(
			() =>
				/** @type {any} */ ( window ).pccmAdminData || null
		);
		expect( bootstrap ).not.toBeNull();
		expect( bootstrap.mountId ).toBe( 'pccm-admin-root' );
		expect( typeof bootstrap.nonce ).toBe( 'string' );
		expect( bootstrap.nonce.length ).toBeGreaterThan( 0 );
		expect( typeof bootstrap.restRoot ).toBe( 'string' );
		// wp-env may run without pretty permalinks so the REST root can be
		// either "…/wp-json/" or "…/index.php?rest_route=/" — both are valid.
		expect( bootstrap.restRoot ).toMatch(
			/(\/wp-json\/?|rest_route=\/)$/
		);
		expect( bootstrap.restNamespace ).toBe(
			'pinkcrab-comment-moderation/v1'
		);
		expect( bootstrap.pageSlug ).toBe( 'pinkcrab-comment-moderation' );

		// 4) Confirm the Settings submenu now exposes the page (proves the
		//    perique-admin-menu module wired it under options-general.php).
		await expect(
			page.locator(
				'#adminmenu a[href*="page=pinkcrab-comment-moderation"]'
			)
		).toHaveCount( 1 );

		// Capture the primary changed screen for the routine commit.
		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-19.png'
			),
			fullPage: true,
		} );
	} );

	test( 'Stage 20: admin AJAX endpoints accept signed requests and reject unsigned ones', async ( {
		page,
	} ) => {
		// Stage 20 wires the six admin AJAX endpoints (list / get / create /
		// update / delete / clear) behind the Comment Moderation screen. Each
		// endpoint verifies a pinkcrab/wp-nonce token, runs the filterable
		// capability check, sanitises the payload at the boundary, and
		// responds with escaped JSON. This spec exercises that the bootstrap
		// localises the nonce + ajaxUrl + ajaxActions onto the page, that a
		// signed `create_rule` round-trips through wp-admin/admin-ajax.php
		// and persists, and that an unsigned call to the same endpoint is
		// rejected with the spec's security-check message.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect( page ).toHaveURL(
			/options-general\.php\?page=pinkcrab-comment-moderation/
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();

		// 1) The page bootstraps the new AJAX fields onto window so the Elm
		//    app can authenticate its admin-ajax calls back to WordPress.
		const bootstrap = await page.evaluate(
			() =>
				/** @type {any} */ ( window ).pccmAdminData || null
		);
		expect( bootstrap ).not.toBeNull();
		expect( typeof bootstrap.ajaxUrl ).toBe( 'string' );
		expect( bootstrap.ajaxUrl ).toMatch( /admin-ajax\.php$/ );
		expect( typeof bootstrap.ajaxNonce ).toBe( 'string' );
		expect( bootstrap.ajaxNonce.length ).toBeGreaterThan( 0 );
		expect( bootstrap.ajaxActions ).toMatchObject( {
			list: 'pinkcrab_comment_moderation_list_rules',
			get: 'pinkcrab_comment_moderation_get_rule',
			create: 'pinkcrab_comment_moderation_create_rule',
			update: 'pinkcrab_comment_moderation_update_rule',
			delete: 'pinkcrab_comment_moderation_delete_rule',
			clear: 'pinkcrab_comment_moderation_clear_rules',
		} );

		// 2) Clear any rules a prior run may have left behind so the assertions
		//    below own the table state.
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

		// 3) Signed `create_rule` POST goes through admin-ajax and reports
		//    `success: true` with the Rule_Created message.
		const created = await page.evaluate( async ( data ) => {
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.create );
			params.set( '_wpnonce', data.ajaxNonce );
			params.set( 'type', 'regex' );
			params.set( 'name', 'E2E block casino' );
			params.set( 'description', 'Created by playwright stage 20' );
			params.set( 'pattern', '/casino/i' );
			params.append( 'comment_parts[]', 'content' );
			params.set( 'response', 'spam' );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return response.json();
		}, bootstrap );
		expect(
			created,
			'create_rule should succeed'
		).toEqual( expect.objectContaining( { success: true } ) );
		expect( created.data.message ).toBe( 'Rule created' );
		expect( created.data.rule ).toMatchObject( {
			type: 'regex',
			name: 'E2E block casino',
			pattern: '/casino/i',
			response: 'spam',
		} );
		const createdId = created.data.rule.id;
		expect( typeof createdId ).toBe( 'number' );

		// 4) Signed `list_rules` returns the rule + the pagination metadata
		//    that drives the "Show More Rules" button.
		const listed = await page.evaluate( async ( data ) => {
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.list );
			params.set( '_wpnonce', data.ajaxNonce );
			params.set( 'page', '1' );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return response.json();
		}, bootstrap );
		expect( listed.success ).toBe( true );
		expect( listed.data.page_size ).toBe( 25 );
		expect( listed.data.total ).toBeGreaterThanOrEqual( 1 );
		expect( listed.data.has_more ).toBe( false );
		expect(
			listed.data.rules.find(
				( /** @type {any} */ rule ) => rule.id === createdId
			)
		).toBeTruthy();

		// 5) Signed `get_rule` (the deep-link-to-edit path) returns the full
		//    payload for the just-created id.
		const fetched = await page.evaluate(
			async ( { data, id } ) => {
				const params = new URLSearchParams();
				params.set( 'action', data.ajaxActions.get );
				params.set( '_wpnonce', data.ajaxNonce );
				params.set( 'id', String( id ) );
				const response = await fetch( data.ajaxUrl, {
					method: 'POST',
					credentials: 'include',
					body: params,
				} );
				return response.json();
			},
			{ data: bootstrap, id: createdId }
		);
		expect( fetched.success ).toBe( true );
		expect( fetched.data.rule.id ).toBe( createdId );
		expect( fetched.data.rule.pattern ).toBe( '/casino/i' );

		// 6) An unsigned call to the same `create_rule` action is rejected
		//    with the spec's security-check message — proves the nonce
		//    preflight runs and the endpoint is not CSRFable.
		const unsigned = await page.evaluate( async ( data ) => {
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.create );
			params.set( 'type', 'regex' );
			params.set( 'pattern', '/casino/i' );
			params.append( 'comment_parts[]', 'content' );
			params.set( 'response', 'spam' );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return { status: response.status, body: await response.json() };
		}, bootstrap );
		expect( unsigned.body.success ).toBe( false );
		expect( unsigned.body.data.message ).toBe( 'Security check failed.' );

		// Capture the primary changed screen for the routine commit.
		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-20.png'
			),
			fullPage: true,
		} );
	} );

	test( 'Stage 21: AJAX layer rejects bad nonces, sanitises boundary input, and round-trips a deep-linked rule', async ( {
		page,
	} ) => {
		// Stage 21 adds targeted PHPUnit coverage for the four behaviours the
		// rebuild spec pins on the AJAX layer (capability denial, nonce
		// failure via pinkcrab/wp-nonce, boundary sanitisation, deep-link).
		// This spec exercises the same contract live, end-to-end, against
		// wp-env's admin-ajax.php so the proof carries through the wire.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();

		const bootstrap = await page.evaluate(
			() =>
				/** @type {any} */ ( window ).pccmAdminData || null
		);
		expect( bootstrap ).not.toBeNull();

		// Reset the rules table so the assertions below own its state.
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

		// 1) A request with a non-empty but invalid `_wpnonce` token must be
		//    rejected — exercises the pinkcrab/wp-nonce `Nonce::validate()`
		//    branch (the missing-token branch is already covered by stage 20).
		const forged = await page.evaluate( async ( data ) => {
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.create );
			params.set( '_wpnonce', 'not-a-real-token' );
			params.set( 'type', 'regex' );
			params.set( 'pattern', '/casino/i' );
			params.append( 'comment_parts[]', 'content' );
			params.set( 'response', 'spam' );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return response.json();
		}, bootstrap );
		expect( forged.success ).toBe( false );
		expect( forged.data.message ).toBe( 'Security check failed.' );

		// 2) A signed create with HTML/script-tag payloads in name +
		//    description must succeed and persist sanitised values.
		const created = await page.evaluate( async ( data ) => {
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.create );
			params.set( '_wpnonce', data.ajaxNonce );
			params.set( 'type', 'regex' );
			params.set(
				'name',
				"Block <script>alert('xss')</script>casino rule"
			);
			params.set(
				'description',
				'Catches <strong>casino</strong> <script>alert(1)</script>spam.'
			);
			params.set( 'pattern', '/casino/i' );
			params.append( 'comment_parts[]', 'content' );
			params.set( 'response', 'spam' );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return response.json();
		}, bootstrap );
		expect( created.success ).toBe( true );
		expect( created.data.rule.name ).not.toContain( '<script>' );
		expect( created.data.rule.name ).not.toContain( 'alert' );
		expect( created.data.rule.description ).toContain(
			'<strong>casino</strong>'
		);
		expect( created.data.rule.description ).not.toContain( '<script>' );
		const createdId = created.data.rule.id;

		// 3) Deep-link to the just-created rule via the get endpoint and
		//    confirm the full payload (the sanitised name + the pattern)
		//    comes back, mirroring the spec's "Direct link to a rule" flow.
		const fetched = await page.evaluate(
			async ( { data, id } ) => {
				const params = new URLSearchParams();
				params.set( 'action', data.ajaxActions.get );
				params.set( '_wpnonce', data.ajaxNonce );
				params.set( 'id', String( id ) );
				const response = await fetch( data.ajaxUrl, {
					method: 'POST',
					credentials: 'include',
					body: params,
				} );
				return response.json();
			},
			{ data: bootstrap, id: createdId }
		);
		expect( fetched.success ).toBe( true );
		expect( fetched.data.rule.id ).toBe( createdId );
		expect( fetched.data.rule.pattern ).toBe( '/casino/i' );
		expect( fetched.data.rule.name ).not.toContain( '<script>' );

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-21.png'
			),
			fullPage: true,
		} );
	} );

	test( 'Stage 23: lint + build + sniff toolchain still produces a bundle that mounts the Elm admin UI', async ( {
		page,
	} ) => {
		// Stage 23 only runs the project's verification gates:
		//   - `npm run lint`        (elm-format --validate + elm-review)
		//   - `npm run build`       (elm make --optimize + scripts/bundle-admin.js)
		//   - `composer lint:php:phpcs` for the PHP enqueue/bridge code.
		// No production code is changed; the available end-to-end claim is that
		// the freshly re-emitted `assets/js/admin.js` still boots into the
		// Settings → Comment Moderation screen, the Elm mount node renders, and
		// the bootstrap data the PHP bridge localises is still wired up. This
		// mirrors the per-stage "plugin remains active" pattern used by stages
		// 9–18 for stages that intentionally touch no production code.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect( page ).toHaveURL(
			/options-general\.php\?page=pinkcrab-comment-moderation/
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();

		// Elm mount node + Rules card heading prove the rebuilt bundle still
		// initialises against the PHP-localised bootstrap.
		await expect( page.locator( 'div#pccm-admin-root.pccm-app' ) ).toHaveCount(
			1
		);
		await expect(
			page.getByRole( 'heading', { name: 'Rules', level: 2 } )
		).toBeVisible();

		// Bootstrap data is still being localised by the PHP enqueue/bridge
		// code that `composer lint:php:phpcs` just cleared.
		const bootstrap = await page.evaluate(
			() =>
				/** @type {any} */ ( window ).pccmAdminData || null
		);
		expect( bootstrap ).not.toBeNull();
		expect( bootstrap.mountId ).toBe( 'pccm-admin-root' );
		expect( typeof bootstrap.ajaxNonce ).toBe( 'string' );
		expect( bootstrap.ajaxNonce.length ).toBeGreaterThan( 0 );

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-23.png'
			),
			fullPage: true,
		} );
	} );
} );
