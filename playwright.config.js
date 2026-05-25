// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );
const path = require( 'path' );
require( 'dotenv' ).config( {
	path: path.resolve( __dirname, 'tests/e2e/.env' ),
} );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:57881';
const ADMIN_STORAGE_STATE = path.resolve(
	__dirname,
	'tests/e2e/.auth/admin.json'
);

module.exports = defineConfig( {
	testDir: './tests/e2e/specs',
	outputDir: './test-results',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: 'playwright-report', open: 'never' } ],
	],
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	use: {
		baseURL: BASE_URL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'setup',
			testMatch: /global-setup\.js$/,
		},
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: ADMIN_STORAGE_STATE,
			},
			dependencies: [ 'setup' ],
		},
	],
} );
