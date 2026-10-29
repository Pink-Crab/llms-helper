const { test, expect } = require( '../fixtures' );
const { wpCli } = require( '../fixtures/wp-cli' );

const CONTENT = '<!-- wp:paragraph --><p>RENDERED-BODY</p><!-- /wp:paragraph -->';

/**
 * The copy of a post published now.
 *
 * @param {Object}                                       requestUtils WordPress request utils.
 * @param {import('@playwright/test').APIRequestContext} request      Request context.
 * @param {string}                                       slug         Post slug.
 */
async function copyOf( requestUtils, request, slug ) {
	await requestUtils.createPost( { title: slug, slug, content: CONTENT, status: 'publish' } );

	return ( await request.get( `/wp-content/uploads/llms-helper/${ slug }/index.html.md` ) ).text();
}

test.describe( 'Content renderer', () => {
	test.afterEach( async ( { requestUtils } ) => {
		try {
			wpCli( 'option', 'delete', 'llms_e2e_feed_renderer' );
		} catch ( error ) {
			// Not set.
		}
		await requestUtils.deleteAllPosts();
	} );

	test( 'the default renderer gives the content as the post page shows it', async ( { requestUtils, request } ) => {
		const copy = await copyOf( requestUtils, request, 'renderer-default' );

		expect( copy ).toContain( 'RENDERED-BODY' );
		expect( copy ).not.toContain( 'FEED-ONLY-TEXT' );
	} );

	test( 'the feed renderer, bound through llms_helper_di_rules, gives the content as feeds show it', async ( { requestUtils, request } ) => {
		wpCli( 'option', 'update', 'llms_e2e_feed_renderer', '1' );

		const copy = await copyOf( requestUtils, request, 'renderer-feed' );

		expect( copy ).toContain( 'RENDERED-BODY' );
		expect( copy ).toContain( 'FEED-ONLY-TEXT' );
	} );
} );
