const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

/**
 * Runs WP-CLI on the wp-env tests site and returns its output.
 *
 * @param {...string} args WP-CLI arguments.
 * @return {string} Output.
 */
function wpCli( ...args ) {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'tests-cli', 'wp', ...args ], {
		cwd: path.resolve( __dirname, '../..' ),
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} ).trim();
}

/**
 * Puts the plugin settings back to their defaults.
 */
function resetSettings() {
	try {
		wpCli( 'option', 'delete', 'llms_helper_settings' );
	} catch ( error ) {
		// Nothing saved.
	}
}

/**
 * Installs plugins from wordpress.org without activating them, skipping any already installed.
 *
 * @param {string[]} slugs Plugin slugs.
 */
function installPlugins( slugs ) {
	const missing = slugs.filter( ( slug ) => {
		try {
			wpCli( 'plugin', 'is-installed', slug );
			return false;
		} catch ( error ) {
			return true;
		}
	} );

	if ( missing.length ) {
		wpCli( 'plugin', 'install', ...missing );
	}
}

/**
 * Installs a plugin from a zip URL without activating it, unless its folder is already there.
 *
 * @param {string} folder Plugin folder once installed.
 * @param {string} url    Zip URL.
 */
function installPluginFrom( folder, url ) {
	try {
		wpCli( 'plugin', 'is-installed', folder );
	} catch ( error ) {
		wpCli( 'plugin', 'install', url );
	}
}

module.exports = { wpCli, resetSettings, installPlugins, installPluginFrom };
