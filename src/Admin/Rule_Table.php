<?php

/**
 * Used to render the rules table.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */
declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Admin;

use PinkCrab\Comment_Moderation\Rule\Rule;

/**
 * WP Post Table for the rules.
 */
class Rule_Table extends \WP_List_Table {

	/**
	* All the rules to be displayed.
	*
	* @var Rule[]
	*/
	protected $rules;

	/**
	 * Custom notices.
	 *
	 * @var array{message:string, type:string}[]
	 */
	private array $notices = array();

	/**
	* Construct.
	*
	* @param Rule[] $rules All the rules to be displayed.
	*/
	public function __construct( array $rules = array() ) {
		parent::__construct(
			array(
				'singular' => 'rule',
				'plural'   => 'rules',
				'ajax'     => false,
			)
		);

		$this->rules = $rules;
	}

	/**
	 * Pepare the items for the table.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->define_pagination_args();
		$this->items           = $this->rules;
		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	/**
	 * Sets the pagination args.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function define_pagination_args() {
		// Get the total number of links.
		$link_count = 10;

		// Set the pagination args.
		$this->set_pagination_args(
			array(
				'total_items' => $link_count,
				'per_page'    => 5,
				'total_pages' => absint( ceil( $link_count / 5 ) ),
			)
		);
	}

	/**
	 * Render any notices.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_notices() {
		foreach ( $this->notices as $notice ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Return all the columns for the table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'           => '<input type="checkbox" />',
			'rule_name'    => __( 'Rule Name', 'pc-cm' ),
			'rule_type'    => __( 'Rule Type', 'pc-cm' ),
			'rule_value'   => __( 'Rule Value', 'pc-cm' ),
			'rule_fields'  => __( 'Fields', 'pc-cm' ),
			'rule_enabled' => __( 'Enabled', 'pc-cm' ),
		);
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function no_items() {
		echo esc_html__( 'No links have been created yet.', 'pc-cm' );
	}
}
