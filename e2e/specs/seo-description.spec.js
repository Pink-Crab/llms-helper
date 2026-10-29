const { test, expect } = require( '../fixtures' );
const { wpCli } = require( '../fixtures/wp-cli' );
const { SEO_PLUGINS } = require( '../fixtures/seo-plugins' );

const CONTENT = '<!-- wp:paragraph --><p>Plain words for any description a plugin makes from the content.</p><!-- /wp:paragraph -->';

/**
 * Publishes a post, setting its own meta description while it is a draft so its copy is built with it.
 *
 * @param {Object}      requestUtils WordPress request utils.
 * @param {string}      plugin       Plugin slug.
 * @param {string}      slug         Post slug.
 * @param {string|null} description  Its own description, or null for none.
 */
async function publish( requestUtils, plugin, slug, description = null ) {
	const post = await requestUtils.createPost( { title: slug, slug, content: CONTENT, status: 'draft' } );

	if ( description ) {
		wpCli( 'llms-e2e-seo', 'description', plugin, String( post.id ), description );
	}
	wpCli( 'post', 'update', String( post.id ), '--post_status=publish' );
}

/**
 * Decodes the entities a meta tag attribute may hold.
 *
 * @param {string} text Attribute value.
 * @return {string} Text.
 */
function decode( text ) {
	return text
		.replace( /&#0?39;|&apos;/g, "'" )
		.replace( /&quot;/g, '"' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&amp;/g, '&' );
}

/**
 * The meta description the page prints, and the description in the copy's front matter.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {string}                                         slug    Post slug.
 */
async function descriptions( request, slug ) {
	const html = await ( await request.get( `/${ slug }/` ) ).text();
	const tag = ( html.match( /<meta\b[^>]*\bname=["']description["'][^>]*>/i ) || [ '' ] )[ 0 ];
	const page = decode( ( tag.match( /\bcontent=["']([^"']*)["']/i ) || [ '', '' ] )[ 1 ] ).trim();

	const copy = await ( await request.get( `/wp-content/uploads/llms-helper/${ slug }/index.html.md` ) ).text();
	const line = copy.split( '\n' ).find( ( row ) => row.startsWith( 'description: ' ) );

	return { page, copy: line ? JSON.parse( line.slice( 'description: '.length ) ) : '' };
}

for ( const plugin of SEO_PLUGINS ) {
	test.describe( `${ plugin.name } descriptions`, () => {
		// Fetch as a logged out visitor, as agents do.
		test.use( { storageState: { cookies: [], origins: [] } } );

		const slug = ( name ) => `${ plugin.slug }-${ name }`;

		test.beforeAll( () => {
			wpCli( 'plugin', 'activate', plugin.slug );
			wpCli( 'llms-e2e-seo', 'setup', plugin.slug );
			wpCli( 'llms-e2e-seo', 'post-type', plugin.slug, 'post', 'reset' );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await requestUtils.deleteAllPosts();
		} );

		test.afterAll( () => {
			wpCli( 'plugin', 'deactivate', plugin.slug );
		} );

		test( 'a post\'s own meta description is in its copy and in llms.txt', async ( { requestUtils, request } ) => {
			const description = `E2E-description-for-${ plugin.slug }`;
			await publish( requestUtils, plugin.slug, slug( 'own' ), description );

			const result = await descriptions( request, slug( 'own' ) );
			expect( result.page, 'the plugin prints the description' ).toBe( description );
			expect( result.copy ).toBe( description );

			wpCli( 'llms-helper', 'index' );
			const llms = await ( await request.get( '/llms.txt' ) ).text();
			expect( llms ).toContain( `/${ slug( 'own' ) }/index.html.md): ${ description }` );
		} );

		test( 'with no description of its own the copy has what the page prints', async ( { requestUtils, request } ) => {
			await publish( requestUtils, plugin.slug, slug( 'none' ) );

			const result = await descriptions( request, slug( 'none' ) );
			expect( result.copy ).toBe( result.page );
		} );

		if ( plugin.siteDescription ) {
			test( 'the site description is the llms.txt summary', async ( { request } ) => {
				const description = `E2E-site-description-for-${ plugin.slug }`;
				wpCli( 'llms-e2e-seo', 'site-description', plugin.slug, description );

				try {
					const html = await ( await request.get( '/' ) ).text();
					expect( html, 'the plugin prints it on the home page' ).toContain( description );

					wpCli( 'llms-helper', 'index' );
					const llms = await ( await request.get( '/llms.txt' ) ).text();
					expect( llms ).toContain( `> ${ description }` );
				} finally {
					wpCli( 'llms-e2e-seo', 'site-description', plugin.slug, '' );
				}
			} );
		}
	} );
}
