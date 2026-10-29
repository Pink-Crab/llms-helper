<?php
/**
 * E2E helper for cache.spec.js: WP Rocket is paid, so while llms_e2e_mock_rocket is set this stands in for its
 * rocket_clean_files(), with its signature, and records the URLs it is given in llms_e2e_rocket_cleaned.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

if ( get_option( 'llms_e2e_mock_rocket' ) && ! function_exists( 'rocket_clean_files' ) ) {
	/**
	 * Records the URLs WP Rocket would clear.
	 *
	 * @param string|array<int, string> $urls        URLs.
	 * @param mixed                     $filesystem  Unused.
	 * @param bool                      $run_actions Unused.
	 * @return void
	 */
	function rocket_clean_files( $urls, $filesystem = null, $run_actions = true ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP Rocket's own name and signature.
		update_option( 'llms_e2e_rocket_cleaned', array_merge( (array) get_option( 'llms_e2e_rocket_cleaned', array() ), (array) $urls ), false );
	}
}
