const { defineConfig, devices } = require( '@playwright/test' );
const path = require( 'path' );
require( 'dotenv' ).config( { path: path.resolve( __dirname, '.env' ) } );

const baseURL = process.env.WP_BASE_URL || 'http://localhost:57892';

// The WordPress e2e utils read this themselves, and default to port 8889.
process.env.WP_BASE_URL = baseURL;

module.exports = defineConfig( {
	testDir: './specs',
	outputDir: '../test-results',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: [
		// On CI, line prints each failure as it happens.
		[ process.env.CI ? 'line' : 'list' ],
		[ 'html', { outputFolder: '../playwright-report', open: 'never' } ],
	],
	use: {
		baseURL,
		storageState: path.resolve(
			__dirname,
			'../artifacts/storage-states/admin.json'
		),
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'setup',
			testMatch: /global-setup\.js/,
			testDir: '.',
		},
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
		},
	],
} );
