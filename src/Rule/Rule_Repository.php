<?php

/**
 * Repository for the Rule entity.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Rule;

use PinkCrab\Comment_Moderation\Rule\Rule;
use PinkCrab\Perique\Application\App_Config;
use PinkCrab\Comment_Moderation\Rule\Condition\Serializer;

/**
 * Repository for the Rule entity.
 *
 * @phpstan-type RuleDBRow array{
 * id: int,
 * name: string,
 * rule_enabled: bool,
 * conditions: string,
 * outcome: string,
 * created: string,
 * updated: string
 * }
 */
class Rule_Repository {

	/**
	 * The WPDB instance.
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * Access to the app config.
	 *
	 * @var App_Config
	 */
	protected $app_config;

	/**
	 * Condition Serializer.
	 * 
	 * @var Serializer
	 */
	protected $serializer;

	/**
	 * Construct.
	 *
	 * @param \wpdb      $wpdb       The WPDB instance.
	 * @param App_Config $app_config The App Config.
	 * @param Serializer $serializer The Condition Serializer.
	 */
	public function __construct( \wpdb $wpdb, App_Config $app_config, Serializer $serializer ) {
		$this->wpdb       = $wpdb;
		$this->app_config = $app_config;
		$this->serializer = $serializer;
	}

	/**
	 * Gets the table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return $this->app_config->db_tables( 'rules' );
	}

	/**
	 * Get a rule by its ID.
	 *
	 * @param integer $id The ID of the rule.
	 *
	 * @return Rule|null
	 */
	public function get( int $id ): ?Rule {
		$rule = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_name()} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, Cant escape table name and prepare called as $this->wpdb->prepare. // @phpstan-ignore-line
			ARRAY_A
		);

		// @phpstan-ignore-next-line, this will be an array based on ARRAY_A.
		return $rule ? $this->map_to_rule( $rule ) : null; // @phpstan-ignore-line
	}

	/**
	 * Get all rules.
	 *
	 * @return Rule[]
	 */
	public function get_all_rules(): array {
		$rules = $this->wpdb->get_results( "SELECT * FROM {$this->table_name()}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Cant escape the table name.
		// @phpstan-ignore-next-line, this will be an array based on ARRAY_A.
		return array_map( array( $this, 'map_to_rule' ), $rules ?? array() );
	}

	/**
	 * Count all rules.
	 *
	 * @return integer
	 */
	public function count_all_rules(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Cant escape the table name.
	}

	/**
	 * Upsert Rule.
	 *
	 * @param Rule $rule The rule to upsert.
	 *
	 * @return Rule
	 *
	 * @throws \Exception If the rule cannot be saved.
	 */
	public function upsert( Rule $rule ): Rule {
		return $rule->get_id() ? $this->update_rule( $rule ) : $this->insert_rule( $rule );
	}

	/**
	 * Insert a new rule.
	 *
	 * @param Rule $rule The rule to insert.
	 *
	 * @return Rule
	 *
	 * @throws \Exception If the rule cannot be saved.
	 */
	protected function insert_rule( Rule $rule ): Rule {
		$inserted = $this->wpdb->insert(
			$this->table_name(),
			array(
				'name'         => $rule->get_rule_name(),
				'rule_enabled' => $rule->get_rule_enabled(),
				'conditions'   => \json_encode( $rule->get_rule_conditions() ),
				'outcome'      => $rule->get_outcome(),
				'created'      => $rule->get_created()->format( 'Y-m-d H:i:s' ),
				'updated'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			throw new \Exception(
				sprintf(
					// Translators: %s is the error message.
					'Failed to insert rule: %s',
					esc_html( $this->wpdb->last_error )
				)
			);
		}

		return new Rule(
			(int) $this->wpdb->insert_id,
			$rule->get_rule_name(),
			$rule->get_rule_enabled(),
			$rule->get_rule_conditions(),
			$rule->get_outcome(),
			new \DateTimeImmutable( $rule->get_created()->format( 'Y-m-d H:i:s' ) ),
			new \DateTimeImmutable( current_time( 'mysql' ) )
		);
	}

	/**
	 * Update a rule.
	 *
	 * @param Rule $rule The rule to update.
	 *
	 * @return Rule
	 */
	protected function update_rule( Rule $rule ): Rule {
		$updated = $this->wpdb->update(
			$this->table_name(),
			array(
				'name'         => $rule->get_rule_name(),
				'rule_enabled' => $rule->get_rule_enabled(),
				'conditions'   => wp_json_encode( $rule->get_rule_conditions() ),
				'outcome'      => $rule->get_outcome(),
				'updated'      => current_time( 'mysql' ),
			),
			array( 'id' => $rule->get_id() ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( ! $updated ) {
			throw new \Exception(
				sprintf(
					// Translators: %s is the error message.
					'Failed to update rule: %s',
					esc_html( $this->wpdb->last_error )
				)
			);
		}

		return new Rule(
			$rule->get_id(),
			$rule->get_rule_name(),
			$rule->get_rule_enabled(),
			$rule->get_rule_conditions(),
			$rule->get_outcome(),
			$rule->get_created(),
			new \DateTimeImmutable( current_time( 'mysql' ) )
		);
	}

    // phpcs:disable
	/**
	 * Maps an array to a Rule.
	 *
     * @param RuleDBRow $rule The rule to map.
	 *
	 * @return Rule
	 */
	protected function map_to_rule( array $rule ): Rule { 
	// phpcs:enable	
		return new Rule(
			(int) $rule['id'],
			\sanitize_text_field( $rule['name'] ),
			(bool) $rule['rule_enabled'],
			$this->serializer->decode( $rule['conditions'] ),
			\sanitize_text_field( $rule['outcome'] ),
			new \DateTimeImmutable( $rule['created'] ),
			new \DateTimeImmutable( $rule['updated'] )
		);
	}
}
