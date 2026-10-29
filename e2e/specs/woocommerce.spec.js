const { test, expect } = require( '../fixtures' );
const { wpCli, resetSettings } = require( '../fixtures/wp-cli' );

/**
 * Last number WP-CLI printed, for --porcelain IDs.
 *
 * @param {string} output WP-CLI output.
 * @return {string} ID.
 */
function lastId( output ) {
	return output.split( '\n' ).map( ( line ) => line.trim() ).filter( ( line ) => /^\d+$/.test( line ) ).pop();
}

/**
 * Creates a published product through WooCommerce, so its data store saves it.
 *
 * @param {string} slug       Product slug.
 * @param {string} visibility visible, catalog, search or hidden.
 * @return {string} Product ID.
 */
function createProduct( slug, visibility ) {
	return lastId( wpCli( 'wc', 'product', 'create', `--name=${ slug }`, `--slug=${ slug }`, '--status=publish', `--catalog_visibility=${ visibility }`, '--regular_price=10', '--user=admin', '--porcelain' ) );
}

/**
 * Whether the copy file exists in uploads.
 *
 * @param {import('@playwright/test').APIRequestContext} request Request context.
 * @param {string}                                         path    Copy path.
 */
async function hasCopy( request, path ) {
	const copy = await request.get( `/wp-content/uploads/llms-helper${ path }index.html.md`, { maxRedirects: 0 } );

	// Coming soon mode can answer a missing file with its own page, so only front matter counts as a copy.
	return 200 === copy.status() && ( await copy.text() ).startsWith( '---' );
}

test.describe( 'WooCommerce', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( () => {
		wpCli( 'plugin', 'activate', 'woocommerce' );
		// Earlier specs delete every page, so WooCommerce makes its store pages again.
		wpCli( 'wc', 'tool', 'run', 'install_pages', '--user=1' );
		wpCli( 'rewrite', 'flush', '--hard' );
		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["post","page","product"]}', '--format=json' );
	} );

	test.afterEach( () => {
		wpCli( 'option', 'update', 'woocommerce_coming_soon', 'no' );
		const ids = wpCli( 'post', 'list', '--post_type=product,post', '--post_status=any', '--format=ids' ).split( /\s+/ ).filter( ( id ) => /^\d+$/.test( id ) );
		if ( ids.length ) {
			wpCli( 'post', 'delete', ...ids, '--force' );
		}
	} );

	test.afterAll( () => {
		resetSettings();
		wpCli( 'plugin', 'deactivate', 'woocommerce' );
	} );

	for ( const [ visibility, copy ] of [ [ 'visible', true ], [ 'catalog', false ], [ 'search', false ], [ 'hidden', false ] ] ) {
		test( `a product with ${ visibility } catalogue visibility ${ copy ? 'gets a copy' : 'gets no copy' }`, async ( { request } ) => {
			createProduct( `woo-${ visibility }`, visibility );

			expect( await hasCopy( request, `/product/woo-${ visibility }/` ) ).toBe( copy );
		} );
	}

	test( 'hiding a product through WooCommerce removes its copy', async ( { request } ) => {
		const id = createProduct( 'woo-later-hidden', 'visible' );
		expect( await hasCopy( request, '/product/woo-later-hidden/' ) ).toBe( true );

		wpCli( 'wc', 'product', 'update', id, '--catalog_visibility=hidden', '--user=admin' );

		expect( await hasCopy( request, '/product/woo-later-hidden/' ) ).toBe( false );
	} );

	test( 'a product copy has the price, currency, SKU and stock WooCommerce gives it', async ( { request } ) => {
		const id = lastId( wpCli( 'wc', 'product', 'create', '--name=woo-priced', '--slug=woo-priced', '--status=publish', '--regular_price=20', '--sale_price=15', '--sku=E2E-SKU-1', '--user=admin', '--porcelain' ) );
		const woo = ( field ) => wpCli( 'wc', 'product', 'get', id, `--field=${ field }`, '--user=admin' ).split( '\n' ).pop().trim();

		const copy = await ( await request.get( '/wp-content/uploads/llms-helper/product/woo-priced/index.html.md' ) ).text();
		const front = Object.fromEntries(
			copy.split( '---' )[ 1 ].trim().split( '\n' ).map( ( line ) => {
				const at = line.indexOf( ': ' );
				return [ line.slice( 0, at ), JSON.parse( line.slice( at + 2 ) ) ];
			} )
		);

		expect( front.price ).toBe( woo( 'price' ) );
		expect( front.regular_price ).toBe( woo( 'regular_price' ) );
		expect( front.sku ).toBe( woo( 'sku' ) );
		// wp wc product get has no stock_status field, so read where WooCommerce stores it.
		expect( front.stock_status ).toBe( wpCli( 'post', 'meta', 'get', id, '_stock_status' ).split( '\n' ).pop().trim() );
		expect( front.currency ).toBe( wpCli( 'option', 'get', 'woocommerce_currency' ).split( '\n' ).pop().trim() );
	} );

	test( 'a variable product copy has the price range of its variations', async ( { request } ) => {
		const id = lastId( wpCli( 'wc', 'product', 'create', '--name=woo-variable', '--slug=woo-variable', '--type=variable', '--status=publish', '--attributes=[{"name":"Size","options":["S","L"],"variation":true,"visible":true}]', '--user=admin', '--porcelain' ) );
		wpCli( 'wc', 'product_variation', 'create', id, '--regular_price=10', '--attributes=[{"name":"Size","option":"S"}]', '--user=admin' );
		wpCli( 'wc', 'product_variation', 'create', id, '--regular_price=30', '--attributes=[{"name":"Size","option":"L"}]', '--user=admin' );

		const copy = await ( await request.get( '/wp-content/uploads/llms-helper/product/woo-variable/index.html.md' ) ).text();
		const value = ( key ) => Number( JSON.parse( ( copy.match( new RegExp( `^${ key }: (.*)$`, 'm' ) ) || [ '', '""' ] )[ 1 ] ) );

		// WooCommerce formats variation prices to the store's decimals, such as "10.00".
		expect( value( 'price_min' ) ).toBe( 10 );
		expect( value( 'price_max' ) ).toBe( 30 );
	} );

	test( 'with WooCommerce\'s Action Scheduler a build is queued and run there', async ( { request } ) => {
		const pending = () => Number( wpCli( 'action-scheduler', 'action', 'list', '--hook=llms_helper_build_batch', '--status=pending', '--format=count' ).split( '\n' ).pop().trim() );
		createProduct( 'woo-queued', 'visible' );
		expect( await hasCopy( request, '/product/woo-queued/' ) ).toBe( true );

		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["post","page"]}', '--format=json' );
		expect( pending(), 'queued in Action Scheduler' ).toBe( 1 );
		expect( wpCli( 'cron', 'event', 'list', '--hook=llms_helper_build_batch', '--format=count' ).split( '\n' ).pop().trim(), 'not in WP-Cron' ).toBe( '0' );

		for ( let run = 0; run < 20 && pending() > 0; run++ ) {
			wpCli( 'action-scheduler', 'run', '--hooks=llms_helper_build_batch' );
		}

		expect( await hasCopy( request, '/product/woo-queued/' ) ).toBe( false );
		wpCli( 'option', 'update', 'llms_helper_settings', '{"post_types":["post","page","product"]}', '--format=json' );
	} );

	test( 'coming soon for the whole site gives no copies', async ( { request } ) => {
		wpCli( 'option', 'update', 'woocommerce_coming_soon', 'yes' );
		wpCli( 'option', 'update', 'woocommerce_store_pages_only', 'no' );
		wpCli( 'post', 'create', '--post_title=woo-soon-post', '--post_name=woo-soon-post', '--post_content=WOO-SOON-SECRET', '--post_status=publish' );

		const html = await ( await request.get( '/woo-soon-post/' ) ).text();
		expect( html.includes( 'WOO-SOON-SECRET' ), 'WooCommerce hides the post from a visitor' ).toBe( false );
		expect( await hasCopy( request, '/woo-soon-post/' ) ).toBe( false );
	} );

	test( 'coming soon for the store only drops products and keeps posts', async ( { request } ) => {
		wpCli( 'option', 'update', 'woocommerce_coming_soon', 'yes' );
		wpCli( 'option', 'update', 'woocommerce_store_pages_only', 'yes' );
		createProduct( 'woo-soon-product', 'visible' );
		wpCli( 'post', 'create', '--post_title=woo-open-post', '--post_name=woo-open-post', '--post_content=WOO-OPEN', '--post_status=publish' );

		expect( await hasCopy( request, '/product/woo-soon-product/' ) ).toBe( false );
		expect( await hasCopy( request, '/woo-open-post/' ) ).toBe( true );
	} );

	test( 'the cart, checkout and my account pages get no copies', async ( { request } ) => {
		wpCli( 'llms-helper', 'build' );

		for ( const page of [ 'cart', 'checkout', 'myaccount' ] ) {
			const id = lastId( wpCli( 'option', 'get', `woocommerce_${ page }_page_id` ) );
			expect( Number( id ), `${ page } page exists` ).toBeGreaterThan( 0 );

			const slug = wpCli( 'post', 'get', id, '--field=post_name' ).split( '\n' ).pop().trim();
			expect( await hasCopy( request, `/${ slug }/` ), `${ page } page /${ slug }/` ).toBe( false );
		}
	} );
} );
