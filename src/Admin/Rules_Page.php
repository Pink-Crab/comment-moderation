<?php

/**
 * The Rules management page.
 *
 * @package PinkCrab\Comment_Moderation
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\Comment_Moderation\Admin;

use PinkCrab\Comment_Moderation\Rule\Rule;
use PinkCrab\Perique_Admin_Menu\Page\Page;
use PinkCrab\Perique_Admin_Menu\Page\Menu_Page;
use PinkCrab\Comment_Moderation\Rule\Rule_Repository;

/**
 * The Rules management page.
 */
class Rules_Page extends Menu_Page {
	// Required
	protected string $page_slug = 'pc_comment_moderation';

	// Optional
	protected ?string $parent_slug = 'tools.php';       // If null, will be a top level menu item.
	protected string $capability   = 'manage_options';  // Default capability for page.
	protected ?int $position       = 12;

	/**
	 * Access to App_Config
	 *
	 * @var \PinkCrab\Perique\Application\App_Config
	 */
	protected $app_config;

	/**
	 * Access to the Rule Repository
	 *
	 * @var Rule_Repository
	 */
	protected $rule_repository;

	/**
	 * The Rule Form Handler
	 *
	 * @var Rule_Form_Handler
	 */
	protected $rule_form_handler;

	/**
	 * Create a new instance of the Rules_Page.
	 *
	 * @param \PinkCrab\Perique\Application\App_Config $app_config        The App Config.
	 * @param Rule_Repository                          $rule_repository   The Rule Repository.
	 * @param Rule_Form_Handler                        $rule_form_handler The Rule Form Handler.
	 */
	public function __construct(
		\PinkCrab\Perique\Application\App_Config $app_config,
		Rule_Repository $rule_repository,
		Rule_Form_Handler $rule_form_handler
	) {
		$this->app_config        = $app_config;
		$this->rule_repository   = $rule_repository;
		$this->rule_form_handler = $rule_form_handler;

		// Set the titles.
		$this->menu_title    = _x( 'Comment Rules', 'Rule Menu Title', 'pc-cm' );
		$this->page_title    = _x( 'Comment Moderation Rules', 'Rule List Page Title', 'pc-cm' );
		$this->view_template = 'admin/page/rules.php';
	}

	/**
	 * Set the page template and data.
	 *
	 * @param Page $page The page object.
	 *
	 * @return void
	 */
	public function load( Page $page ): void {

		// Add page and form handler to view data.
		$this->view_data['pc_cm_page']         = $this;
		$this->view_data['pc_cm_form_handler'] = $this->rule_form_handler;

		// If the form has been submitted, handle the request.
		if ( $this->rule_form_handler->is_submitted() ) {

				// Populated here to so the user can see the values and errors.
				$this->rule_form_handler->populate_form_values();
				$this->rule_form_handler->verify_form_submission();

			// If we have no errors, show success.
			if ( ! $this->rule_form_handler->has_form_errors() ) {
				$rule_id = $this->rule_form_handler->get_field_value( 'rule_id' );
				// Create a rule from the values.
				$rule = new Rule(
					is_numeric( $rule_id ) ? absint( $rule_id ) : null,
					$this->rule_form_handler->get_field_value( 'rule_name' ),
					$this->rule_form_handler->get_field_value( 'rule_type' ),
					$this->rule_form_handler->get_field_value( 'rule_value' ),
					$this->rule_form_handler->get_field_value( 'rule_enabled' ),
					$this->rule_form_handler->get_field_value( 'rule_field' ),
					$this->rule_form_handler->get_field_value( 'rule_outcome' ),
				);

				$rule = $this->rule_repository->upsert( $rule );

				$this->view_data['pc_cm_success'] = __( 'Rule saved.', 'pc-cm' );
				$this->view_data['pc_cm_rule']    = $rule;

				// Get the rule id to the $_GET request.
				$_GET['rule_id'] = $rule->get_id();
			}
		}

		// Pass all allowed rule types to the view.
		$this->view_data['pc_cm_rule_types'] = array(
			Rule::RULE_TYPE_CONTAINS    => _x( 'Contains', 'Rule Type Contains', 'pc-cm' ),
			Rule::RULE_TYPE_NOT_CONTAIN => _x( 'Doesn\'t contain', 'Rule Type Not Contains', 'pc-cm' ),
			Rule::RULE_TYPE_EQUALS      => _x( 'Equals', 'Rule Type Exact Match', 'pc-cm' ),
			Rule::RULE_TYPE_NOT_EQUALS  => _x( 'Not  Equals', 'Rule Type Not Exact Match', 'pc-cm' ),
			Rule::RULE_TYPE_REGEX       => _x( 'Regex', 'Rule Type Regex', 'pc-cm' ),
			Rule::RULE_TYPE_WILDCARD    => _x( 'Wildcard', 'Rule Type Wildcard', 'pc-cm' ),
		);
			// If rule_id is set in url, attempt to load single.
		if ( isset( $_GET['rule_id'] ) && is_numeric( $_GET['rule_id'] ) ) { // phpcs:ignore
			$rule = $this->rule_repository->get( absint( $_GET['rule_id'] ) ); // phpcs:ignore

			if ( $rule ) {
				$this->page_title              = _x( 'Edit Rule', 'Edit Rule Page Title', 'pc-cm' );
				$this->view_template           = 'admin/page/edit_rule.php';
				$this->view_data['pc_cm_rule'] = $rule;

				// Add rule to the form handler.
				$this->rule_form_handler->add_rule( $rule );
			} else {
				$this->view_data['pc_cm_errors'] = array( __( 'Rule not found.', 'pc-cm' ) );
				$this->view_template             = 'admin/page/error.php';
			}

			return;
		} elseif ( isset( $_GET['new_rule'] ) ) { // phpcs:ignore
			$this->page_title              = _x( 'Create Rule', 'New Rule Page Title', 'pc-cm' );
			$this->view_template           = 'admin/page/edit_rule.php';
			$this->view_data['pc_cm_rule'] = null;
		} else {
			$this->view_data['pc_cm_table'] = new Rule_Table( array() );
		}
	}

	/**
	 * Get the url for a new rule.
	 *
	 * @return string
	 */
	public function new_rule_url(): string {
		return \add_query_arg( 'new_rule', 'true', \menu_page_url( $this->slug(), false ) );
	}

	/**
	 * Undocumented function
	 *
	 * @param string  $a Some value.
	 * @param boolean $c Some value.
	 * @return string
	 */
	public function foo( string $a, bool $c ): string {
		return $a;
	}

	/**
	 * @inheritDoc
	 */
	public function enqueue( Page $page ): void {
		\wp_enqueue_script(
			'pc-cm-admin',
			$this->app_config->url( 'assets' ) . 'app/dist/index.bundle.js',
			array( 'wp-i18n' ),
			$this->app_config->version(),
			true
		);
	}
}
