const { test, expect } = require( '../fixtures' );
const { wpCli, resetSettings } = require( '../fixtures/wp-cli' );

const CONTENT = '<!-- wp:paragraph --><p>Sent directly.</p><!-- /wp:paragraph -->';

test.describe( 'Settings page', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=llms-helper' );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		resetSettings();
		// With redirect mode back on this also removes a site root llms.txt.
		wpCli( 'llms-helper', 'index' );
		await requestUtils.deleteAllPosts();
	} );

	async function save( page ) {
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
	}

	test( 'warns when jobs are overdue and when a build failed', async ( { admin, page } ) => {
		const overdue = Math.floor( Date.now() / 1000 ) - 1200;
		// WordPress will not add a past event while an identical one is queued, so start with none.
		try {
			wpCli( 'cron', 'event', 'delete', 'llms_helper_build_index' );
		} catch ( error ) {
			// None queued.
		}
		wpCli( 'cron', 'event', 'schedule', 'llms_helper_build_index', String( overdue ) );
		wpCli( 'option', 'update', 'llms_helper_build_failed', '42' );

		try {
			await admin.visitAdminPage( 'options-general.php', 'page=llms-helper' );

			await expect( page.getByText( /more than 15 minutes overdue/ ) ).toBeVisible();
			await expect( page.getByText( /The last build stopped after post 42/ ) ).toBeVisible();
		} finally {
			wpCli( 'cron', 'event', 'delete', 'llms_helper_build_index' );
			wpCli( 'option', 'delete', 'llms_helper_build_failed' );
		}
	} );

	test( 'lists public post types by name and type', async ( { page } ) => {
		await expect( page.getByRole( 'heading', { name: 'LLMs Helper', level: 1 } ) ).toBeVisible();
		await expect( page.getByRole( 'checkbox', { name: 'Posts (post)' } ) ).toBeChecked();
		await expect( page.getByRole( 'checkbox', { name: 'Pages (page)' } ) ).toBeChecked();
		await expect( page.getByRole( 'checkbox', { name: /\(attachment\)/ } ) ).toHaveCount( 0 );
	} );

	test( 'a copy URL with no copy can send a 410', async ( { page, request } ) => {
		await page.getByLabel( 'Copy URL with no copy' ).selectOption( '410' );
		await save( page );

		const response = await request.get( '/no-such-page/index.html.md', { maxRedirects: 0 } );
		expect( response.status() ).toBe( 410 );
	} );

	test( 'with redirect mode off the copy is sent from its own URL', async ( { page, request, requestUtils } ) => {
		await requestUtils.createPost( { title: 'E2E Direct', slug: 'e2e-direct', content: CONTENT, status: 'publish' } );

		await page.getByRole( 'checkbox', { name: /Send a 301 from llms\.txt/ } ).uncheck();
		await save( page );

		const response = await request.get( '/e2e-direct/index.html.md', { maxRedirects: 0 } );
		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'text/markdown' );
		expect( await response.text() ).toContain( '# E2E Direct' );
	} );
} );
