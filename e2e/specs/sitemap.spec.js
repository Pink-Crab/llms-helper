const { test, expect } = require( '../fixtures' );
const { wpCli, resetSettings } = require( '../fixtures/wp-cli' );

// The SEO plugins whose sitemap index lists llms-sitemap.xml, by wordpress.org slug.
const SITEMAP_PLUGINS = [
	{ slug: 'wordpress-seo', name: 'Yoast SEO' },
	{ slug: 'seo-by-rank-math', name: 'Rank Math' },
];

const LLMS_TXT = /<loc>http:\/\/localhost:\d+\/llms\.txt<\/loc>/;

/**
 * Saves the plugin's settings, with llms.txt sent by PHP.
 *
 * @param {Object} values Settings over the defaults.
 */
const saveSettings = ( values ) => wpCli( 'option', 'update', 'llms_helper_settings', JSON.stringify( { redirect: false, ...values } ), '--format=json' );

/**
 * Publishes a post and builds the copies and llms.txt.
 *
 * @param {Object} requestUtils WordPress request utils.
 * @param {string} slug         Post slug.
 */
async function publish( requestUtils, slug ) {
	await requestUtils.createPost( { title: slug, slug, content: '<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->', status: 'publish' } );
	wpCli( 'llms-helper', 'build' );
}

/**
 * A response's body.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {string}                                         path    Path.
 * @return {Promise<string>} Body.
 */
const body = async ( request, path ) => ( await request.get( path ) ).text();

for ( const plugin of SITEMAP_PLUGINS ) {
	test.describe( `${ plugin.name } sitemap`, () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test.beforeAll( () => {
			wpCli( 'plugin', 'activate', plugin.slug );
			wpCli( 'llms-e2e-seo', 'setup', plugin.slug );
			// The plugin's sitemap URLs are rewrite rules.
			wpCli( 'rewrite', 'flush', '--hard' );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			resetSettings();
			// With redirect mode back on this also removes a site root llms.txt.
			wpCli( 'llms-helper', 'index' );
			await requestUtils.deleteAllPosts();
		} );

		test.afterAll( () => {
			wpCli( 'plugin', 'deactivate', plugin.slug );
			wpCli( 'rewrite', 'flush', '--hard' );
		} );

		test( 'with indexing on, the sitemap index lists the llms sitemap, which lists llms.txt', async ( { requestUtils, request } ) => {
			saveSettings( { index_llms_txt: true } );
			await publish( requestUtils, 'sitemap-on' );

			expect( await body( request, '/sitemap_index.xml' ) ).toContain( '/llms-sitemap.xml' );
			expect( await body( request, '/llms-sitemap.xml' ) ).toMatch( LLMS_TXT );
		} );

		test( 'with indexing off, the sitemaps leave llms.txt out', async ( { requestUtils, request } ) => {
			saveSettings( {} );
			await publish( requestUtils, 'sitemap-off' );

			expect( await body( request, '/sitemap_index.xml' ) ).not.toContain( '/llms-sitemap.xml' );
			expect( await body( request, '/llms-sitemap.xml' ) ).not.toMatch( LLMS_TXT );
		} );

		test( 'turning indexing on and off updates the sitemap index the plugin has kept', async ( { requestUtils, request } ) => {
			saveSettings( {} );
			await publish( requestUtils, 'sitemap-toggle' );
			expect( await body( request, '/sitemap_index.xml' ) ).not.toContain( '/llms-sitemap.xml' );

			saveSettings( { index_llms_txt: true } );
			expect( await body( request, '/sitemap_index.xml' ) ).toContain( '/llms-sitemap.xml' );

			saveSettings( {} );
			expect( await body( request, '/sitemap_index.xml' ) ).not.toContain( '/llms-sitemap.xml' );
		} );

		test( 'rebuilding llms.txt updates its time in the sitemap the plugin has kept', async ( { requestUtils, request } ) => {
			saveSettings( { index_llms_txt: true } );
			await publish( requestUtils, 'sitemap-first' );
			const before = await body( request, '/llms-sitemap.xml' );
			expect( before ).toMatch( LLMS_TXT );

			// The time is to the second.
			await new Promise( ( resolve ) => setTimeout( resolve, 1100 ) );
			await publish( requestUtils, 'sitemap-second' );

			const after = await body( request, '/llms-sitemap.xml' );
			expect( after ).toMatch( LLMS_TXT );
			expect( after.match( /<lastmod>([^<]+)<\/lastmod>/ )[ 1 ] ).not.toBe( before.match( /<lastmod>([^<]+)<\/lastmod>/ )[ 1 ] );
		} );
	} );
}
