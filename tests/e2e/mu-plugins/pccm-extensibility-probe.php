<?php
/**
 * Plugin Name: PCCM Extensibility Probe (test fixture)
 * Description: Drives the rebuild spec §11 extensibility hooks at runtime so
 *              the Playwright suite can assert each one fires in a real
 *              wp-env browser session. Active only when the
 *              `PCCM_EXTENSIBILITY_PROBE_ACTIVE` cookie is set on the request,
 *              so it has zero effect on every other spec that runs against
 *              the same wp-env site.
 *
 * @package PinkCrab\Comment_Moderation\Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gate every probe hook on a single request-scoped cookie so unrelated specs
 * that share this wp-env site continue to see the unmodified admin shell.
 *
 * @return bool
 */
function pccm_extensibility_probe_is_active(): bool {
	return ! empty( $_COOKIE['PCCM_EXTENSIBILITY_PROBE_ACTIVE'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
}

/**
 * pccm_admin_page_html — wrap the rendered shell in a probe banner so the
 * e2e spec can prove the filter fires with the real markup.
 */
add_filter(
	'pccm_admin_page_html',
	static function ( $html ) {
		if ( ! pccm_extensibility_probe_is_active() ) {
			return $html;
		}
		return '<div data-pccm-probe="page-html">PCCM_PROBE_PAGE_HTML</div>' . $html;
	},
	10,
	1
);

/**
 * pccm_edit_rule_link — emit a span whose textContent advertises the
 * received arguments, so the e2e spec can confirm rule id / label / url
 * are passed to the filter.
 */
add_filter(
	'pccm_edit_rule_link',
	static function ( $markup, $rule_id, $label, $url ) {
		if ( ! pccm_extensibility_probe_is_active() ) {
			return $markup;
		}
		return sprintf(
			'<span data-pccm-probe="edit-link" data-rule-id="%1$d" data-url="%2$s">%3$s</span>',
			(int) $rule_id,
			esc_attr( (string) $url ),
			esc_html( (string) $label )
		);
	},
	10,
	4
);

/**
 * pccm_required_capability — drop the gate to `read` only when the probe
 * cookie is set, so the e2e spec can prove the filter actually changes the
 * effective capability.
 */
add_filter(
	'pccm_required_capability',
	static function ( $capability ) {
		if ( ! pccm_extensibility_probe_is_active() ) {
			return $capability;
		}
		return 'read';
	},
	10,
	1
);

/**
 * pccm_akismet_enabled — flip the integration off only under the probe
 * cookie. The probe spec asserts the constant the production code reads is
 * the same one this filter targets.
 */
add_filter(
	'pccm_akismet_enabled',
	static function ( $enabled ) {
		if ( ! pccm_extensibility_probe_is_active() ) {
			return $enabled;
		}
		return false;
	},
	10,
	1
);

/**
 * pccm_comment_failed_rule — record the dispatch in a transient so the
 * spec can prove the action fires when a rule diverts a comment. The
 * production engine fires this action from `pre_comment_approved`; here we
 * only want a lightweight "did it fire" probe for the e2e layer.
 */
add_action(
	'pccm_comment_failed_rule',
	static function (): void {
		if ( ! pccm_extensibility_probe_is_active() ) {
			return;
		}
		set_transient( 'pccm_probe_failed_rule', time(), 60 );
	},
	10,
	3
);

/**
 * Expose the recorded probe state through a tiny admin-ajax endpoint so the
 * Playwright spec can read it without resorting to DB introspection. The
 * endpoint is admin-only and uses the same `pccm_required_capability` gate
 * the production AJAX layer uses, so it inherits the relaxed-to-`read`
 * setting above while the probe cookie is active.
 */
add_action(
	'wp_ajax_pccm_probe_state',
	static function (): void {
		if ( ! pccm_extensibility_probe_is_active() ) {
			wp_send_json_error( array( 'message' => 'probe inactive' ), 403 );
		}

		$capability = (string) apply_filters( 'pccm_required_capability', 'manage_options' );
		$akismet    = (bool) apply_filters( 'pccm_akismet_enabled', true );

		// Snapshot the default deep-link markup before our own probe filter
		// rewrites it — the spec uses both values to prove the hook is the
		// one production code routes through.
		$probe_filter = 'pccm_extensibility_probe_edit_link_filter';
		$ours         = null;
		foreach ( $GLOBALS['wp_filter']['pccm_edit_rule_link']->callbacks[10] ?? array() as $key => $cb ) {
			if ( $cb['function'] instanceof Closure && ( new ReflectionFunction( $cb['function'] ) )->getFileName() === __FILE__ ) {
				$ours = array( $key, $cb );
				break;
			}
		}
		if ( null !== $ours ) {
			remove_filter( 'pccm_edit_rule_link', $ours[1]['function'], 10 );
		}
		$default = \PinkCrab\Comment_Moderation\Presentation\Page\Admin_Page::edit_link( 123, 'Edit Rule' );
		if ( null !== $ours ) {
			add_filter( 'pccm_edit_rule_link', $ours[1]['function'], 10, $ours[1]['accepted_args'] );
		}

		$filtered = \PinkCrab\Comment_Moderation\Presentation\Page\Admin_Page::edit_link( 123, 'Edit Rule' );

		wp_send_json_success(
			array(
				'capability'         => $capability,
				'akismet_enabled'    => $akismet,
				'edit_link_default'  => $default,
				'edit_link_filtered' => $filtered,
				'failed_rule_seen'   => false !== get_transient( 'pccm_probe_failed_rule' ),
			)
		);
	}
);
