#!/usr/bin/env node
/**
 * Bundle the compiled Elm output (`assets/js/admin.elm.js`) together with the
 * tiny boot snippet (`assets/src/scripts/admin-boot.js`) into a single browser
 * script at `assets/js/admin.js`. The boot snippet is what actually mounts the
 * Elm app on the `#pccm-admin-root` div emitted by the admin page template and
 * hands it the `window.pccmAdminData` bootstrap (ajaxUrl / ajaxNonce / actions
 * / pageSlug) that `wp_localize_script` puts on the page.
 *
 * Run as part of `npm run build`. No external deps; uses only `fs`.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const elm = path.join( root, 'assets/js/admin.elm.js' );
const boot = path.join( root, 'assets/src/scripts/admin-boot.js' );
const out = path.join( root, 'assets/js/admin.js' );

const elmSrc = fs.readFileSync( elm, 'utf8' );
const bootSrc = fs.readFileSync( boot, 'utf8' );

fs.writeFileSync( out, elmSrc + '\n' + bootSrc, 'utf8' );
fs.unlinkSync( elm );

process.stdout.write( '→ wrote ' + path.relative( root, out ) + '\n' );
