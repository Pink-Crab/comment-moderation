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

	test( 'Stage 6: Settings → Comment Moderation still mounts after the Akismet listener replaces its per-closure guard with shared cross-closure coordination', async ( {
		page,
	} ) => {
		// Stage 6 rebuilds the deferred-dispatch path inside
		// `Akismet_Integration`: a `$dispatched_comment_ids` ledger lives on
		// the (Perique-shared) instance, the `comment_post` closure now
		// dedupes against that ledger, and an identity check
		// (`get_comment( $id )` → field-by-field compare against the captured
		// `Comment_Submission`) gates the call to the gateway. The change is
		// invisible to the admin UI — the listener has no UI — so the
		// available end-to-end claim is the same shape used by the other
		// invisible-engine stages: registering the listener through DI still
		// boots cleanly, the Settings → Comment Moderation screen still
		// renders, and the Elm app still mounts onto its root node.
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
		await expect(
			page.locator( 'div#pccm-admin-root.pccm-app' )
		).toHaveCount( 1 );
		await expect(
			page.getByRole( 'heading', { name: 'Rules', level: 2 } )
		).toBeVisible();

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-6.png'
			),
			fullPage: true,
		} );
	} );

	test( 'Stage 9: ?pccm_edit={id} from the URL is surfaced as editRuleId on the localized Elm bootstrap payload', async ( {
		page,
	} ) => {
		// Stage 9 wires the PHP side of the deep-link-to-edit flow
		// (rebuild spec §6 "Direct link to a rule"). `Admin_Page::enqueue()`
		// reads `$_GET['pccm_edit']` via `absint()` and adds it to the
		// `pccmAdminData` payload as `editRuleId` so the Elm app can open
		// the matching rule's edit pane on load. The Elm consumer is built
		// in a later stage; the available e2e claim is therefore that the
		// PHP boundary surfaces the value (and omits it when absent).

		// 1) No deep-link in the URL → editRuleId is absent / zero.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();
		const withoutDeepLink = await page.evaluate(
			() =>
				/** @type {any} */ ( window ).pccmAdminData || null
		);
		expect( withoutDeepLink ).not.toBeNull();
		const editIdWhenAbsent = withoutDeepLink.editRuleId;
		expect(
			editIdWhenAbsent === undefined ||
				editIdWhenAbsent === 0 ||
				editIdWhenAbsent === '0',
			'editRuleId must be absent or zero when ?pccm_edit is not in the request'
		).toBeTruthy();

		// 2) `?pccm_edit=42` in the URL → editRuleId is 42 on the payload.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation&pccm_edit=42'
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Comment Moderation/i,
				level: 1,
			} )
		).toBeVisible();
		const withDeepLink = await page.evaluate(
			() =>
				/** @type {any} */ ( window ).pccmAdminData || null
		);
		expect( withDeepLink ).not.toBeNull();
		// wp_localize_script stringifies scalar payload values; the Elm
		// runtime parses editRuleId back to an int on the JS side.
		expect( Number( withDeepLink.editRuleId ) ).toBe( 42 );

		await page.screenshot( {
			path: path.resolve(
				__dirname,
				'../../../../.karkinos/shots/stage-9.png'
			),
			fullPage: true,
		} );
	} );

	test( 'Stage 10: ?pccm_edit={id} URL opens the matching rule\'s editor pre-filled in the Elm app', async ( {
		page,
	} ) => {
		// Stage 10 wires the JS + Elm consumer side of the deep-link the
		// Stage 9 PHP enqueue already surfaces on `pccmAdminData.editRuleId`
		// (rebuild spec §6 "Direct link to a rule"). The boot snippet forwards
		// the id into the Elm `flags` object; `flagsDecoder` reads it as an
		// optional `Maybe Int`; `init` dispatches the existing `get_rule`
		// admin-ajax call alongside the initial list and, on success, opens
		// the editor pre-filled with the stored values.
		//
		// Verify end-to-end: seed a real Regex rule through admin-ajax, then
		// land on the page with `?pccm_edit={id}` in the URL and assert that
		// the editor mounts open with that rule's stored fields populated.

		// 1) Seed a rule we can deep-link to.
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
			() => /** @type {any} */ ( window ).pccmAdminData
		);
		expect( bootstrap ).toBeTruthy();

		// Reset the rules table so the deep-linked id is the only row we
		// have to reason about and the spec is repeatable.
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

		const seeded = await page.evaluate( async ( data ) => {
			const params = new URLSearchParams();
			params.set( 'action', data.ajaxActions.create );
			params.set( '_wpnonce', data.ajaxNonce );
			params.set( 'type', 'regex' );
			params.set( 'name', 'Stage 10 deep-link target' );
			params.set(
				'description',
				'Seeded by stage 10 to verify the Elm deep-link.'
			);
			params.set( 'pattern', '/casino|gambling/i' );
			params.append( 'comment_parts[]', 'content' );
			params.append( 'comment_parts[]', 'email' );
			params.set( 'response', 'spam' );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'include',
				body: params,
			} );
			return response.json();
		}, bootstrap );
		expect( seeded.success ).toBe( true );
		const deepLinkId = seeded.data.rule.id;
		expect( typeof deepLinkId ).toBe( 'number' );

		// 2) Land on the screen with `?pccm_edit={id}` — the editor must
		//    open pre-filled with the seeded rule's values without any
		//    further user interaction.
		await page.goto(
			`/wp-admin/options-general.php?page=pinkcrab-comment-moderation&pccm_edit=${ deepLinkId }`
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Edit Regular Expression Rule/i,
				level: 2,
			} )
		).toBeVisible();
		await expect( page.locator( '#pccm-rule-name' ) ).toHaveValue(
			'Stage 10 deep-link target'
		);
		await expect( page.locator( '#pccm-rule-pattern' ) ).toHaveValue(
			'/casino|gambling/i'
		);
		await expect( page.locator( '#pccm-part-content' ) ).toBeChecked();
		await expect( page.locator( '#pccm-part-email' ) ).toBeChecked();
		await expect( page.locator( '#pccm-response-spam' ) ).toBeChecked();

		// 3) Cancel: the editor closes and the rule remains in the list.
		await page.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( page.locator( '.pccm-editor' ) ).toHaveCount( 0 );
		await expect(
			page.locator( '.pccm-rule', {
				hasText: 'Stage 10 deep-link target',
			} )
		).toBeVisible();

		// 4) No deep-link in the URL → the editor stays closed on load.
		await page.goto(
			'/wp-admin/options-general.php?page=pinkcrab-comment-moderation'
		);
		await expect(
			page.getByRole( 'heading', { name: 'Rules', level: 2 } )
		).toBeVisible();
		await expect( page.locator( '.pccm-editor' ) ).toHaveCount( 0 );

		// 5) Re-open with the deep-link so the screenshot captures the
		//    primary changed screen for this stage (the pre-filled editor).
		await page.goto(
			`/wp-admin/options-general.php?page=pinkcrab-comment-moderation&pccm_edit=${ deepLinkId }`
		);
		await expect(
			page.getByRole( 'heading', {
				name: /Edit Regular Expression Rule/i,
				level: 2,
			} )
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

	test( 'plugin remains active after Stage 16 wires the i18n Load_Text_Domain hookable on init', async ( {
		page,
	} ) => {
		// Stage 16 introduces src/Application/I18n/Load_Text_Domain.php — a
		// Perique Hookable that calls load_plugin_textdomain() on `init` at
		// priority 10 so the plugin's translations are loaded at the exact
		// timing WP 6.7+ demands (anything earlier trips the
		// `_load_textdomain_just_in_time` doing-it-wrong notice). It is
		// registered in config/registration.php and exercised by
		// tests/Integration/I18n/Test_Load_Text_Domain.php. No admin UI or
		// JS changes — the available end-to-end claim is therefore the same
		// as the other pre-UI stages: the plugin still autoloads and
		// WordPress reports it as active.
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
