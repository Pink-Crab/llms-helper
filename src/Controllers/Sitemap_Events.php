<?php
/**
 * Lists llms.txt in the Yoast SEO and Rank Math sitemaps.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Controllers;

use WPSEO_Sitemaps_Cache;
use RankMath\Sitemap\Cache_Watcher;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Sitemap\Yoast_Provider;
use PinkCrab\LLMs_Helper\Sitemap\Llms_Txt_Sitemap;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Sitemap\Rank_Math_Provider;

/**
 * Adds the llms sitemap providers, and clears each plugin's cached llms sitemap and index when llms.txt is rebuilt
 * or the indexing setting changes.
 */
class Sitemap_Events implements Hookable {

	/**
	 * Set the sitemap and URLs.
	 *
	 * @param Llms_Txt_Sitemap $sitemap Sitemap.
	 * @param Url_Map          $urls    URLs.
	 */
	public function __construct(
		protected Llms_Txt_Sitemap $sitemap,
		protected Url_Map $urls
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->filter( 'wpseo_sitemaps_providers', array( $this, 'yoast_providers' ) );
		$loader->filter( 'rank_math/sitemap/providers', array( $this, 'rank_math_providers' ) );
		$loader->action( 'llms_helper_purge_urls', array( $this, 'purged' ) );
		$loader->action( 'add_option_' . Option_Settings::OPTION, array( $this, 'settings_added' ), 10, 2 );
		$loader->action( 'update_option_' . Option_Settings::OPTION, array( $this, 'settings_changed' ), 10, 2 );
	}

	/**
	 * Add the Yoast SEO provider.
	 *
	 * @param mixed $providers Providers.
	 * @return array<int, object>
	 */
	public function yoast_providers( $providers ): array {
		$providers = (array) $providers;
		if ( interface_exists( 'WPSEO_Sitemap_Provider' ) ) {
			$providers[] = new Yoast_Provider( $this->sitemap );
		}

		return $providers;
	}

	/**
	 * Add the Rank Math provider.
	 *
	 * @param mixed $providers Providers.
	 * @return array<int, object>
	 */
	public function rank_math_providers( $providers ): array {
		$providers = (array) $providers;
		if ( interface_exists( 'RankMath\Sitemap\Providers\Provider' ) ) {
			$providers[] = new Rank_Math_Provider( $this->sitemap );
		}

		return $providers;
	}

	/**
	 * Clear the cached sitemaps when llms.txt is among the purged URLs.
	 *
	 * @param list<string> $urls Purged URLs.
	 * @return void
	 */
	public function purged( array $urls ): void {
		if ( in_array( $this->urls->url_for_path( $this->urls->index_path() ), $urls, true ) ) {
			$this->clear_caches();
		}
	}

	/**
	 * Clear the cached sitemaps when the settings are first saved.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Saved value.
	 * @return void
	 */
	public function settings_added( string $option, $value ): void {
		$this->settings_changed( Option_Settings::defaults(), $value );
	}

	/**
	 * Clear the cached sitemaps when indexing llms.txt is turned on or off.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public function settings_changed( $old_value, $value ): void {
		$was = Option_Settings::parse( $old_value )['index_llms_txt'];
		if ( Option_Settings::parse( $value )['index_llms_txt'] !== $was ) {
			$this->clear_caches();
		}
	}

	/**
	 * Clear the llms sitemap and the index from Yoast SEO's and Rank Math's caches.
	 *
	 * @infection-ignore-all Calls the SEO plugins, which the test suite does not load.
	 * @return void
	 */
	public function clear_caches(): void {
		if ( class_exists( 'WPSEO_Sitemaps_Cache' ) ) {
			WPSEO_Sitemaps_Cache::invalidate( Llms_Txt_Sitemap::TYPE );
			WPSEO_Sitemaps_Cache::clear_queued();
		}
		if ( class_exists( 'RankMath\Sitemap\Cache_Watcher' ) ) {
			Cache_Watcher::invalidate( Llms_Txt_Sitemap::TYPE );
			Cache_Watcher::clear_queued();
		}
	}
}
