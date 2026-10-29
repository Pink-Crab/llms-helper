const { test, expect } = require( '../fixtures' );
const { wpCli } = require( '../fixtures/wp-cli' );
const { MEMBERSHIP_PLUGINS } = require( '../fixtures/membership-plugins' );

const SECRET = 'MEMBERS-ONLY-SECRET';

// The secret sits past the 55 word automatic excerpt some plugins show visitors in place of the content.
const CONTENT = `<!-- wp:paragraph --><p>${ 'Public intro word. '.repeat( 40 ) }</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>${ SECRET }</p><!-- /wp:paragraph -->`;

/**
 * Publishes a post, restricting it while it is a draft so its copy is built with the restriction.
 *
 * @param {Object}  requestUtils WordPress request utils.
 * @param {string}  plugin       Plugin slug.
 * @param {string}  slug         Post slug.
 * @param {boolean} restrict     Restrict it to members.
 */
async function publish( requestUtils, plugin, slug, restrict ) {
	const post = await requestUtils.createPost( { title: slug, slug, content: CONTENT, status: 'draft' } );

	if ( restrict ) {
		wpCli( 'llms-e2e-access', 'restrict', plugin, String( post.id ) );
	}
	wpCli( 'post', 'update', String( post.id ), '--post_status=publish' );
}

/**
 * Whether a visitor sees the secret on the page, and the copy's contents if it has one.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {string}                                         slug    Post slug.
 */
async function outcome( request, slug ) {
	// Redirects are not followed: a visitor sent elsewhere, such as a login page, does not see the content.
	const page = await request.get( `/${ slug }/`, { maxRedirects: 0 } );
	const copy = await request.get( `/wp-content/uploads/llms-helper/${ slug }/index.html.md`, { maxRedirects: 0 } );
	const text = 200 === copy.status() ? await copy.text() : '';

	return {
		visible: 200 === page.status() && ( await page.text() ).includes( SECRET ),
		// A private site answers a missing file with its login page, so only front matter counts as a copy.
		copy: text.startsWith( '---' ) ? text : null,
	};
}

for ( const plugin of MEMBERSHIP_PLUGINS ) {
	test.describe( `${ plugin.name } restrictions`, () => {
		// Fetch as a logged out visitor, as agents do.
		test.use( { storageState: { cookies: [], origins: [] } } );

		const slug = ( name ) => `${ plugin.slug }-${ name }`;

		test.beforeAll( () => {
			for ( const required of plugin.requires ) {
				wpCli( 'plugin', 'activate', required );
			}
			wpCli( 'plugin', 'activate', plugin.folder );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			wpCli( 'llms-e2e-access', 'reset', plugin.slug );
			await requestUtils.deleteAllPosts();
		} );

		test.afterAll( () => {
			wpCli( 'plugin', 'deactivate', plugin.folder, ...plugin.requires );
		} );

		test( 'a post open to visitors keeps its copy', async ( { requestUtils, request } ) => {
			await publish( requestUtils, plugin.slug, slug( 'open' ), false );

			const result = await outcome( request, slug( 'open' ) );
			expect( result.visible, 'a visitor sees the content' ).toBe( true );
			expect( result.copy ).toContain( SECRET );
		} );

		test( 'a post for members only gets no copy', async ( { requestUtils, request } ) => {
			await publish( requestUtils, plugin.slug, slug( 'members' ), true );

			const result = await outcome( request, slug( 'members' ) );
			expect( result.visible, 'the plugin hides the content from a visitor' ).toBe( false );
			expect( result.copy ).toBeNull();
		} );

		if ( plugin.shortcode ) {
			test( 'a members only shortcode in a public post stays out of the copy', async ( { requestUtils, request } ) => {
				const content = `<!-- wp:paragraph --><p>PUBLIC-PART</p><!-- /wp:paragraph --><!-- wp:shortcode -->${ plugin.shortcode.replace( '%s', SECRET ) }<!-- /wp:shortcode -->`;
				await requestUtils.createPost( { title: slug( 'shortcode' ), slug: slug( 'shortcode' ), content, status: 'publish' } );

				const result = await outcome( request, slug( 'shortcode' ) );
				expect( result.visible, 'the plugin hides the shortcode content from a visitor' ).toBe( false );
				expect( result.copy ).toContain( 'PUBLIC-PART' );
				expect( result.copy ).not.toContain( SECRET );
			} );
		}

		if ( plugin.blockAttrs ) {
			test( 'a block for logged in users only stays out of the copy', async ( { requestUtils, request } ) => {
				const content = `<!-- wp:paragraph --><p>PUBLIC-PART</p><!-- /wp:paragraph --><!-- wp:paragraph ${ plugin.blockAttrs } --><p>${ SECRET }</p><!-- /wp:paragraph -->`;
				await requestUtils.createPost( { title: slug( 'block' ), slug: slug( 'block' ), content, status: 'publish' } );

				const result = await outcome( request, slug( 'block' ) );
				expect( result.visible, 'the plugin hides the block from a visitor' ).toBe( false );
				expect( result.copy ).toContain( 'PUBLIC-PART' );
				expect( result.copy ).not.toContain( SECRET );
			} );
		}

		if ( plugin.siteGate ) {
			test( 'a site closed to visitors gives no copies', async ( { requestUtils, request } ) => {
				wpCli( 'llms-e2e-access', 'gate', plugin.slug );
				await publish( requestUtils, plugin.slug, slug( 'gated' ), false );

				const result = await outcome( request, slug( 'gated' ) );
				expect( result.visible, 'the plugin hides the site from a visitor' ).toBe( false );
				expect( result.copy ).toBeNull();
			} );
		}
	} );
}
