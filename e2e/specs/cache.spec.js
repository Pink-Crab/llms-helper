const { test, expect } = require( '../fixtures' );
const { wpCli, resetSettings } = require( '../fixtures/wp-cli' );
const { CACHE_PLUGINS } = require( '../fixtures/cache-plugins' );

/**
 * Paragraph block content.
 *
 * @param {string} text Text.
 * @return {string} Content.
 */
const paragraph = ( text ) => `<!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph -->`;

/**
 * Loads a path, following redirects.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {string}                                         path    Path.
 * @return {Promise<{headers: Object<string, string>, body: string}>} Response.
 */
async function load( request, path ) {
	const response = await request.get( path );
	return { headers: response.headers(), body: await response.text() };
}

for ( const plugin of CACHE_PLUGINS ) {
	test.describe( `${ plugin.name } caching`, () => {
		// Load as a logged out visitor, as agents do and as page caches serve.
		test.use( { storageState: { cookies: [], origins: [] } } );

		test.beforeAll( () => {
			for ( const args of plugin.on ) {
				wpCli( ...args );
			}
		} );

		test.afterEach( async ( { requestUtils } ) => {
			resetSettings();
			await requestUtils.deleteAllPosts();
		} );

		test.afterAll( () => {
			for ( const args of plugin.off ) {
				try {
					wpCli( ...args );
				} catch ( error ) {
					// Already undone by the plugin's own deactivation.
				}
			}
		} );

		test( 'the plugin is working on pages', async ( { requestUtils, request } ) => {
			await requestUtils.createPost( { title: 'Cache Page', slug: 'cache-page', content: paragraph( 'A page.' ), status: 'publish' } );

			const first = await load( request, '/cache-page/' );
			await new Promise( ( resolve ) => setTimeout( resolve, 1100 ) );
			const second = await load( request, '/cache-page/' );

			expect( plugin.working( first, second ) ).toBe( true );
		} );

		for ( const redirect of [ true, false ] ) {
			test( `llms.txt and copies show each change, ${ redirect ? 'redirected to the files' : 'sent by PHP' }`, async ( { requestUtils, request } ) => {
				wpCli( 'option', 'update', 'llms_helper_settings', JSON.stringify( { redirect } ), '--format=json' );
				const post = await requestUtils.createPost( { title: 'Cache One', slug: 'cache-one', content: paragraph( 'FIRST-VERSION' ), status: 'publish' } );
				wpCli( 'llms-helper', 'build' );

				// Loaded twice, so a cache that keeps them has them.
				for ( let round = 0; round < 2; round++ ) {
					await load( request, '/llms.txt' );
					await load( request, '/cache-one/index.html.md' );
				}

				await requestUtils.createPost( { title: 'Cache Two', slug: 'cache-two', content: paragraph( 'Two.' ), status: 'publish' } );
				await requestUtils.rest( { method: 'POST', path: `/wp/v2/posts/${ post.id }`, data: { content: paragraph( 'SECOND-VERSION' ) } } );
				wpCli( 'llms-helper', 'build' );

				const llms = await load( request, '/llms.txt' );
				const copy = await load( request, '/cache-one/index.html.md' );
				expect( llms.body ).toContain( '[Cache Two]' );
				expect( copy.body ).toContain( 'SECOND-VERSION' );
				expect( copy.body ).not.toContain( 'FIRST-VERSION' );

				// Exactly as written, with nothing added by the plugin.
				expect( llms.body ).toBe( ( await load( request, '/wp-content/uploads/llms-helper/llms.txt' ) ).body );
				expect( copy.body ).toBe( ( await load( request, '/wp-content/uploads/llms-helper/cache-one/index.html.md' ) ).body );
			} );
		}
	} );
}

// WP Rocket is paid. It only keeps responses with a closing </html> (Buffer\Tests::is_html()), so never llms.txt or a
// copy; llms-e2e-cache.php stands in for its rocket_clean_files() to check each copy and llms.txt URL reaches it.
test.describe( 'WP Rocket purging', () => {
	test.beforeAll( () => {
		wpCli( 'option', 'update', 'llms_e2e_mock_rocket', '1' );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.afterAll( () => {
		for ( const option of [ 'llms_e2e_mock_rocket', 'llms_e2e_rocket_cleaned' ] ) {
			try {
				wpCli( 'option', 'delete', option );
			} catch ( error ) {
				// Not set.
			}
		}
	} );

	test( 'rocket_clean_files gets the copy and llms.txt URLs', async ( { requestUtils } ) => {
		await requestUtils.createPost( { title: 'Rocket One', slug: 'rocket-one', content: paragraph( 'Rocket.' ), status: 'publish' } );
		wpCli( 'llms-helper', 'build' );

		const cleaned = JSON.parse( wpCli( 'option', 'get', 'llms_e2e_rocket_cleaned', '--format=json' ).split( '\n' ).pop().trim() );
		expect( cleaned ).toContainEqual( expect.stringMatching( /^http:\/\/localhost:\d+\/rocket-one\/index\.html\.md$/ ) );
		expect( cleaned ).toContainEqual( expect.stringMatching( /\/wp-content\/uploads\/llms-helper\/rocket-one\/index\.html\.md$/ ) );
		expect( cleaned ).toContainEqual( expect.stringMatching( /^http:\/\/localhost:\d+\/llms\.txt$/ ) );
	} );
} );
