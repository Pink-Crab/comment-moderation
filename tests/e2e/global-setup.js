// @ts-check
const { chromium } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Global setup — log in as the WordPress administrator once and persist the
 * session cookies for the `chromium` project to reuse via `storageState`.
 *
 * @param {import('@playwright/test').FullConfig} config
 */
module.exports = async function globalSetup( config ) {
	const baseURL =
		( config.projects[ 0 ] && config.projects[ 0 ].use.baseURL ) ||
		process.env.WP_BASE_URL ||
		'http://localhost:57881';
	const adminUser = process.env.WP_ADMIN_USER || 'admin';
	const adminPassword = process.env.WP_ADMIN_PASSWORD || 'password';
	const storagePath = path.resolve(
		__dirname,
		'.auth/admin.json'
	);

	fs.mkdirSync( path.dirname( storagePath ), { recursive: true } );

	const browser = await chromium.launch();
	const context = await browser.newContext();
	const page = await context.newPage();

	await page.goto( `${ baseURL }/wp-login.php` );
	await page.fill( '#user_login', adminUser );
	await page.fill( '#user_pass', adminPassword );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );

	await context.storageState( { path: storagePath } );
	await browser.close();
};
