const { test, expect } = require( '../fixtures' );
const { wpCli } = require( '../fixtures/wp-cli' );
const { SEO_PLUGINS } = require( '../fixtures/seo-plugins' );

/**
 * Publishes a post, making the SEO setting while it is a draft so its copy is built with it.
 *
 * @param {string}      plugin  Plugin slug.
 * @param {string}      slug    Post slug.
 * @param {string|null} setting 'noindex', 'index' or null for none.
 */
function publish( plugin, slug, setting = null ) {
	const output = wpCli( 'post', 'create', `--post_title=${ slug }`, `--post_name=${ slug }`, '--post_status=draft', '--porcelain' );
	const id = output.split( '\n' ).map( ( line ) => line.trim() ).filter( ( line ) => /^\d+$/.test( line ) ).pop();

	if ( setting ) {
		wpCli( 'llms-e2e-seo', 'post', plugin, id, setting );
	}
	wpCli( 'post', 'update', id, '--post_status=publish' );
}

/**
 * The robots tags the page prints, and whether the post has a copy.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {string}                                         slug    Post slug.
 */
async function outcome( request, slug ) {
	const html = await ( await request.get( `/${ slug }/` ) ).text();
	const robots = [ ...html.matchAll( /<meta name=["']robots["'] content=["']([^"']*)/gi ) ].map( ( match ) => match[ 1 ] ).join( ', ' );
	const copy = await request.get( `/wp-content/uploads/llms-helper/${ slug }/index.html.md` );

	return { robots, noindex: robots.includes( 'noindex' ), copy: 200 === copy.status() };
}

for ( const plugin of SEO_PLUGINS ) {
	test.describe( `${ plugin.name } noindex`, () => {
		// Fetch as a logged out visitor, as search engines and agents do.
		test.use( { storageState: { cookies: [], origins: [] } } );

		const slug = ( name ) => `${ plugin.slug }-${ name }`;

		test.beforeAll( () => {
			wpCli( 'plugin', 'activate', plugin.slug );
			wpCli( 'llms-e2e-seo', 'setup', plugin.slug );
			// Options outlive the plugin being removed, so start from a known post type setting.
			wpCli( 'llms-e2e-seo', 'post-type', plugin.slug, 'post', 'reset' );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			wpCli( 'llms-e2e-seo', 'post-type', plugin.slug, 'post', 'reset' );
			await requestUtils.deleteAllPosts();
		} );

		test.afterAll( () => {
			wpCli( 'plugin', 'deactivate', plugin.slug );
		} );

		test( 'a post with no setting keeps its copy', async ( { request } ) => {
			publish( plugin.slug, slug( 'default' ) );

			const result = await outcome( request, slug( 'default' ) );
			expect( result.noindex, result.robots ).toBe( false );
			expect( result.copy ).toBe( true );
		} );

		test( 'a post set to noindex gets no copy', async ( { request } ) => {
			publish( plugin.slug, slug( 'noindex' ), 'noindex' );

			const result = await outcome( request, slug( 'noindex' ) );
			expect( result.noindex, result.robots ).toBe( true );
			expect( result.copy ).toBe( false );
		} );

		test( 'a post in a noindex post type gets no copy', async ( { request } ) => {
			wpCli( 'llms-e2e-seo', 'post-type', plugin.slug, 'post', 'noindex' );
			publish( plugin.slug, slug( 'type' ) );

			const result = await outcome( request, slug( 'type' ) );
			expect( result.noindex, result.robots ).toBe( true );
			expect( result.copy ).toBe( false );
		} );

		test( 'a post set to index in a noindex post type follows what the plugin prints', async ( { request } ) => {
			wpCli( 'llms-e2e-seo', 'post-type', plugin.slug, 'post', 'noindex' );
			publish( plugin.slug, slug( 'index' ), 'index' );

			const result = await outcome( request, slug( 'index' ) );
			expect( result.copy, result.robots ).toBe( ! result.noindex );
		} );
	} );
}
