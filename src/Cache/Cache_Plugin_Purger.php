<?php
/**
 * Purger that clears the URLs from the cache plugins that are active.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Cache;

use PinkCrab\LLMs_Helper\Interfaces\Cache_Purger;

/**
 * Clears each URL through each cache plugin's own purge for one URL: WP Rocket, W3 Total Cache, WP Super Cache,
 * WP Fastest Cache and LiteSpeed Cache. Also fires llms_helper_purge_urls for anything else, such as a CDN.
 */
class Cache_Plugin_Purger implements Cache_Purger {

	/**
	 * {@inheritDoc}
	 */
	public function purge( array $urls ): void {
		/**
		 * Fires when cached responses for these URLs should be cleared.
		 *
		 * @param list<string> $urls Full URLs.
		 */
		do_action( 'llms_helper_purge_urls', $urls );

		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $urls );
		}

		foreach ( $urls as $url ) {
			if ( function_exists( 'w3tc_flush_url' ) ) {
				w3tc_flush_url( $url );
			}
			if ( function_exists( 'wpsc_delete_url_cache' ) ) {
				wpsc_delete_url_cache( $url );
			}
			if ( function_exists( 'wpfc_clear_cache_by_url' ) ) {
				wpfc_clear_cache_by_url( $url );
			}
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.
			do_action( 'litespeed_purge_url', $url );
		}
	}
}
