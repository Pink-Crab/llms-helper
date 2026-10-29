const { test: setup } = require( '@wordpress/e2e-test-utils-playwright' );
const { wpCli, resetSettings, installPlugins, installPluginFrom } = require( './fixtures/wp-cli' );
const { SEO_PLUGINS } = require( './fixtures/seo-plugins' );
const { MEMBERSHIP_PLUGINS } = require( './fixtures/membership-plugins' );
const { CACHE_PLUGINS } = require( './fixtures/cache-plugins' );

setup( 'authenticate and prepare the site', async ( { requestUtils } ) => {
	setup.setTimeout( 300_000 );
	await requestUtils.setupRest();

	// Copies are only made with pretty permalinks.
	wpCli( 'rewrite', 'structure', '/%postname%/', '--hard' );
	resetSettings();

	// Installed, not active: the integration specs turn each on in turn.
	installPlugins( [
		...SEO_PLUGINS.map( ( plugin ) => plugin.slug ),
		...MEMBERSHIP_PLUGINS.filter( ( plugin ) => plugin.wordpressOrg ).map( ( plugin ) => plugin.slug ),
		...CACHE_PLUGINS.filter( ( plugin ) => plugin.wordpressOrg ).map( ( plugin ) => plugin.slug ),
		'woocommerce',
	] );
	for ( const plugin of MEMBERSHIP_PLUGINS.filter( ( item ) => item.source ) ) {
		installPluginFrom( plugin.folder, plugin.source );
	}
} );
