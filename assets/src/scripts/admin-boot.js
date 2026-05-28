/**
 * Boot snippet appended to the compiled Elm bundle by `scripts/bundle-admin.js`.
 *
 * `wp_localize_script` injects a `window.pccmAdminData` global before this file
 * runs (see `Admin_Page::enqueue`). All this script does is hand that object to
 * the Elm runtime as flags and mount it on the `#pccm-admin-root` div emitted
 * by `views/pages/admin-page.php`. Everything else is Elm's job.
 */
( function () {
	if ( typeof window === 'undefined' ) {
		return;
	}

	const data = window.pccmAdminData || {};
	const mountId = data.mountId || 'pccm-admin-root';
	const node = document.getElementById( mountId );
	if ( ! node ) {
		return;
	}

	if (
		! window.Elm ||
		! window.Elm.Main ||
		typeof window.Elm.Main.init !== 'function'
	) {
		return;
	}

	// `wp_localize_script` stringifies scalar payload values, so
	// `editRuleId` arrives as either a string ("42"), a number (42), or is
	// absent when no `?pccm_edit={id}` deep-link was in the request URL.
	// Normalise to a positive integer (or null) before handing it to Elm so
	// `flagsDecoder` only ever sees the two shapes it expects.
	let editRuleId = null;
	const rawEditRuleId = data.editRuleId;
	if (
		rawEditRuleId !== undefined &&
		rawEditRuleId !== null &&
		rawEditRuleId !== ''
	) {
		const parsed = parseInt( rawEditRuleId, 10 );
		if ( Number.isFinite( parsed ) && parsed > 0 ) {
			editRuleId = parsed;
		}
	}

	window.Elm.Main.init( {
		node,
		flags: {
			ajaxUrl: data.ajaxUrl || '',
			ajaxNonce: data.ajaxNonce || '',
			ajaxActions: data.ajaxActions || {},
			pageSlug: data.pageSlug || '',
			editRuleId,
		},
	} );
} )();
