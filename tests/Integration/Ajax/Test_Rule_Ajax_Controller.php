<?php
/**
 * End-to-end test for Rule_Ajax_Controller.
 *
 * Mirrors the convention in tests/Integration/Engine/Test_Comment_Engine.php:
 * the controller is instantiated directly with a wpdb-backed Rule_Repository
 * (rather than going through Perique's DI) so the suite never depends on the
 * framework's DI container in a unit-test context. The handler methods are
 * exercised through real WordPress plumbing — admin-only AJAX `wp_die()`
 * intercepted by WP_Ajax_UnitTestCase, real nonces, real capability checks,
 * real boundary sanitisers — so every claim about the production path holds.
 *
 * @package PinkCrab\Comment_Moderation\Tests\Integration\Ajax
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Tests\Integration\Ajax;

use DateTimeImmutable;
use DateTimeZone;
use PinkCrab\Comment_Moderation\Application\Services\Rule_Validator;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Filter;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Repository;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Infrastructure\Persistence\Wpdb_Rule_Repository;
use PinkCrab\Comment_Moderation\Presentation\Ajax\Rule_Ajax_Controller;
use PinkCrab\Comment_Moderation\Presentation\Page\Admin_Page;
use PinkCrab\Perique\Application\App_Config;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * Exercises Rule_Ajax_Controller's seven endpoints — nonce + capability
 * preflight, sanitisation, validation, persistence and JSON response shape —
 * for create / update / delete / clear / list / get / show-more pagination
 * (rebuild spec §3, §6, §7, §8).
 *
 * @group integration
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class Test_Rule_Ajax_Controller extends WP_Ajax_UnitTestCase {

	/**
	 * Real wpdb-backed repository — exactly what runs in production.
	 */
	private Wpdb_Rule_Repository $repo;

	/**
	 * Subject under test.
	 */
	private Rule_Ajax_Controller $controller;

	/**
	 * Build a fresh wpdb-backed repository, an empty rules table, and the
	 * controller; log in as an administrator so the capability preflight
	 * passes by default.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$app_config = new App_Config(
			array(
				'path'       => array(
					'plugin' => '/var/www/plugin/',
					'assets' => '/var/www/plugin/assets/build',
					'view'   => '/var/www/plugin/views',
				),
				'url'        => array(
					'plugin' => 'https://example.org/wp-content/plugins/my-plugin/',
					'assets' => 'https://example.org/wp-content/plugins/my-plugin/assets/build',
					'view'   => 'https://example.org/wp-content/plugins/my-plugin/views',
				),
				'plugin'     => array( 'version' => '0.1.0' ),
				'namespaces' => array( 'rest' => 'pinkcrab-comment-moderation/v1' ),
				'db_tables'  => array( 'rules' => $wpdb->prefix . 'pccm_rules' ),
			)
		);

		$this->repo = new Wpdb_Rule_Repository( $wpdb, $app_config );
		$this->repo->clear();

		$this->controller = new Rule_Ajax_Controller(
			$this->repo,
			new Rule_Validator()
		);

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Read the JSON envelope the controller wrote into `$_last_response`. The
	 * wp_die exception thrown by `wp_send_json_*` is caught upstream so the
	 * body is always present by the time this is called.
	 *
	 * @return array<string,mixed>
	 */
	private function last_json(): array {
		$decoded = json_decode( $this->_last_response, true );
		$this->assertIsArray( $decoded, "Response was not JSON: {$this->_last_response}" );
		return $decoded;
	}

	/**
	 * Stash the payload onto $_POST with a valid nonce, invoke the named
	 * handler under output buffering, and return the decoded JSON envelope.
	 *
	 * @param callable            $handler Controller handler to call (e.g. `[$this->controller, 'handle_create']`).
	 * @param array<string,mixed> $payload POST body fields.
	 * @param boolean             $with_nonce When true (default) include a fresh nonce; pass false to test missing-nonce paths.
	 *
	 * @return array<string,mixed>
	 */
	private function call( callable $handler, array $payload = array(), bool $with_nonce = true ): array {
		if ( $with_nonce ) {
			$payload[ Rule_Ajax_Controller::NONCE_FIELD ] = wp_create_nonce( Rule_Ajax_Controller::NONCE_ACTION );
		}

		$_POST                = $payload;
		$_REQUEST             = $_POST;
		$this->_last_response = '';

		// Register our directly-built controller as the wp_ajax handler for a
		// throw-away action and dispatch through the framework's `_handleAjax`
		// helper. This mirrors WordPress's real admin-ajax.php flow — output
		// buffering, `wp_doing_ajax()`, and the `wp_die` interception are all
		// applied — but without going through Perique's DI container.
		$action = '__test_pccm_dispatch';
		add_action( 'wp_ajax_' . $action, $handler );
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_* always throws this in the test framework.
		} catch ( WPAjaxDieStopException $e ) {
			// Same, but for `wp_send_json_error` with no output before die.
		} finally {
			remove_all_actions( 'wp_ajax_' . $action );
		}

		return $this->last_json();
	}

	/**
	 * @testdox It should reject any AJAX call that arrives without a valid nonce so the admin endpoints cannot be CSRF'd
	 */
	public function test_missing_nonce_returns_security_failure(): void {
		$decoded = $this->call( array( $this->controller, 'handle_list' ), array(), false );

		$this->assertFalse( $decoded['success'] );
		$this->assertSame( 'Security check failed.', $decoded['data']['message'] );
	}

	/**
	 * @testdox It should refuse callers without the required capability even when a valid nonce is presented
	 */
	public function test_subscriber_cannot_call_endpoints(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$decoded = $this->call( array( $this->controller, 'handle_list' ) );

		$this->assertFalse( $decoded['success'] );
		$this->assertSame(
			'You do not have permission to manage moderation rules.',
			$decoded['data']['message']
		);
	}

	/**
	 * @testdox It should let a non-administrator through once the pccm_required_capability filter relaxes the gate
	 */
	public function test_capability_filter_broadens_to_moderate_comments(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		// `moderate_comments` is the editor's default cap that maps cleanly to
		// "anyone who can manage comments" — exactly the broadening rebuild
		// spec §2 envisages.
		$callback = static fn(): string => 'moderate_comments';
		add_filter( Admin_Page::FILTER_CAPABILITY, $callback );

		try {
			$decoded = $this->call( array( $this->controller, 'handle_list' ) );
		} finally {
			remove_filter( Admin_Page::FILTER_CAPABILITY, $callback );
		}

		$this->assertTrue(
			$decoded['success'],
			'Editor with moderate_comments should be admitted. Response: ' . wp_json_encode( $decoded )
		);
		$this->assertSame( 0, $decoded['data']['total'] );
	}

	/**
	 * @testdox It should be possible to create a regex rule via the create endpoint and find it persisted with fresh stats
	 */
	public function test_create_endpoint_persists_a_regex_rule(): void {
		$decoded = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'          => Rule_Type::REGEX,
				'name'          => 'Block casino',
				'description'   => 'Catches casino spam',
				'pattern'       => '/casino/i',
				'comment_parts' => array( 'content' ),
				'response'      => Response::SPAM,
			)
		);

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( 'Rule created', $decoded['data']['message'] );
		$this->assertNotNull( $decoded['data']['rule']['id'] );
		$this->assertSame( 'Block casino', $decoded['data']['rule']['name'] );

		$id    = (int) $decoded['data']['rule']['id'];
		$found = $this->repo->find( $id );
		$this->assertInstanceOf( Regex_Rule::class, $found );
		$this->assertSame( '/casino/i', $found->pattern() );
		$this->assertSame( 0, $found->usage_stats()->times_used() );
	}

	/**
	 * @testdox It should reject an invalid regex submission with the spec's human-readable error and not persist anything
	 */
	public function test_create_endpoint_surfaces_validator_errors(): void {
		$decoded = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'          => Rule_Type::REGEX,
				'pattern'       => '/[unclosed/',
				'comment_parts' => array( 'content' ),
				'response'      => Response::SPAM,
			)
		);

		$this->assertFalse( $decoded['success'] );
		$this->assertStringContainsString( 'is not a valid regex expression', $decoded['data']['message'] );
		$this->assertSame( 0, $this->repo->count( Rule_Filter::none() ) );
	}

	/**
	 * @testdox It should reject a regex rule submitted without any comment part ticked
	 */
	public function test_create_endpoint_rejects_regex_without_parts(): void {
		$decoded = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'          => Rule_Type::REGEX,
				'pattern'       => '/casino/i',
				'comment_parts' => array(),
				'response'      => Response::SPAM,
			)
		);

		$this->assertFalse( $decoded['success'] );
		$this->assertSame( 'Regex rules has no fields selected.', $decoded['data']['message'] );
	}

	/**
	 * @testdox It should be possible to update an existing rule's editable fields while preserving its accumulated usage stats
	 */
	public function test_update_endpoint_preserves_usage_stats(): void {
		$saved = $this->repo->save( $this->make_regex_rule() );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'pccm_rules',
			array(
				'times_used' => 7,
				'last_used'  => '2026-04-01 12:00:00',
			),
			array( 'id' => $saved->id() ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$decoded = $this->call(
			array( $this->controller, 'handle_update' ),
			array(
				'id'            => $saved->id(),
				'type'          => Rule_Type::REGEX,
				'name'          => 'Renamed',
				'description'   => 'Updated note',
				'pattern'       => '/(casino|gambling)/i',
				'comment_parts' => array( 'content', 'email' ),
				'response'      => Response::SPAM,
			)
		);

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( 'Rule updated', $decoded['data']['message'] );
		$this->assertSame( 7, $decoded['data']['rule']['usage_stats']['times_used'] );

		$reloaded = $this->repo->find( (int) $saved->id() );
		$this->assertInstanceOf( Regex_Rule::class, $reloaded );
		$this->assertSame( '/(casino|gambling)/i', $reloaded->pattern() );
		$this->assertSame( array( 'email', 'content' ), $reloaded->comment_parts()->values() );
		$this->assertSame( 7, $reloaded->usage_stats()->times_used() );
	}

	/**
	 * @testdox It should respond 404 when the update endpoint is called with a rule id that does not exist
	 */
	public function test_update_endpoint_reports_404_for_missing_rule(): void {
		$decoded = $this->call(
			array( $this->controller, 'handle_update' ),
			array(
				'id'            => 99999,
				'type'          => Rule_Type::REGEX,
				'pattern'       => '/casino/i',
				'comment_parts' => array( 'content' ),
				'response'      => Response::SPAM,
			)
		);

		$this->assertFalse( $decoded['success'] );
		$this->assertSame( 'Rule not found.', $decoded['data']['message'] );
	}

	/**
	 * @testdox It should be possible to delete a single rule by id via the delete endpoint and find the table no longer contains it
	 */
	public function test_delete_endpoint_removes_one_rule(): void {
		$saved = $this->repo->save( $this->make_regex_rule( array( 'name' => 'Doomed' ) ) );

		$decoded = $this->call(
			array( $this->controller, 'handle_delete' ),
			array( 'id' => $saved->id() )
		);

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( 'Rule Deleted', $decoded['data']['message'] );
		$this->assertTrue( $decoded['data']['removed'] );
		$this->assertNull( $this->repo->find( (int) $saved->id() ) );
	}

	/**
	 * @testdox It should be possible to clear every rule in one call via the clear endpoint and report the count cleared
	 */
	public function test_clear_endpoint_wipes_every_rule(): void {
		for ( $index = 1; $index <= 3; $index++ ) {
			$this->repo->save( $this->make_regex_rule( array( 'name' => sprintf( 'Rule %d', $index ) ) ) );
		}

		$decoded = $this->call( array( $this->controller, 'handle_clear' ) );

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( 'All rules cleared', $decoded['data']['message'] );
		$this->assertSame( 3, $decoded['data']['removed'] );
		$this->assertSame( 0, $this->repo->count( Rule_Filter::none() ) );
	}

	/**
	 * @testdox It should be possible to list the rules on the first page and report total + page_size + has_more so the UI can drive Show More
	 */
	public function test_list_endpoint_returns_first_page_with_pagination_metadata(): void {
		for ( $index = 1; $index <= 27; $index++ ) {
			$this->repo->save( $this->make_regex_rule( array( 'name' => sprintf( 'Rule %02d', $index ) ) ) );
		}

		$decoded = $this->call( array( $this->controller, 'handle_list' ), array( 'page' => 1 ) );

		$this->assertTrue( $decoded['success'] );
		$this->assertCount( Rule_Repository::PAGE_SIZE, $decoded['data']['rules'] );
		$this->assertSame( 27, $decoded['data']['total'] );
		$this->assertSame( Rule_Repository::PAGE_SIZE, $decoded['data']['page_size'] );
		$this->assertSame( Rule_Repository::PAGE_SIZE, $decoded['data']['visible'] );
		$this->assertTrue( $decoded['data']['has_more'] );
	}

	/**
	 * @testdox It should be possible to ask for page 2 of the list and get the remaining rules with has_more flipped to false
	 */
	public function test_list_endpoint_supports_show_more_pagination(): void {
		for ( $index = 1; $index <= 27; $index++ ) {
			$this->repo->save( $this->make_regex_rule( array( 'name' => sprintf( 'Rule %02d', $index ) ) ) );
		}

		$decoded = $this->call( array( $this->controller, 'handle_list' ), array( 'page' => 2 ) );

		$this->assertTrue( $decoded['success'] );
		$this->assertCount( 2, $decoded['data']['rules'] );
		$this->assertSame( 27, $decoded['data']['total'] );
		$this->assertSame( 27, $decoded['data']['visible'] );
		$this->assertFalse( $decoded['data']['has_more'] );
	}

	/**
	 * @testdox It should be possible to narrow the list with the search/types/parts/responses filter and get the count for that filter back
	 */
	public function test_list_endpoint_applies_filter_criteria(): void {
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Block casino', 'pattern' => '/casino/i' ) ) );
		$this->repo->save(
			$this->make_regex_rule(
				array(
					'name'     => 'Hold gambling',
					'pattern'  => '/gambling/i',
					'response' => Response::pending(),
				)
			)
		);

		$decoded = $this->call(
			array( $this->controller, 'handle_list' ),
			array(
				'search'    => 'gambl',
				'types'     => array( Rule_Type::REGEX ),
				'parts'     => array( Comment_Part::CONTENT ),
				'responses' => array( Response::PENDING ),
			)
		);

		$this->assertTrue( $decoded['success'] );
		$this->assertCount( 1, $decoded['data']['rules'] );
		$this->assertSame( 'Hold gambling', $decoded['data']['rules'][0]['name'] );
		$this->assertSame( 1, $decoded['data']['total'] );
	}

	/**
	 * @testdox It should be possible to use the get endpoint to deep-link to a rule for editing and receive the full payload back
	 */
	public function test_get_endpoint_returns_full_rule_payload(): void {
		$saved = $this->repo->save(
			$this->make_regex_rule(
				array(
					'name'          => 'Block casino',
					'description'   => 'Catches casino spam',
					'comment_parts' => Comment_Parts::of( Comment_Part::content(), Comment_Part::email() ),
				)
			)
		);

		$decoded = $this->call( array( $this->controller, 'handle_get' ), array( 'id' => $saved->id() ) );

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( $saved->id(), $decoded['data']['rule']['id'] );
		$this->assertSame( 'Block casino', $decoded['data']['rule']['name'] );
		$this->assertSame( '/casino/i', $decoded['data']['rule']['pattern'] );
		$this->assertSame( array( 'email', 'content' ), $decoded['data']['rule']['comment_parts'] );
		$this->assertSame( Response::SPAM, $decoded['data']['rule']['response'] );
	}

	/**
	 * @testdox It should respond 404 when the get endpoint is asked for a rule id that does not exist
	 */
	public function test_get_endpoint_reports_404_for_missing_rule(): void {
		$decoded = $this->call( array( $this->controller, 'handle_get' ), array( 'id' => 99999 ) );

		$this->assertFalse( $decoded['success'] );
		$this->assertSame( 'Rule not found.', $decoded['data']['message'] );
	}

	/**
	 * @testdox It should be possible to create a conditional rule via the recursive condition-tree payload and have the sanitiser preserve the tree shape
	 */
	public function test_create_endpoint_persists_a_conditional_rule(): void {
		$decoded = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'        => Rule_Type::CONDITIONAL,
				'name'        => 'Crypto trap',
				'description' => 'Multi-part trap',
				'response'    => Response::PENDING,
				'root'        => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => Comment_Part::EMAIL,
							'operator' => 'wildcard',
							'value'    => '*@gmail.com',
						),
						array(
							'combinator' => 'or',
							'children'   => array(
								array(
									'part'     => Comment_Part::CONTENT,
									'operator' => 'contains',
									'value'    => 'crypto',
								),
								array(
									'part'     => Comment_Part::CONTENT,
									'operator' => 'contains',
									'value'    => 'NFT',
								),
							),
						),
					),
				),
			)
		);

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( 'Rule created', $decoded['data']['message'] );
		$this->assertSame( 'conditional', $decoded['data']['rule']['type'] );

		$id    = (int) $decoded['data']['rule']['id'];
		$found = $this->repo->find( $id );
		$this->assertNotNull( $found );
		$this->assertSame(
			array( 'email', 'content' ),
			$found->comment_parts()->values(),
			'Nested condition tree should round-trip every part it touches.'
		);
	}

	/**
	 * @testdox It should accept a conditional rule whose root tree arrives as a single JSON-encoded form field (the Elm admin app's wire shape)
	 */
	public function test_create_endpoint_accepts_json_encoded_conditional_root(): void {
		// The Elm admin app posts the recursive condition tree as one form
		// field whose value is the JSON-encoded shape, rather than the
		// bracket-nested arrays PHP would parse natively. The boundary
		// sanitiser must decode the string before walking the tree, otherwise
		// every conditional rule created through the production UI would fail
		// validation with "Every condition group must contain at least one
		// condition".
		$decoded = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'        => Rule_Type::CONDITIONAL,
				'name'        => 'Elm-shape conditional',
				'description' => 'Posted with root as a JSON string',
				'response'    => Response::PENDING,
				'root'        => wp_json_encode(
					array(
						'combinator' => 'and',
						'children'   => array(
							array(
								'part'     => Comment_Part::EMAIL,
								'operator' => 'contains',
								'value'    => '@gmail.com',
							),
							array(
								'part'     => Comment_Part::CONTENT,
								'operator' => 'contains',
								'value'    => 'crypto',
							),
						),
					)
				),
			)
		);

		$this->assertTrue(
			$decoded['success'],
			'Elm-style JSON-string root should round-trip. Response: ' . wp_json_encode( $decoded )
		);
		$this->assertSame( 'Rule created', $decoded['data']['message'] );

		$id    = (int) $decoded['data']['rule']['id'];
		$found = $this->repo->find( $id );
		$this->assertNotNull( $found, 'Conditional rule should persist.' );
		$this->assertSame(
			array( 'email', 'content' ),
			$found->comment_parts()->values(),
			'Both leaf parts should be visible on the saved rule.'
		);
	}

	/**
	 * @testdox It should reject a request that supplies a non-empty but invalid nonce token via the pinkcrab/wp-nonce validate() path
	 */
	public function test_invalid_token_is_rejected_by_wp_nonce_lib(): void {
		// Supply a non-empty token so the controller takes the
		// `Nonce::validate()` branch (rather than skipping straight to the
		// `check_ajax_referer` fallback for missing-token requests). The token
		// is deliberately gibberish so both `wp_verify_nonce()` (called by the
		// wp-nonce library) AND the fallback `check_ajax_referer()` reject it,
		// proving the security gate cannot be bypassed by a forged token.
		$decoded = $this->call(
			array( $this->controller, 'handle_list' ),
			array( Rule_Ajax_Controller::NONCE_FIELD => 'not-a-real-token' ),
			false
		);

		$this->assertFalse( $decoded['success'] );
		$this->assertSame( 'Security check failed.', $decoded['data']['message'] );
		$this->assertSame( 0, $this->repo->count( Rule_Filter::none() ) );
	}

	/**
	 * @testdox It should sanitise the name, description, pattern and search inputs at the boundary so script/HTML payloads never reach the domain or the database
	 */
	public function test_create_endpoint_sanitises_boundary_inputs(): void {
		// Boundary-sanitisation contract: `sanitize_text_field` strips tags +
		// collapses whitespace on the name/pattern/search, `wp_kses_post`
		// permits safe inline markup in the description but drops <script>.
		$decoded = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'          => Rule_Type::REGEX,
				'name'          => "  Block <script>alert('xss')</script>casino  \nrule  ",
				'description'   => 'Catches <strong>casino</strong> <script>alert(1)</script>spam.',
				'pattern'       => '/casino/i',
				'comment_parts' => array( 'content' ),
				'response'      => Response::SPAM,
			)
		);

		$this->assertTrue( $decoded['success'], wp_json_encode( $decoded ) );

		$id      = (int) $decoded['data']['rule']['id'];
		$persist = $this->repo->find( $id );
		$this->assertInstanceOf( Regex_Rule::class, $persist );

		// `sanitize_text_field` strips `<script>` and its content, plus any
		// other tags, and normalises run-on whitespace to a single space.
		$saved_name = (string) $persist->name();
		$this->assertStringNotContainsString( '<script>', $saved_name );
		$this->assertStringNotContainsString( 'alert', $saved_name );
		$this->assertStringNotContainsString( "\n", $saved_name );
		$this->assertSame( trim( $saved_name ), $saved_name, 'Name should be trimmed at the boundary.' );

		// `wp_kses_post` keeps benign tags but drops `<script>` itself
		// (the text inside the tag may remain as plain text — that's the
		// documented kses behaviour; what matters is the executable tag is
		// gone, so the description cannot be injected back as live markup).
		$saved_desc = (string) $persist->description();
		$this->assertStringContainsString( '<strong>casino</strong>', $saved_desc );
		$this->assertStringNotContainsString( '<script', $saved_desc );
		$this->assertStringNotContainsString( '</script>', $saved_desc );
	}

	/**
	 * @testdox It should sanitise the list endpoint's search term at the boundary so XSS-y filter input cannot reach the SQL layer with markup intact
	 */
	public function test_list_endpoint_sanitises_search_filter(): void {
		// Pre-seed one matching rule so the assertion has something to assert.
		$this->repo->save( $this->make_regex_rule( array( 'name' => 'Block casino' ) ) );

		// Pass an HTML-bearing search term — the controller routes it through
		// `sanitize_text_field` before building the Rule_Filter. The endpoint
		// itself should still 200 OK and the search should still match the
		// seeded rule because the tags are stripped before the LIKE runs.
		$decoded = $this->call(
			array( $this->controller, 'handle_list' ),
			array( 'search' => '<script>alert(1)</script>casino' )
		);

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( 1, $decoded['data']['total'] );
		$this->assertSame( 'Block casino', $decoded['data']['rules'][0]['name'] );
	}

	/**
	 * @testdox It should round-trip a conditional rule through the deep-link get endpoint so the Elm app can re-open a complex tree for editing
	 */
	public function test_deep_link_get_returns_conditional_rule_tree(): void {
		// Build a multi-level conditional rule via the create endpoint (so
		// the same sanitiser + build path runs as in production), then deep-
		// link to it via handle_get — the spec's "Direct link to a rule" flow
		// (§6) drives this. The shape must round-trip exactly so the admin
		// app's add/edit pane re-hydrates the original tree.
		$created = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'        => Rule_Type::CONDITIONAL,
				'name'        => 'Deep-link target',
				'description' => 'Round-trip me',
				'response'    => Response::PENDING,
				'root'        => array(
					'combinator' => 'and',
					'children'   => array(
						array(
							'part'     => Comment_Part::EMAIL,
							'operator' => 'wildcard',
							'value'    => '*@gmail.com',
						),
						array(
							'combinator' => 'or',
							'children'   => array(
								array(
									'part'     => Comment_Part::CONTENT,
									'operator' => 'contains',
									'value'    => 'crypto',
								),
								array(
									'part'     => Comment_Part::CONTENT,
									'operator' => 'contains',
									'value'    => 'NFT',
								),
							),
						),
					),
				),
			)
		);
		$this->assertTrue( $created['success'], wp_json_encode( $created ) );
		$id = (int) $created['data']['rule']['id'];

		$decoded = $this->call(
			array( $this->controller, 'handle_get' ),
			array( 'id' => $id )
		);

		$this->assertTrue( $decoded['success'] );
		$rule = $decoded['data']['rule'];
		$this->assertSame( $id, $rule['id'] );
		$this->assertSame( 'conditional', $rule['type'] );
		$this->assertSame( 'Deep-link target', $rule['name'] );
		$this->assertSame( Response::PENDING, $rule['response'] );

		$this->assertSame( 'and', $rule['root']['combinator'] );
		$this->assertCount( 2, $rule['root']['children'] );

		// First child: leaf wildcard against email.
		$this->assertSame( Comment_Part::EMAIL, $rule['root']['children'][0]['part'] );
		$this->assertSame( 'wildcard', $rule['root']['children'][0]['operator'] );
		$this->assertSame( '*@gmail.com', $rule['root']['children'][0]['value'] );

		// Second child: nested OR group with two contains-content leaves.
		$nested = $rule['root']['children'][1];
		$this->assertSame( 'or', $nested['combinator'] );
		$this->assertCount( 2, $nested['children'] );
		$this->assertSame( Comment_Part::CONTENT, $nested['children'][0]['part'] );
		$this->assertSame( 'crypto', $nested['children'][0]['value'] );
		$this->assertSame( 'NFT', $nested['children'][1]['value'] );
	}

	/**
	 * @testdox It should be possible to create an IP range rule and have it stored with both endpoints intact
	 */
	public function test_create_endpoint_persists_an_ip_range_rule(): void {
		$decoded = $this->call(
			array( $this->controller, 'handle_create' ),
			array(
				'type'     => Rule_Type::IP_RANGE,
				'name'     => 'Quarantine block',
				'response' => Response::TRASH,
				'start_ip' => '203.0.113.10',
				'end_ip'   => '203.0.113.40',
			)
		);

		$this->assertTrue( $decoded['success'] );
		$this->assertSame( '203.0.113.10', $decoded['data']['rule']['start_ip'] );
		$this->assertSame( '203.0.113.40', $decoded['data']['rule']['end_ip'] );
	}

	/**
	 * Build a representative Regex_Rule for fixture setup.
	 *
	 * @param array<string,mixed> $overrides Optional field overrides.
	 *
	 * @return Regex_Rule
	 */
	private function make_regex_rule( array $overrides = array() ): Regex_Rule {
		$defaults = array(
			'id'            => null,
			'name'          => 'Block casino',
			'description'   => 'Catches casino spam',
			'pattern'       => '/casino/i',
			'comment_parts' => Comment_Parts::of( Comment_Part::content() ),
			'response'      => Response::spam(),
			'usage_stats'   => Usage_Stats::fresh( new DateTimeImmutable( '2026-01-01 00:00:00', new DateTimeZone( 'UTC' ) ) ),
		);
		$args     = array_replace( $defaults, $overrides );
		return new Regex_Rule(
			$args['id'],
			$args['name'],
			$args['description'],
			$args['pattern'],
			$args['comment_parts'],
			$args['response'],
			$args['usage_stats']
		);
	}
}
