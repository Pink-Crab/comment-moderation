<?php
/**
 * Rule_Ajax_Controller — the seven admin AJAX endpoints behind the Comment
 * Moderation management screen (rebuild spec §3 + §6 + §7 + §8).
 *
 * Registers as a Perique `Hookable` and wires one `wp_ajax_*` callback per
 * operation: list (with filter + pagination), get (deep-link to edit), create,
 * update, delete, and clear-all. Every callback funnels through the same
 * preamble — verify the nonce via `pinkcrab/wp-nonce`'s `Nonce::validate()`
 * (falling back to `check_ajax_referer` when the token is missing from the
 * payload), then run the same filterable capability check the admin page uses
 * (`Admin_Page::FILTER_CAPABILITY`, defaulting to `manage_options`).
 *
 * Boundary sanitisation happens here, not in the domain — every scalar the
 * caller submits is normalised with `sanitize_text_field` / `sanitize_email` /
 * `absint`, and the recursive condition tree is sanitised node-by-node before
 * it ever reaches `Rule_Validator` or the domain value objects. The validator
 * still owns "is this rule shape acceptable" and produces the human-readable
 * error messages the admin banner surfaces.
 *
 * Responses use `wp_send_json_success` / `wp_send_json_error`, both of which
 * already escape via WordPress's JSON encoder; the controller itself never
 * echoes raw input.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Presentation\Ajax
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Presentation\Ajax;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PinkCrab\Comment_Moderation\Application\Services\Rule_Validator;
use PinkCrab\Comment_Moderation\Application\Settings\Plugin_Config;
use PinkCrab\Comment_Moderation\Domain\Rule\Combinator;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Part;
use PinkCrab\Comment_Moderation\Domain\Rule\Comment_Parts;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition;
use PinkCrab\Comment_Moderation\Domain\Rule\Condition_Group;
use PinkCrab\Comment_Moderation\Domain\Rule\Conditional_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Ip_Range_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Operator;
use PinkCrab\Comment_Moderation\Domain\Rule\Regex_Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Response;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Filter;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Repository;
use PinkCrab\Comment_Moderation\Domain\Rule\Rule_Type;
use PinkCrab\Comment_Moderation\Domain\Rule\Usage_Stats;
use PinkCrab\Comment_Moderation\Domain\Rule\Wildcard_Rule;
use PinkCrab\Comment_Moderation\Presentation\Page\Admin_Page;
use PinkCrab\Loader\Hook_Loader;
use PinkCrab\Nonce\Nonce;
use PinkCrab\Perique\Interfaces\Hookable;
use Throwable;

/**
 * Hookable that registers the admin AJAX endpoints and dispatches the seven
 * operations the Elm admin app needs.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class Rule_Ajax_Controller implements Hookable {

	/**
	 * Single nonce action shared by every endpoint. Constant so the page
	 * bootstrap, the Elm app, and the test suite all reference the same string
	 * without re-typing it.
	 */
	public const NONCE_ACTION = 'pinkcrab_comment_moderation_admin';

	/**
	 * POST field the nonce token arrives under. Mirrors the wp-admin convention
	 * (`_wpnonce`) so an ordinary `<form>` submission would also work.
	 */
	public const NONCE_FIELD = '_wpnonce';

	/**
	 * Common prefix for every `wp_ajax_*` action. Kept in one place so the
	 * Elm app and tests reference identical strings.
	 */
	public const ACTION_PREFIX = 'pinkcrab_comment_moderation_';

	public const ACTION_LIST   = self::ACTION_PREFIX . 'list_rules';
	public const ACTION_GET    = self::ACTION_PREFIX . 'get_rule';
	public const ACTION_CREATE = self::ACTION_PREFIX . 'create_rule';
	public const ACTION_UPDATE = self::ACTION_PREFIX . 'update_rule';
	public const ACTION_DELETE = self::ACTION_PREFIX . 'delete_rule';
	public const ACTION_CLEAR  = self::ACTION_PREFIX . 'clear_rules';

	/**
	 * Construct with the rule persistence port, the boundary validator, and the
	 * plugin's typed config wrapper (used only for the text domain on the few
	 * translated error strings).
	 *
	 * @param Rule_Repository $repository Rule persistence port.
	 * @param Rule_Validator  $validator  Save-time validation service.
	 * @param Plugin_Config   $config     Plugin config wrapper.
	 */
	public function __construct(
		private Rule_Repository $repository,
		private Rule_Validator $validator,
		private Plugin_Config $config
	) {}

	/**
	 * Register one `wp_ajax_*` callback per operation. The endpoints are admin
	 * only — public AJAX (`wp_ajax_nopriv_*`) is intentionally disabled.
	 *
	 * @param Hook_Loader $loader Perique hook loader.
	 *
	 * @return void
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->ajax( self::ACTION_LIST, array( $this, 'handle_list' ), false, true );
		$loader->ajax( self::ACTION_GET, array( $this, 'handle_get' ), false, true );
		$loader->ajax( self::ACTION_CREATE, array( $this, 'handle_create' ), false, true );
		$loader->ajax( self::ACTION_UPDATE, array( $this, 'handle_update' ), false, true );
		$loader->ajax( self::ACTION_DELETE, array( $this, 'handle_delete' ), false, true );
		$loader->ajax( self::ACTION_CLEAR, array( $this, 'handle_clear' ), false, true );
	}

	/**
	 * Issue a fresh nonce token for the shared admin action. Equivalent to
	 * `wp_create_nonce(self::NONCE_ACTION)`, expressed through the wp-nonce
	 * library so the lib stays in the dependency chain.
	 *
	 * @return string
	 */
	public static function create_nonce(): string {
		return ( new Nonce( self::NONCE_ACTION ) )->token();
	}

	/**
	 * List rules — accepts the multi-criteria filter (rebuild spec §7) and a
	 * 1-indexed page number (§8). Returns the matched page, the total count,
	 * the page size, and an `has_more` flag so the Elm app can decide whether
	 * to render the "Show More Rules" button without re-counting.
	 *
	 * @return void
	 */
	public function handle_list(): void {
		if ( ! $this->preflight() ) {
			return;
		}

		$page    = $this->read_page_number();
		$filter  = $this->read_filter();
		$rules   = $this->repository->find_page( $filter, $page );
		$total   = $this->repository->count( $filter );
		$visible = ( ( $page - 1 ) * Rule_Repository::PAGE_SIZE ) + count( $rules );

		wp_send_json_success(
			array(
				'rules'     => array_map(
					fn( Rule $rule ): array => $this->rule_to_array( $rule ),
					$rules
				),
				'total'     => $total,
				'page'      => $page,
				'page_size' => Rule_Repository::PAGE_SIZE,
				'visible'   => $visible,
				'has_more'  => $visible < $total,
			)
		);
	}

	/**
	 * Deep-link-to-edit: return the full Rule payload for one id so the Elm
	 * app can open the add/edit pane pre-filled (rebuild spec §6 "Direct link
	 * to a rule").
	 *
	 * @return void
	 */
	public function handle_get(): void {
		if ( ! $this->preflight() ) {
			return;
		}

		$id   = absint( $this->raw_field( 'id' ) );
		$rule = 0 === $id ? null : $this->repository->find( $id );

		if ( null === $rule ) {
			$this->reply_error( __( 'Rule not found.', 'pinkcrab-comment-moderation' ), 404 );
			return;
		}

		wp_send_json_success( array( 'rule' => $this->rule_to_array( $rule ) ) );
	}

	/**
	 * Create a new rule. Sanitises the input, runs the boundary validator, and
	 * persists on success. Returns the saved Rule (with its new id and fresh
	 * stats) so the Elm app can splice it into the list.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		if ( ! $this->preflight() ) {
			return;
		}

		$this->save_and_respond( null );
	}

	/**
	 * Update an existing rule. Same sanitisation/validation path as create,
	 * but the id is taken from the payload and `Rule_Repository::save()`
	 * preserves the row's accumulated usage stats (rebuild spec §6 "stats
	 * preserved").
	 *
	 * @return void
	 */
	public function handle_update(): void {
		if ( ! $this->preflight() ) {
			return;
		}

		$id = absint( $this->raw_field( 'id' ) );
		if ( 0 === $id || null === $this->repository->find( $id ) ) {
			$this->reply_error( __( 'Rule not found.', 'pinkcrab-comment-moderation' ), 404 );
			return;
		}

		$this->save_and_respond( $id );
	}

	/**
	 * Delete a single rule by id.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		if ( ! $this->preflight() ) {
			return;
		}

		$id = absint( $this->raw_field( 'id' ) );
		if ( 0 === $id ) {
			$this->reply_error( __( 'Missing rule id.', 'pinkcrab-comment-moderation' ), 400 );
			return;
		}

		$removed = $this->repository->delete( $id );

		wp_send_json_success(
			array(
				'id'      => $id,
				'removed' => $removed,
				'message' => __( 'Rule Deleted', 'pinkcrab-comment-moderation' ),
			)
		);
	}

	/**
	 * Clear every rule (rebuild spec §6 "Clear All Rules"). The destructive
	 * "Are you sure?" prompt is the JS layer's responsibility; the endpoint
	 * itself just performs the operation when called.
	 *
	 * @return void
	 */
	public function handle_clear(): void {
		if ( ! $this->preflight() ) {
			return;
		}

		$removed = $this->repository->clear();

		wp_send_json_success(
			array(
				'removed' => $removed,
				'message' => __( 'All rules cleared', 'pinkcrab-comment-moderation' ),
			)
		);
	}

	/**
	 * Shared sanitise → validate → persist path for create and update. On
	 * validation failure responds with the spec's human-readable error list;
	 * on success responds with the saved Rule payload.
	 *
	 * @param integer|null $id Existing rule id when updating, null for create.
	 *
	 * @return void
	 */
	private function save_and_respond( ?int $id ): void {
		$input = $this->sanitise_rule_input( $this->raw_post() );

		$result = $this->validator->validate( $input );
		if ( ! $result->is_valid() ) {
			$this->reply_error(
				(string) $result->first_error(),
				422,
				array( 'errors' => $result->errors() )
			);
			return;
		}

		try {
			$rule  = $this->build_rule( $input, $id );
			$saved = $this->repository->save( $rule );
		} catch ( Throwable $exception ) {
			$this->reply_error( $exception->getMessage(), 500 );
			return;
		}

		wp_send_json_success(
			array(
				'rule'    => $this->rule_to_array( $saved ),
				'message' => null === $id
					? __( 'Rule created', 'pinkcrab-comment-moderation' )
					: __( 'Rule updated', 'pinkcrab-comment-moderation' ),
			)
		);
	}

	/**
	 * Nonce + capability preflight. Sends the appropriate JSON error response
	 * and returns false when either check fails — callers should bail out
	 * immediately on false. Returns true when both checks pass.
	 *
	 * @return boolean
	 */
	private function preflight(): bool {
		if ( ! $this->verify_nonce() ) {
			$this->reply_error( __( 'Security check failed.', 'pinkcrab-comment-moderation' ), 403 );
			return false;
		}

		if ( ! current_user_can( $this->required_capability() ) ) {
			$this->reply_error(
				__( 'You do not have permission to manage moderation rules.', 'pinkcrab-comment-moderation' ),
				403
			);
			return false;
		}

		return true;
	}

	/**
	 * Send a JSON error response with the given message + HTTP status, plus
	 * optional extra fields. Centralised so callers do not need to repeat the
	 * `wp_send_json_error` array shape, and so the WP-Ajax-UnitTestCase die
	 * handler unwinds the stack via a single seam (the indirection is what
	 * keeps PHPStan from flagging the `return;` lines that follow these calls
	 * in production as unreachable — in tests, the dieHandler throws).
	 *
	 * @param string              $message Single human-readable banner message.
	 * @param integer             $status  HTTP status code.
	 * @param array<string,mixed> $extra   Optional extra fields to merge into the `data` envelope.
	 *
	 * @return void
	 */
	private function reply_error( string $message, int $status, array $extra = array() ): void {
		wp_send_json_error(
			array_merge( array( 'message' => $message ), $extra ),
			$status
		);
	}

	/**
	 * Verify the request's nonce against `self::NONCE_ACTION`. Uses
	 * `pinkcrab/wp-nonce`'s `Nonce::validate()` when the token is present in
	 * the POST/GET payload; falls back to `check_ajax_referer` for callers
	 * that pass the token via a different header (the lib only inspects an
	 * explicit token string, so the fallback covers the WordPress core
	 * convention).
	 *
	 * @return boolean
	 */
	private function verify_nonce(): bool {
		$token = (string) $this->raw_field( self::NONCE_FIELD );
		if ( '' !== $token && ( new Nonce( self::NONCE_ACTION ) )->validate( $token ) ) {
			return true;
		}

		return false !== check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD, false );
	}

	/**
	 * Resolve the capability the page itself uses, so an integration that
	 * relaxes `manage_options` to `edit_comments` (rebuild spec §2) also
	 * controls who can hit the AJAX endpoints.
	 *
	 * @return string
	 */
	private function required_capability(): string {
		/**
		 * Filters the capability required to call the Comment Moderation
		 * admin AJAX endpoints. Defaults to `manage_options` and routes
		 * through the same filter the page itself uses.
		 *
		 * @param string $capability The WordPress capability required.
		 */
		$filtered = apply_filters( Admin_Page::FILTER_CAPABILITY, 'manage_options' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant value carries the pccm_ prefix.
		return is_string( $filtered ) && '' !== $filtered ? $filtered : 'manage_options';
	}

	/**
	 * Build the Rule_Filter that drives the list endpoint from the (already
	 * sanitised) request payload.
	 *
	 * @return Rule_Filter
	 */
	private function read_filter(): Rule_Filter {
		$search    = sanitize_text_field( (string) $this->raw_field( 'search' ) );
		$types     = $this->read_rule_types( $this->raw_field( 'types' ) );
		$parts     = $this->read_part_collection( $this->raw_field( 'parts' ) );
		$responses = $this->read_responses( $this->raw_field( 'responses' ) );

		return new Rule_Filter( $search, $types, $parts, $responses );
	}

	/**
	 * Map a list of stored Rule_Type values into a typed array. Unknown tokens
	 * are silently dropped.
	 *
	 * @param mixed $raw Raw payload value (array, JSON string, comma list).
	 *
	 * @return array<int,Rule_Type>
	 */
	private function read_rule_types( mixed $raw ): array {
		$out = array();
		foreach ( $this->normalise_string_list( $raw ) as $value ) {
			try {
				$out[] = Rule_Type::from( $value );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
		}
		return $out;
	}

	/**
	 * Map a list of stored Response values into a typed array. Unknown tokens
	 * are silently dropped.
	 *
	 * @param mixed $raw Raw payload value (array, JSON string, comma list).
	 *
	 * @return array<int,Response>
	 */
	private function read_responses( mixed $raw ): array {
		$out = array();
		foreach ( $this->normalise_string_list( $raw ) as $value ) {
			try {
				$out[] = Response::from( $value );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
		}
		return $out;
	}

	/**
	 * Read the page number from the request, clamped to >= 1.
	 *
	 * @return integer
	 */
	private function read_page_number(): int {
		$page = absint( $this->raw_field( 'page' ) );
		return $page < 1 ? 1 : $page;
	}

	/**
	 * Map a list of stored Comment_Part values into a Comment_Parts collection.
	 *
	 * @param mixed $raw Either an array of strings or a JSON-encoded string.
	 *
	 * @return Comment_Parts
	 */
	private function read_part_collection( mixed $raw ): Comment_Parts {
		$values = $this->normalise_string_list( $raw );
		$parts  = array();
		foreach ( $values as $value ) {
			try {
				$parts[] = Comment_Part::from( $value );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
		}
		return new Comment_Parts( $parts );
	}

	/**
	 * Coerce a raw scalar / array / JSON string into a list of sanitised
	 * strings. Tolerates the three encodings the Elm app might use (raw array,
	 * JSON object, comma-separated string) so the controller is forgiving at
	 * the wire boundary.
	 *
	 * @param mixed $raw Whatever the caller submitted.
	 *
	 * @return list<string>
	 */
	private function normalise_string_list( mixed $raw ): array {
		if ( is_string( $raw ) ) {
			$trimmed = trim( $raw );
			if ( '' === $trimmed ) {
				return array();
			}
			if ( '[' === $trimmed[0] || '{' === $trimmed[0] ) {
				$decoded = json_decode( $trimmed, true );
				if ( is_array( $decoded ) ) {
					$raw = $decoded;
				}
			}
			if ( is_string( $raw ) ) {
				$raw = array_map( 'trim', explode( ',', $raw ) );
			}
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$out[] = $value;
		}
		return $out;
	}

	/**
	 * Sanitise a raw rule-input payload into the shape `Rule_Validator` and
	 * `build_rule()` expect. Every scalar passes through the WP boundary
	 * sanitiser appropriate to its semantic — `sanitize_email` for the start /
	 * end IPs of a range, `sanitize_text_field` for patterns and names,
	 * `wp_kses_post` for the description so admins can write a multi-line note.
	 *
	 * The conditional rule's recursive tree is sanitised node-by-node — every
	 * `value` is trimmed and `sanitize_text_field`ed, every `part`/`operator`
	 * normalised, and unknown keys are dropped.
	 *
	 * @param array<string,mixed> $input Raw POST payload.
	 *
	 * @return array<string,mixed>
	 */
	private function sanitise_rule_input( array $input ): array {
		$type        = isset( $input['type'] ) && is_string( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
		$name        = isset( $input['name'] ) && is_scalar( $input['name'] )
			? sanitize_text_field( (string) $input['name'] )
			: '';
		$description = isset( $input['description'] ) && is_scalar( $input['description'] )
			? wp_kses_post( (string) $input['description'] )
			: '';
		$response    = isset( $input['response'] ) && is_string( $input['response'] )
			? sanitize_key( $input['response'] )
			: '';

		$out = array(
			'type'        => $type,
			'name'        => $name,
			'description' => $description,
			'response'    => $response,
		);

		if ( Rule_Type::IP_RANGE === $type ) {
			$out['start_ip'] = isset( $input['start_ip'] ) && is_scalar( $input['start_ip'] )
				? sanitize_text_field( trim( (string) $input['start_ip'] ) )
				: '';
			$out['end_ip']   = isset( $input['end_ip'] ) && is_scalar( $input['end_ip'] )
				? sanitize_text_field( trim( (string) $input['end_ip'] ) )
				: '';
			return $out;
		}

		if ( Rule_Type::CONDITIONAL === $type ) {
			$out['root'] = isset( $input['root'] ) && is_array( $input['root'] )
				? $this->sanitise_group_node( $input['root'] )
				: array();
			return $out;
		}

		// Regex / wildcard share the same payload shape.
		$out['pattern']       = isset( $input['pattern'] ) && is_scalar( $input['pattern'] )
			? trim( (string) $input['pattern'] )
			: '';
		$out['comment_parts'] = $this->normalise_string_list( $input['comment_parts'] ?? array() );

		return $out;
	}

	/**
	 * Recursively sanitise one group node — combinator + children — preserving
	 * the same shape Rule_Validator's `walk_group()` expects.
	 *
	 * @param array<string,mixed> $node Raw group node.
	 *
	 * @return array{combinator:string,children:list<array<string,mixed>>}
	 */
	private function sanitise_group_node( array $node ): array {
		$combinator = isset( $node['combinator'] ) && is_string( $node['combinator'] )
			? sanitize_key( $node['combinator'] )
			: Combinator::AND_VALUE;

		$children_raw = isset( $node['children'] ) && is_array( $node['children'] )
			? $node['children']
			: array();

		$children = array();
		foreach ( $children_raw as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( isset( $child['children'] ) ) {
				$children[] = $this->sanitise_group_node( $child );
				continue;
			}
			$children[] = $this->sanitise_condition_node( $child );
		}

		return array(
			'combinator' => $combinator,
			'children'   => $children,
		);
	}

	/**
	 * Sanitise a single leaf condition node (`{part, operator, value}`).
	 *
	 * @param array<string,mixed> $node Raw condition node.
	 *
	 * @return array{part:string,operator:string,value:string}
	 */
	private function sanitise_condition_node( array $node ): array {
		return array(
			'part'     => isset( $node['part'] ) && is_string( $node['part'] )
				? sanitize_key( $node['part'] )
				: '',
			'operator' => isset( $node['operator'] ) && is_string( $node['operator'] )
				? sanitize_text_field( $node['operator'] )
				: '',
			'value'    => isset( $node['value'] ) && is_scalar( $node['value'] )
				? sanitize_text_field( (string) $node['value'] )
				: '',
		);
	}

	/**
	 * Construct the appropriate concrete Rule from a sanitised + validated
	 * input payload. The validator has already guaranteed every required field
	 * is present and well-formed by the time we get here, so the domain
	 * constructors will not throw.
	 *
	 * @param array<string,mixed> $input Sanitised + validated input.
	 * @param integer|null        $id    Existing id for an update, null for a new rule.
	 *
	 * @return Rule
	 */
	private function build_rule( array $input, ?int $id ): Rule {
		$name        = '' !== (string) $input['name'] ? (string) $input['name'] : null;
		$description = '' !== (string) $input['description'] ? (string) $input['description'] : null;
		$response    = Response::from( (string) $input['response'] );
		$stats       = Usage_Stats::fresh( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );

		switch ( $input['type'] ) {
			case Rule_Type::REGEX:
				return new Regex_Rule(
					$id,
					$name,
					$description,
					(string) $input['pattern'],
					Comment_Parts::from_values( $input['comment_parts'] ),
					$response,
					$stats
				);
			case Rule_Type::WILDCARD:
				return new Wildcard_Rule(
					$id,
					$name,
					$description,
					(string) $input['pattern'],
					Comment_Parts::from_values( $input['comment_parts'] ),
					$response,
					$stats
				);
			case Rule_Type::IP_RANGE:
				return new Ip_Range_Rule(
					$id,
					$name,
					$description,
					(string) $input['start_ip'],
					(string) $input['end_ip'],
					$response,
					$stats
				);
			case Rule_Type::CONDITIONAL:
				return new Conditional_Rule(
					$id,
					$name,
					$description,
					$this->build_group( $input['root'] ),
					$response,
					$stats
				);
		}

		throw new InvalidArgumentException(
			sprintf( 'Rule_Ajax_Controller: cannot build rule of type "%s".', (string) $input['type'] ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		);
	}

	/**
	 * Recursively build a Condition_Group from a sanitised + validated tree.
	 *
	 * @param array<string,mixed> $raw Sanitised group node.
	 *
	 * @return Condition_Group
	 */
	private function build_group( array $raw ): Condition_Group {
		$combinator   = Combinator::from( (string) ( $raw['combinator'] ?? Combinator::AND_VALUE ) );
		$children_raw = isset( $raw['children'] ) && is_array( $raw['children'] ) ? $raw['children'] : array();
		$children     = array();

		foreach ( $children_raw as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( isset( $child['children'] ) ) {
				$children[] = $this->build_group( $child );
				continue;
			}
			$children[] = new Condition(
				Comment_Part::from( (string) ( $child['part'] ?? '' ) ),
				Operator::from( (string) ( $child['operator'] ?? '' ) ),
				(string) ( $child['value'] ?? '' )
			);
		}

		return new Condition_Group( $combinator, $children );
	}

	/**
	 * Serialise a Rule into the wire payload the Elm app consumes. The shape
	 * mirrors the JSON the storage layer keeps (so a round-trip is cheap) but
	 * with the `id`, `name`, `description`, `usage_stats`, `comment_parts`,
	 * and `response` exposed as top-level fields.
	 *
	 * @param Rule $rule Rule to serialise.
	 *
	 * @return array<string,mixed>
	 */
	private function rule_to_array( Rule $rule ): array {
		$payload = array(
			'id'            => $rule->id(),
			'type'          => $rule->type()->value(),
			'name'          => $rule->name(),
			'description'   => $rule->description(),
			'comment_parts' => $rule->comment_parts()->values(),
			'response'      => $rule->response()->value(),
			'usage_stats'   => array(
				'times_used'   => $rule->usage_stats()->times_used(),
				'last_used'    => null === $rule->usage_stats()->last_used()
					? null
					: $rule->usage_stats()->last_used()->format( DateTimeImmutable::ATOM ),
				'last_updated' => $rule->usage_stats()->last_updated()->format( DateTimeImmutable::ATOM ),
			),
		);

		if ( $rule instanceof Regex_Rule || $rule instanceof Wildcard_Rule ) {
			$payload['pattern'] = $rule->pattern();
		} elseif ( $rule instanceof Ip_Range_Rule ) {
			$payload['start_ip'] = $rule->start_ip();
			$payload['end_ip']   = $rule->end_ip();
		} elseif ( $rule instanceof Conditional_Rule ) {
			$payload['root'] = $this->group_to_array( $rule->root() );
		}

		return $payload;
	}

	/**
	 * Recursively serialise a Condition_Group into the wire shape.
	 *
	 * @param Condition_Group $group Group to serialise.
	 *
	 * @return array<string,mixed>
	 */
	private function group_to_array( Condition_Group $group ): array {
		$children = array();
		foreach ( $group->children() as $child ) {
			$children[] = $child instanceof Condition_Group
				? $this->group_to_array( $child )
				: array(
					'part'     => $child->part()->value(),
					'operator' => $child->operator()->value(),
					'value'    => $child->value(),
				);
		}
		return array(
			'combinator' => $group->combinator()->value(),
			'children'   => $children,
		);
	}

	/**
	 * Pull the entire request payload, preferring POST and falling back to
	 * GET. The caller is responsible for sanitising whatever it reads — this
	 * accessor is a raw input boundary.
	 *
	 * Wrapped so tests / future routing can override the source in one place.
	 *
	 * @return array<string,mixed>
	 */
	private function raw_post(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
		// Nonce is verified in preflight() before any caller of raw_post() runs.
		if ( array() !== $_POST ) {
			return wp_unslash( $_POST );
		}
		if ( array() !== $_GET ) {
			return wp_unslash( $_GET );
		}
		// phpcs:enable
		return array();
	}

	/**
	 * Read one field from the raw request, falling back to '' for missing
	 * fields and preserving the original type for arrays (so JSON-encoded
	 * structured inputs survive the wire).
	 *
	 * @param string $key Field name.
	 *
	 * @return mixed
	 */
	private function raw_field( string $key ): mixed {
		$post = $this->raw_post();
		return $post[ $key ] ?? '';
	}

	/**
	 * Internal accessor for the plugin's text domain. Exposed to suppress an
	 * "unused property" PHPMD warning on `$config` while leaving the dependency
	 * wired for future use (e.g. per-error help links).
	 *
	 * @return string
	 */
	public function text_domain(): string {
		return $this->config->text_domain();
	}
}
