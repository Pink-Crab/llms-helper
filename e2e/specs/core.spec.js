const { test, expect } = require( '../fixtures' );
const { wpCli } = require( '../fixtures/wp-cli' );

const CONTENT = '<!-- wp:paragraph --><p>Core body.</p><!-- /wp:paragraph -->';
const BATCH = 'llms_helper_build_batch';
const INDEX = 'llms_helper_build_index';
const POSTS = 'llms_helper_build_posts';
const RETRY = 'llms_helper_build_retry';

/**
 * Last line of WP-CLI output.
 *
 * @param {string} output WP-CLI output.
 * @return {string} Line.
 */
const last = ( output ) => output.split( '\n' ).pop().trim();

/**
 * Number of WP-Cron events queued for a hook.
 *
 * @param {string} hook Hook name.
 * @return {number} Count.
 */
const queued = ( hook ) => Number( last( wpCli( 'cron', 'event', 'list', `--hook=${ hook }`, '--format=count' ) ) );

/**
 * Runs a hook's queued events until none are left, as WP-Cron would over several requests.
 *
 * @param {string} hook Hook name.
 */
function runQueue( hook ) {
	for ( let run = 0; run < 20 && queued( hook ) > 0; run++ ) {
		wpCli( 'cron', 'event', 'run', hook );
	}
}

/**
 * Whether a path under the copies folder exists. A missing one falls through to WordPress's 404.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {string}                                         path    Path under uploads/llms-helper.
 */
async function exists( request, path ) {
	return 404 !== ( await request.get( `/wp-content/uploads/llms-helper${ path }`, { maxRedirects: 0 } ) ).status();
}

test.describe( 'Core behaviour', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	// Settings, queue and test options, in one WP-CLI call (llms-e2e-core.php).
	test.beforeEach( () => {
		wpCli( 'llms-e2e-core', 'reset' );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		wpCli( 'llms-e2e-core', 'reset' );
		await requestUtils.deleteAllPages();
		await requestUtils.deleteAllPosts();
	} );

	test( 'saving only the summary queues llms.txt and no build', () => {
		wpCli( 'option', 'update', 'llms_helper_settings', '{"summary":"Only the summary"}', '--format=json' );

		expect( queued( INDEX ) ).toBe( 1 );
		expect( queued( BATCH ) ).toBe( 0 );
	} );

	test( 'saving only the missing copy setting queues nothing', () => {
		wpCli( 'option', 'update', 'llms_helper_settings', '{"missing_copy":"410"}', '--format=json' );

		expect( queued( INDEX ) ).toBe( 0 );
		expect( queued( BATCH ) ).toBe( 0 );
	} );

	test( 'changing the post types queues a build that removes and adds copies from the queue', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'core-queue', slug: 'core-queue', content: CONTENT, status: 'publish' } );
		expect( await exists( request, '/core-queue/index.html.md' ) ).toBe( true );

		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["page"]}', '--format=json' );
		expect( queued( BATCH ) ).toBe( 1 );
		runQueue( BATCH );
		expect( await exists( request, '/core-queue/index.html.md' ) ).toBe( false );

		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["post","page"]}', '--format=json' );
		runQueue( BATCH );
		expect( await exists( request, '/core-queue/index.html.md' ) ).toBe( true );
	} );

	test( 'llms.txt is built from the queue after a post is published', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'core-listed', slug: 'core-listed', content: CONTENT, status: 'publish' } );
		expect( queued( INDEX ) ).toBe( 1 );

		runQueue( INDEX );

		expect( await ( await request.get( '/llms.txt' ) ).text() ).toContain( '/core-listed/index.html.md' );
	} );

	test( 'a second build writes no copies that are up to date', async ( { requestUtils } ) => {
		await requestUtils.createPost( { title: 'core-current', slug: 'core-current', content: CONTENT, status: 'publish' } );

		wpCli( 'llms-helper', 'build' );
		const second = last( wpCli( 'llms-helper', 'build' ) );

		expect( second ).toContain( '0 copies built' );
	} );

	test( 'wp llms-helper build replaces a queued build', () => {
		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["page"]}', '--format=json' );
		expect( queued( BATCH ) ).toBe( 1 );

		wpCli( 'llms-helper', 'build' );

		expect( queued( BATCH ) ).toBe( 0 );
	} );

	test( 'removing the last copy in a folder removes the folder', async ( { requestUtils, request } ) => {
		const parent = await requestUtils.createPage( { title: 'core-parent', slug: 'core-parent', content: CONTENT, status: 'publish' } );
		const child = await requestUtils.createPage( { title: 'core-child', slug: 'core-child', content: CONTENT, status: 'publish', parent: parent.id } );
		expect( await exists( request, '/core-parent/core-child/index.html.md' ) ).toBe( true );

		await requestUtils.rest( { method: 'DELETE', path: `/wp/v2/pages/${ child.id }`, params: { force: true } } );

		expect( await exists( request, '/core-parent/core-child/' ) ).toBe( false );
		expect( await exists( request, '/core-parent/index.html.md' ) ).toBe( true );
	} );

	test( 'an untitled post is titled by its slug in its copy', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: '', slug: 'core-untitled', content: CONTENT, status: 'publish' } );

		const copy = await ( await request.get( '/wp-content/uploads/llms-helper/core-untitled/index.html.md' ) ).text();

		expect( copy ).toContain( '# core-untitled' );
	} );

	test( 'wp llms-helper build with a post ID builds that post from how it is now', async ( { requestUtils, request } ) => {
		const post = await requestUtils.createPost( { title: 'core-cli', slug: 'core-cli', content: CONTENT, status: 'publish' } );
		// Setting the exclude flag directly does not save the post, so its copy stays until it is built.
		wpCli( 'post', 'meta', 'update', String( post.id ), '_llms_helper_exclude', '1' );
		expect( await exists( request, '/core-cli/index.html.md' ) ).toBe( true );

		expect( last( wpCli( 'llms-helper', 'build', String( post.id ) ) ) ).toBe( `Post ${ post.id }: no copy.` );
		expect( await exists( request, '/core-cli/index.html.md' ) ).toBe( false );
	} );

	test( 'the llms_helper_build_post action builds a post from how it is now', async ( { requestUtils, request } ) => {
		const post = await requestUtils.createPost( { title: 'core-action', slug: 'core-action', content: CONTENT, status: 'publish' } );
		wpCli( 'post', 'meta', 'update', String( post.id ), '_llms_helper_exclude', '1' );
		expect( await exists( request, '/core-action/index.html.md' ) ).toBe( true );

		wpCli( 'eval', `do_action("llms_helper_build_post",${ post.id });` );

		expect( await exists( request, '/core-action/index.html.md' ) ).toBe( false );
	} );

	test( 'renaming a category rebuilds its posts\' copies from the queue', async ( { requestUtils, request } ) => {
		const category = await requestUtils.rest( { method: 'POST', path: '/wp/v2/categories', data: { name: 'Core Old Name', slug: 'core-old-name' } } );
		await requestUtils.createPost( { title: 'core-term', slug: 'core-term', content: CONTENT, status: 'publish', categories: [ category.id ] } );
		const copy = async () => ( await request.get( '/wp-content/uploads/llms-helper/core-term/index.html.md' ) ).text();
		expect( await copy() ).toContain( 'Core Old Name' );

		try {
			await requestUtils.rest( { method: 'POST', path: `/wp/v2/categories/${ category.id }`, data: { name: 'Core New Name' } } );
			expect( queued( POSTS ) ).toBe( 1 );
			runQueue( POSTS );

			expect( await copy() ).toContain( 'Core New Name' );
			expect( await copy() ).not.toContain( 'Core Old Name' );
		} finally {
			await requestUtils.rest( { method: 'DELETE', path: `/wp/v2/categories/${ category.id }`, params: { force: true } } );
		}
	} );

	test( 'deleting a category rebuilds its posts\' copies from the queue', async ( { requestUtils, request } ) => {
		const category = await requestUtils.rest( { method: 'POST', path: '/wp/v2/categories', data: { name: 'Core Gone', slug: 'core-gone' } } );
		await requestUtils.createPost( { title: 'core-term-gone', slug: 'core-term-gone', content: CONTENT, status: 'publish', categories: [ category.id ] } );
		const copy = async () => ( await request.get( '/wp-content/uploads/llms-helper/core-term-gone/index.html.md' ) ).text();
		expect( await copy() ).toContain( 'Core Gone' );

		await requestUtils.rest( { method: 'DELETE', path: `/wp/v2/categories/${ category.id }`, params: { force: true } } );
		expect( queued( POSTS ) ).toBe( 1 );
		runQueue( POSTS );

		expect( await copy() ).not.toContain( 'Core Gone' );
	} );

	test( 'a batch that dies is run again from its retry and the build finishes', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'core-retry', slug: 'core-retry', content: CONTENT, status: 'publish' } );
		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["page"]}', '--format=json' );
		runQueue( BATCH );
		expect( await exists( request, '/core-retry/index.html.md' ) ).toBe( false );

		wpCli( 'option', 'update', 'llms_e2e_crash_build', '1' );
		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["post","page"]}', '--format=json' );
		let died = false;
		try {
			wpCli( 'cron', 'event', 'run', BATCH );
		} catch ( error ) {
			died = true;
		}
		expect( died, 'the batch run died' ).toBe( true );
		expect( queued( RETRY ) ).toBe( 1 );
		expect( await exists( request, '/core-retry/index.html.md' ) ).toBe( false );

		wpCli( 'option', 'delete', 'llms_e2e_crash_build' );
		wpCli( 'cron', 'event', 'run', RETRY );

		expect( await exists( request, '/core-retry/index.html.md' ) ).toBe( true );
		expect( queued( RETRY ) ).toBe( 0 );
	} );

	test( 'llms.txt sent by PHP asks not to be indexed, and copies do not', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'core-header', slug: 'core-header', content: CONTENT, status: 'publish' } );
		wpCli( 'llms-helper', 'index' );
		// Redirect mode off, with the site root llms.txt not yet written, so PHP sends it.
		wpCli( 'option', 'update', 'llms_helper_settings', '{"redirect":false}', '--format=json' );

		const llms = await request.get( '/llms.txt', { maxRedirects: 0 } );
		expect( llms.status() ).toBe( 200 );
		expect( llms.headers()[ 'x-robots-tag' ] ).toBe( 'noindex' );

		const copy = await request.get( '/core-header/index.html.md', { maxRedirects: 0 } );
		expect( copy.status() ).toBe( 200 );
		expect( copy.headers()[ 'x-robots-tag' ] ).toBeUndefined();
	} );

	test( 'llms.txt sent by PHP may be indexed when the site lets search engines index it', async ( { requestUtils, request } ) => {
		await requestUtils.createPost( { title: 'core-indexed', slug: 'core-indexed', content: CONTENT, status: 'publish' } );
		wpCli( 'llms-helper', 'index' );
		// Redirect mode off, with the site root llms.txt not yet written, so PHP sends it.
		wpCli( 'option', 'update', 'llms_helper_settings', '{"redirect":false,"index_llms_txt":true}', '--format=json' );

		const llms = await request.get( '/llms.txt', { maxRedirects: 0 } );
		expect( llms.status() ).toBe( 200 );
		expect( llms.headers()[ 'x-robots-tag' ] ).toBeUndefined();
	} );

	test( 'the purge action gets the copy URL and the file URL', async ( { requestUtils } ) => {
		wpCli( 'option', 'update', 'llms_e2e_record_purges', '1' );

		await requestUtils.createPost( { title: 'core-purged', slug: 'core-purged', content: CONTENT, status: 'publish' } );

		const purged = JSON.parse( last( wpCli( 'option', 'get', 'llms_e2e_purged', '--format=json' ) ) );
		expect( purged ).toContainEqual( expect.stringMatching( /^http:\/\/localhost:\d+\/core-purged\/index\.html\.md$/ ) );
		expect( purged ).toContainEqual( expect.stringMatching( /\/wp-content\/uploads\/llms-helper\/core-purged\/index\.html\.md$/ ) );
	} );

	test( 'a copy built while an editor saves is rendered for a visitor on the front end', async ( { requestUtils, request } ) => {
		const context = '<!-- wp:shortcode -->[llms_e2e_context]<!-- /wp:shortcode -->';
		await requestUtils.createPost( { title: 'core-context', slug: 'core-context', content: context, status: 'publish' } );

		const copy = await ( await request.get( '/wp-content/uploads/llms-helper/core-context/index.html.md' ) ).text();
		expect( copy ).toContain( 'CONTEXT:visitor-front' );
	} );

	test( 'a site that cannot reach itself still gets the copy, rendered for a visitor in the save request', async ( { requestUtils, request } ) => {
		wpCli( 'option', 'update', 'llms_e2e_break_loopback', '1' );
		const context = '<!-- wp:shortcode -->[llms_e2e_context]<!-- /wp:shortcode -->';
		await requestUtils.createPost( { title: 'core-fallback', slug: 'core-fallback', content: context, status: 'publish' } );

		const copy = await ( await request.get( '/wp-content/uploads/llms-helper/core-fallback/index.html.md' ) ).text();
		expect( copy ).toContain( 'CONTEXT:visitor-rest' );
	} );
} );
