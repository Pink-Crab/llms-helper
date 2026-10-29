const { test, expect } = require( '../fixtures' );
const { wpCli } = require( '../fixtures/wp-cli' );

const CONTENT = '<!-- wp:paragraph --><p>Listed.</p><!-- /wp:paragraph -->';

test.describe( 'llms.txt', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'lists published posts with links to their copies', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'E2E Listed', slug: 'e2e-listed', content: CONTENT, status: 'publish' } );
		wpCli( 'llms-helper', 'index' );

		const redirect = await request.get( '/llms.txt', { maxRedirects: 0 } );
		expect( redirect.status() ).toBe( 301 );
		expect( redirect.headers().location ).toMatch( /\/wp-content\/uploads\/llms-helper\/llms\.txt$/ );

		const text = await ( await request.get( '/llms.txt' ) ).text();
		expect( text ).toMatch( /^# / );
		expect( text ).toContain( '## Posts' );
		expect( text ).toMatch( /- \[E2E Listed\]\(http:\/\/localhost:\d+\/e2e-listed\/index\.html\.md\)/ );
	} );

	test( 'leaves out posts that are not published', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'E2E Draft', slug: 'e2e-draft', content: CONTENT, status: 'draft' } );
		wpCli( 'llms-helper', 'index' );

		const text = await ( await request.get( '/llms.txt' ) ).text();
		expect( text ).not.toContain( 'E2E Draft' );
	} );
} );
