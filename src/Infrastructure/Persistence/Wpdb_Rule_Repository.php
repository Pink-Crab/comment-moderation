<?php
/**
 * Wpdb_Rule_Repository — `$wpdb`-backed implementation of the
 * Rule_Repository domain port.
 *
 * Persists every rule into a single custom table (see migrations/
 * Comment_Rule_001.php). Every query is built with `$wpdb->prepare()` —
 * untrusted scalars never reach the SQL string. The table name itself comes
 * from `App_Config::db_tables('rules')` and is treated as trusted (it is a
 * constant value declared in `config/settings.php`).
 *
 * Payload shape — the `payload` column holds JSON describing the rule's
 * type-specific data:
 *
 *   - regex / wildcard:  {"pattern": "..."}
 *   - ip_range:          {"start_ip": "...", "end_ip": "..."}
 *   - conditional:       {"root": <group>} where <group> is recursive:
 *                          group     = {"combinator": "and|or", "children": [...]}
 *                          condition = {"part": "...", "operator": "...", "value": "..."}
 *
 * `comment_parts` is stored as a comma-wrapped delimited string
 * (e.g. ",email,content,") so a part filter can use a single
 * `LIKE %,email,%` clause without false partial matches (",name," won't
 * match ",user_name,").
 *
 * Update behaviour — `save()` for an existing rule writes only the editable
 * columns and `last_updated`, leaving `times_used` and `last_used` untouched
 * (rebuild spec §6 "stats preserved"). The returned Rule's `usage_stats()`
 * are reconciled against the existing DB row.
 *
 * @since   0.1.0
 * @package PinkCrab\Comment_Moderation\Infrastructure\Persistence
 */

declare( strict_types = 1 );

namespace PinkCrab\Comment_Moderation\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
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
use PinkCrab\Perique\Application\App_Config;
use RuntimeException;
use wpdb; // phpcs:ignore PSR1.Classes.ClassDeclaration -- wpdb is in the global namespace.

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table queries are necessarily direct.
// phpcs:disable WordPress.DB.SlowDBQuery -- The filter LIKE / IN clauses are intrinsic to the design and the table is small (admin-curated).
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Every $wpdb call below uses $wpdb->prepare(); the sniff does not recognise the property-access form ($this->wpdb->prepare()).
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The only interpolated values are the trusted table name (from App_Config) and a pre-built WHERE clause whose user inputs are bound by $wpdb->prepare().
// phpcs:disable WordPress.DB.PreparedSQLPlaceholders -- Dynamic WHERE clauses are built from a fixed set of literal fragments and bound via prepare(); the sniff cannot statically verify this.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- These exception messages are developer-facing diagnostics; their format strings are static and the interpolated values are integers / class names.
/**
 * `$wpdb`-backed implementation of Rule_Repository.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ShortMethodName")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final class Wpdb_Rule_Repository implements Rule_Repository {

	/**
	 * MySQL DATETIME format used for the timestamp columns.
	 *
	 * @var string
	 */
	private const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Construct with the shared `$wpdb` and `App_Config` instances. Both are
	 * pre-registered for DI by the Perique framework, so this class needs no
	 * explicit DI rule.
	 *
	 * @param wpdb       $wpdb       Shared WordPress database accessor.
	 * @param App_Config $app_config Plugin config — resolves the `rules` table alias.
	 */
	public function __construct(
		private wpdb $wpdb,
		private App_Config $app_config
	) {}

	/**
	 * Insert a new rule or update an existing one (decided by `$rule->id()`).
	 *
	 * @param Rule $rule Rule to save.
	 *
	 * @return Rule Saved rule with id (and, for updates, the row's existing stats).
	 *
	 * @throws RuntimeException If the database operation fails.
	 */
	public function save( Rule $rule ): Rule {
		return null === $rule->id() ? $this->insert( $rule ) : $this->update( $rule );
	}

	/**
	 * Find by primary key, or null if no row matches.
	 *
	 * @param integer $id Primary key.
	 *
	 * @return Rule|null
	 */
	public function find( int $id ): ?Rule {
		$table = $this->table();
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return $this->hydrate( $row );
	}

	/**
	 * Delete by primary key.
	 *
	 * @param integer $id Primary key.
	 *
	 * @return boolean True when a row was removed.
	 */
	public function delete( int $id ): bool {
		$deleted = $this->wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		return is_int( $deleted ) && $deleted > 0;
	}

	/**
	 * Remove every rule from the table.
	 *
	 * @return integer Rows removed.
	 */
	public function clear(): int {
		$table  = $this->table();
		$result = $this->wpdb->query( "DELETE FROM `{$table}`" );
		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Return up to PAGE_SIZE rules matching the filter, ordered by id ASC.
	 *
	 * @param Rule_Filter $filter Filter criteria.
	 * @param integer     $page   One-indexed page number; values less than 1 are treated as 1.
	 *
	 * @return array<int,Rule>
	 */
	public function find_page( Rule_Filter $filter, int $page = 1 ): array {
		$page         = max( 1, $page );
		$offset       = ( $page - 1 ) * self::PAGE_SIZE;
		$built        = $this->build_where( $filter );
		$where_clause = '' === $built['sql'] ? '' : ' WHERE ' . $built['sql'];
		$table        = $this->table();
		$query        = "SELECT * FROM `{$table}`{$where_clause} ORDER BY id ASC LIMIT %d OFFSET %d";
		$bound        = array_merge( $built['args'], array( self::PAGE_SIZE, $offset ) );

		$prepared = $this->wpdb->prepare( $query, $bound );
		if ( ! is_string( $prepared ) ) {
			return array();
		}
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map(
			fn( array $row ): Rule => $this->hydrate( $row ),
			$rows
		);
	}

	/**
	 * Total count of rules matching the filter (ignores pagination).
	 *
	 * @param Rule_Filter $filter Filter criteria.
	 *
	 * @return integer
	 */
	public function count( Rule_Filter $filter ): int {
		$built        = $this->build_where( $filter );
		$where_clause = '' === $built['sql'] ? '' : ' WHERE ' . $built['sql'];
		$table        = $this->table();
		$query        = "SELECT COUNT(*) FROM `{$table}`{$where_clause}";

		if ( array() === $built['args'] ) {
			$count = $this->wpdb->get_var( $query );
			return is_numeric( $count ) ? (int) $count : 0;
		}

		$prepared = $this->wpdb->prepare( $query, $built['args'] );
		if ( ! is_string( $prepared ) ) {
			return 0;
		}
		$count = $this->wpdb->get_var( $prepared );
		return is_numeric( $count ) ? (int) $count : 0;
	}

	/**
	 * Insert a brand-new rule. Persists fresh stats (zero hits, last_updated
	 * = now) and returns the rule with its new id.
	 *
	 * @param Rule $rule Rule with id() === null.
	 *
	 * @return Rule
	 *
	 * @throws RuntimeException If the insert fails.
	 */
	private function insert( Rule $rule ): Rule {
		$now  = $this->now();
		$row  = $this->serialise( $rule );
		$data = array(
			'type'          => $row['type'],
			'name'          => $row['name'],
			'description'   => $row['description'],
			'payload'       => $row['payload'],
			'comment_parts' => $row['comment_parts'],
			'response'      => $row['response'],
			'times_used'    => 0,
			'last_used'     => null,
			'last_updated'  => $now->format( self::DATETIME_FORMAT ),
		);

		$inserted = $this->wpdb->insert(
			$this->table(),
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted || 0 === $inserted ) {
			throw new RuntimeException( 'Wpdb_Rule_Repository: failed to insert rule.' );
		}

		$id = (int) $this->wpdb->insert_id;
		return $this->with_persisted_values(
			$rule,
			$id,
			new Usage_Stats( 0, null, $now )
		);
	}

	/**
	 * Update an existing rule, preserving its usage stats. Only the editable
	 * columns and `last_updated` are written.
	 *
	 * @param Rule $rule Rule with a non-null id().
	 *
	 * @return Rule
	 *
	 * @throws RuntimeException If the row no longer exists or the update fails.
	 */
	private function update( Rule $rule ): Rule {
		$id       = (int) $rule->id();
		$existing = $this->find( $id );
		if ( null === $existing ) {
			throw new RuntimeException( sprintf( 'Wpdb_Rule_Repository: rule %d does not exist.', $id ) );
		}

		$now  = $this->now();
		$row  = $this->serialise( $rule );
		$data = array(
			'type'          => $row['type'],
			'name'          => $row['name'],
			'description'   => $row['description'],
			'payload'       => $row['payload'],
			'comment_parts' => $row['comment_parts'],
			'response'      => $row['response'],
			'last_updated'  => $now->format( self::DATETIME_FORMAT ),
		);

		$updated = $this->wpdb->update(
			$this->table(),
			$data,
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			throw new RuntimeException( sprintf( 'Wpdb_Rule_Repository: failed to update rule %d.', $id ) );
		}

		$stats = new Usage_Stats(
			$existing->usage_stats()->times_used(),
			$existing->usage_stats()->last_used(),
			$now
		);

		return $this->with_persisted_values( $rule, $id, $stats );
	}

	/**
	 * Translate a Rule into the column scalars the table expects.
	 *
	 * @param Rule $rule Source rule.
	 *
	 * @return array{type:string,name:?string,description:?string,payload:string,comment_parts:string,response:string}
	 */
	private function serialise( Rule $rule ): array {
		return array(
			'type'          => $rule->type()->value(),
			'name'          => $rule->name(),
			'description'   => $rule->description(),
			'payload'       => $this->encode_payload( $rule ),
			'comment_parts' => $this->encode_parts( $rule->comment_parts() ),
			'response'      => $rule->response()->value(),
		);
	}

	/**
	 * Encode the rule's type-specific data as JSON for the `payload` column.
	 *
	 * @param Rule $rule Source rule.
	 *
	 * @return string
	 *
	 * @throws RuntimeException If the rule is an unrecognised concrete class.
	 */
	private function encode_payload( Rule $rule ): string {
		if ( $rule instanceof Regex_Rule || $rule instanceof Wildcard_Rule ) {
			return (string) wp_json_encode( array( 'pattern' => $rule->pattern() ) );
		}
		if ( $rule instanceof Ip_Range_Rule ) {
			return (string) wp_json_encode(
				array(
					'start_ip' => $rule->start_ip(),
					'end_ip'   => $rule->end_ip(),
				)
			);
		}
		if ( $rule instanceof Conditional_Rule ) {
			return (string) wp_json_encode( array( 'root' => $this->encode_group( $rule->root() ) ) );
		}
		throw new RuntimeException(
			sprintf( 'Wpdb_Rule_Repository: cannot serialise rule of class "%s".', get_class( $rule ) )
		);
	}

	/**
	 * Encode a Condition_Group into a portable nested array.
	 *
	 * @param Condition_Group $group Group to encode.
	 *
	 * @return array<string,mixed>
	 */
	private function encode_group( Condition_Group $group ): array {
		$children = array();
		foreach ( $group->children() as $child ) {
			$children[] = $child instanceof Condition_Group
				? $this->encode_group( $child )
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
	 * Encode a Comment_Parts collection as a comma-wrapped delimited string
	 * (`,name,email,`) so a LIKE filter cannot match partial tokens.
	 *
	 * @param Comment_Parts $parts Parts to encode.
	 *
	 * @return string
	 */
	private function encode_parts( Comment_Parts $parts ): string {
		if ( $parts->is_empty() ) {
			return '';
		}
		return ',' . implode( ',', $parts->values() ) . ',';
	}

	/**
	 * Rehydrate a row from the `pccm_rules` table into a Rule.
	 *
	 * @param array<string,mixed> $row DB row.
	 *
	 * @return Rule
	 *
	 * @throws RuntimeException If the row is malformed.
	 */
	private function hydrate( array $row ): Rule {
		$type    = Rule_Type::from( (string) $row['type'] );
		$id      = (int) $row['id'];
		$name    = isset( $row['name'] ) && '' !== $row['name'] ? (string) $row['name'] : null;
		$desc    = isset( $row['description'] ) ? (string) $row['description'] : null;
		$payload = $this->decode_payload( (string) $row['payload'] );
		$parts   = $this->decode_parts( (string) $row['comment_parts'] );
		$resp    = Response::from( (string) $row['response'] );
		$stats   = new Usage_Stats(
			(int) $row['times_used'],
			$this->decode_datetime( $row['last_used'] ?? null ),
			$this->require_datetime( $row['last_updated'] ?? null )
		);

		switch ( $type->value() ) {
			case Rule_Type::REGEX:
				return new Regex_Rule(
					$id,
					$name,
					$desc,
					(string) ( $payload['pattern'] ?? '' ),
					$parts,
					$resp,
					$stats
				);
			case Rule_Type::WILDCARD:
				return new Wildcard_Rule(
					$id,
					$name,
					$desc,
					(string) ( $payload['pattern'] ?? '' ),
					$parts,
					$resp,
					$stats
				);
			case Rule_Type::IP_RANGE:
				return new Ip_Range_Rule(
					$id,
					$name,
					$desc,
					(string) ( $payload['start_ip'] ?? '' ),
					(string) ( $payload['end_ip'] ?? '' ),
					$resp,
					$stats
				);
			case Rule_Type::CONDITIONAL:
				$root_raw = $payload['root'] ?? null;
				if ( ! is_array( $root_raw ) ) {
					throw new RuntimeException( 'Wpdb_Rule_Repository: conditional rule payload missing "root".' );
				}
				return new Conditional_Rule(
					$id,
					$name,
					$desc,
					$this->decode_group( $root_raw ),
					$resp,
					$stats
				);
		}

		throw new RuntimeException( sprintf( 'Wpdb_Rule_Repository: unknown rule type "%s".', $type->value() ) );
	}

	/**
	 * Decode the `payload` column JSON into an associative array.
	 *
	 * @param string $json Raw JSON.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws RuntimeException If the JSON is invalid.
	 */
	private function decode_payload( string $json ): array {
		if ( '' === $json ) {
			return array();
		}
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( 'Wpdb_Rule_Repository: payload JSON is not an object.' );
		}
		return $decoded;
	}

	/**
	 * Decode the `comment_parts` column into a Comment_Parts collection.
	 *
	 * @param string $stored Stored comma-wrapped value.
	 *
	 * @return Comment_Parts
	 */
	private function decode_parts( string $stored ): Comment_Parts {
		$trimmed = trim( $stored, ',' );
		if ( '' === $trimmed ) {
			return Comment_Parts::none();
		}
		$values = array_values(
			array_filter(
				explode( ',', $trimmed ),
				static fn( string $token ): bool => '' !== $token
			)
		);
		return Comment_Parts::from_values( $values );
	}

	/**
	 * Decode an encoded group array back into a Condition_Group.
	 *
	 * @param array<string,mixed> $raw Encoded group.
	 *
	 * @return Condition_Group
	 *
	 * @throws RuntimeException If the encoded shape is invalid.
	 */
	private function decode_group( array $raw ): Condition_Group {
		$combinator_raw = (string) ( $raw['combinator'] ?? '' );
		$children_raw   = $raw['children'] ?? array();
		if ( ! is_array( $children_raw ) || array() === $children_raw ) {
			throw new RuntimeException( 'Wpdb_Rule_Repository: conditional group has no children.' );
		}

		$children = array();
		foreach ( $children_raw as $child ) {
			if ( ! is_array( $child ) ) {
				throw new RuntimeException( 'Wpdb_Rule_Repository: conditional child is not an object.' );
			}
			$children[] = isset( $child['combinator'] )
				? $this->decode_group( $child )
				: new Condition(
					Comment_Part::from( (string) ( $child['part'] ?? '' ) ),
					Operator::from( (string) ( $child['operator'] ?? '' ) ),
					(string) ( $child['value'] ?? '' )
				);
		}

		return new Condition_Group( Combinator::from( $combinator_raw ), $children );
	}

	/**
	 * Decode a nullable datetime string from the table.
	 *
	 * @param mixed $value Raw value from the row.
	 *
	 * @return DateTimeImmutable|null
	 */
	private function decode_datetime( $value ): ?DateTimeImmutable {
		if ( null === $value || '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}
		return new DateTimeImmutable( (string) $value, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Decode a required datetime string from the table, throwing if absent.
	 *
	 * @param mixed $value Raw value from the row.
	 *
	 * @return DateTimeImmutable
	 *
	 * @throws RuntimeException If the value is missing.
	 */
	private function require_datetime( $value ): DateTimeImmutable {
		$decoded = $this->decode_datetime( $value );
		if ( null === $decoded ) {
			throw new RuntimeException( 'Wpdb_Rule_Repository: row is missing required last_updated.' );
		}
		return $decoded;
	}

	/**
	 * Build the WHERE clause for a Rule_Filter — returns the SQL fragment and
	 * the bound argument list. Empty `sql` means "no WHERE".
	 *
	 * @param Rule_Filter $filter Filter to translate.
	 *
	 * @return array{sql:string,args:list<int|string>}
	 */
	private function build_where( Rule_Filter $filter ): array {
		if ( $filter->is_empty() ) {
			return array(
				'sql'  => '',
				'args' => array(),
			);
		}

		$clauses = array();
		$args    = array();

		$search = trim( $filter->search_term() );
		if ( '' !== $search ) {
			$like      = '%' . $this->wpdb->esc_like( $search ) . '%';
			$clauses[] = '(name LIKE %s OR payload LIKE %s)';
			$args[]    = $like;
			$args[]    = $like;
		}

		$types = $filter->types();
		if ( array() !== $types ) {
			$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
			$clauses[]    = '(type IN (' . $placeholders . '))';
			foreach ( $types as $type ) {
				$args[] = $type->value();
			}
		}

		$parts = $filter->parts();
		if ( ! $parts->is_empty() ) {
			$part_clauses = array();
			foreach ( $parts as $part ) {
				$part_clauses[] = 'comment_parts LIKE %s';
				$args[]         = '%,' . $this->wpdb->esc_like( $part->value() ) . ',%';
			}
			$clauses[] = '(' . implode( ' OR ', $part_clauses ) . ')';
		}

		$responses = $filter->responses();
		if ( array() !== $responses ) {
			$placeholders = implode( ', ', array_fill( 0, count( $responses ), '%s' ) );
			$clauses[]    = '(response IN (' . $placeholders . '))';
			foreach ( $responses as $response ) {
				$args[] = $response->value();
			}
		}

		return array(
			'sql'  => implode( ' AND ', $clauses ),
			'args' => $args,
		);
	}

	/**
	 * Return a fresh copy of $rule with a different id and Usage_Stats.
	 *
	 * @param Rule        $rule  Source rule.
	 * @param integer     $id    New primary key.
	 * @param Usage_Stats $stats Reconciled stats.
	 *
	 * @return Rule
	 *
	 * @throws RuntimeException If the rule is an unrecognised concrete class.
	 */
	private function with_persisted_values( Rule $rule, int $id, Usage_Stats $stats ): Rule {
		if ( $rule instanceof Regex_Rule ) {
			return new Regex_Rule(
				$id,
				$rule->name(),
				$rule->description(),
				$rule->pattern(),
				$rule->comment_parts(),
				$rule->response(),
				$stats
			);
		}
		if ( $rule instanceof Wildcard_Rule ) {
			return new Wildcard_Rule(
				$id,
				$rule->name(),
				$rule->description(),
				$rule->pattern(),
				$rule->comment_parts(),
				$rule->response(),
				$stats
			);
		}
		if ( $rule instanceof Ip_Range_Rule ) {
			return new Ip_Range_Rule(
				$id,
				$rule->name(),
				$rule->description(),
				$rule->start_ip(),
				$rule->end_ip(),
				$rule->response(),
				$stats
			);
		}
		if ( $rule instanceof Conditional_Rule ) {
			return new Conditional_Rule(
				$id,
				$rule->name(),
				$rule->description(),
				$rule->root(),
				$rule->response(),
				$stats
			);
		}
		throw new RuntimeException(
			sprintf( 'Wpdb_Rule_Repository: cannot rebuild rule of class "%s".', get_class( $rule ) )
		);
	}

	/**
	 * Current UTC instant — extracted so tests can keep a deterministic clock.
	 *
	 * @return DateTimeImmutable
	 */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Resolve the prefixed table name from App_Config.
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->app_config->db_tables( 'rules' );
	}
}
