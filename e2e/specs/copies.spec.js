const { test, expect } = require( '../fixtures' );

const CONTENT = '<!-- wp:paragraph --><p>Hello <strong>world</strong>.</p><!-- /wp:paragraph -->';

test.describe( 'Markdown copies', () => {
	// Fetch as a logged out visitor; WordPress shows drafts to admins.
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'publishing a post writes its copy, sent by a 301 to uploads', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'E2E Copy', slug: 'e2e-copy', content: CONTENT, status: 'publish' } );

		const redirect = await request.get( '/e2e-copy/index.html.md', { maxRedirects: 0 } );
		expect( redirect.status() ).toBe( 301 );
		expect( redirect.headers()[ 'x-redirect-by' ] ).toBe( 'llms-helper' );
		expect( redirect.headers().location ).toMatch( /\/wp-content\/uploads\/llms-helper\/e2e-copy\/index\.html\.md$/ );

		const copy = await request.get( '/e2e-copy/index.html.md' );
		expect( copy.status() ).toBe( 200 );
		const markdown = await copy.text();
		expect( markdown ).toContain( 'title: "E2E Copy"' );
		expect( markdown ).toContain( '# E2E Copy' );
		expect( markdown ).toContain( 'Hello **world**.' );
	} );

	test( 'updating a post rewrites its copy', async ( { requestUtils, request } ) => {
		const post = await requestUtils.createPost( { title: 'E2E Copy', slug: 'e2e-copy', content: CONTENT, status: 'publish' } );

		await requestUtils.rest( { method: 'POST', path: `/wp/v2/posts/${ post.id }`, data: { title: 'E2E Copy Updated' } } );

		const markdown = await ( await request.get( '/e2e-copy/index.html.md' ) ).text();
		expect( markdown ).toContain( '# E2E Copy Updated' );
	} );

	test( 'unpublishing a post removes its copy', async ( { requestUtils, request } ) => {
		const post = await requestUtils.createPost( { title: 'E2E Copy', slug: 'e2e-copy', content: CONTENT, status: 'publish' } );
		const file = ( await request.get( '/e2e-copy/index.html.md', { maxRedirects: 0 } ) ).headers().location;

		await requestUtils.rest( { method: 'POST', path: `/wp/v2/posts/${ post.id }`, data: { status: 'draft' } } );

		expect( ( await request.get( file ) ).status() ).toBe( 404 );
		// With no copy the URL renders its page, which a draft does not have.
		expect( ( await request.get( '/e2e-copy/index.html.md', { maxRedirects: 0 } ) ).status() ).toBe( 404 );
	} );

	test( 'a copy URL with no copy renders the page it belongs to', async ( { request } ) => {
		const response = await request.get( '/category/uncategorized/index.html.md', { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'text/html' );
		expect( await response.text() ).toContain( 'Uncategorized' );
	} );

	test( 'the post page links to its copy', async ( { requestUtils, page } ) => {
		await requestUtils.createPost( { title: 'E2E Copy', slug: 'e2e-copy', content: CONTENT, status: 'publish' } );

		await page.goto( '/e2e-copy/' );

		await expect( page.locator( 'link[rel="alternate"][type="text/markdown"]' ) ).toHaveAttribute( 'href', /\/e2e-copy\/index\.html\.md$/ );
	} );
} );
