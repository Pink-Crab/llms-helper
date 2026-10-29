const { test, expect } = require( '../fixtures' );
const { wpCli } = require( '../fixtures/wp-cli' );

const CONTENT = '<!-- wp:paragraph --><p>Excluded.</p><!-- /wp:paragraph -->';

test.describe( 'Editor meta box', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'excluding a post removes its copy', async ( { admin, editor, page, requestUtils, request } ) => {
		const post = await requestUtils.createPost( { title: 'E2E Exclude', slug: 'e2e-exclude', content: CONTENT, status: 'publish' } );
		const file = ( await request.get( '/e2e-exclude/index.html.md', { maxRedirects: 0 } ) ).headers().location;

		await admin.editPost( post.id );
		await editor.openDocumentSettingsSidebar();
		await expect( page.getByRole( 'link', { name: 'View copy' } ) ).toHaveAttribute( 'href', /\/e2e-exclude\/index\.html\.md$/ );

		await page.getByRole( 'checkbox', { name: 'Exclude from Markdown copies and llms.txt' } ).check();
		await page.getByRole( 'region', { name: 'Editor top bar' } ).getByRole( 'button', { name: 'Save', exact: true } ).click();

		// Meta boxes save after the post, so wait for the copy to go.
		await expect.poll( async () => ( await request.get( file ) ).status() ).toBe( 404 );
	} );

	test( 'the Markdown override replaces the content in the copy', async ( { admin, editor, page, requestUtils, request } ) => {
		const post = await requestUtils.createPost( { title: 'E2E Override', slug: 'e2e-override', content: CONTENT, status: 'publish' } );
		const copy = async () => ( await request.get( '/wp-content/uploads/llms-helper/e2e-override/index.html.md' ) ).text();
		expect( await copy() ).toContain( 'Excluded.' );

		await admin.editPost( post.id );
		await editor.openDocumentSettingsSidebar();
		await page.getByLabel( 'Markdown override' ).fill( '## Written for agents\n\nOVERRIDE-TEXT' );
		await page.getByRole( 'region', { name: 'Editor top bar' } ).getByRole( 'button', { name: 'Save', exact: true } ).click();

		// Meta boxes save after the post.
		await expect.poll( copy ).toContain( 'OVERRIDE-TEXT' );
		expect( await copy() ).toContain( '# E2E Override' );
		expect( await copy() ).not.toContain( 'Excluded.' );
	} );

	test( 'the build button builds the copy from how the post is now', async ( { admin, editor, page, requestUtils, request } ) => {
		const post = await requestUtils.createPost( { title: 'E2E Build Button', slug: 'e2e-build-button', content: CONTENT, status: 'publish' } );
		const file = ( await request.get( '/e2e-build-button/index.html.md', { maxRedirects: 0 } ) ).headers().location;
		// Setting the exclude flag directly does not save the post, so its copy stays until it is built.
		wpCli( 'post', 'meta', 'update', String( post.id ), '_llms_helper_exclude', '1' );
		expect( ( await request.get( file ) ).status() ).toBe( 200 );

		await admin.editPost( post.id );
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'link', { name: 'Build copy now' } ).click();

		await expect( page.getByText( 'Copy built.' ) ).toBeVisible();
		expect( ( await request.get( file ) ).status() ).toBe( 404 );
	} );
} );
