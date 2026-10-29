<?php
/**
 * The llms sitemap, listing llms.txt.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Sitemap;

use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;

/**
 * Entries for the llms sitemap, in the link arrays Yoast SEO and Rank Math providers return. Empty unless the site
 * lets search engines index llms.txt and llms.txt has been built.
 */
class Llms_Txt_Sitemap {

	/**
	 * Sitemap type, routed by the SEO plugins from llms-sitemap.xml.
	 *
	 * @var string
	 */
	public const TYPE = 'llms';

	/**
	 * Set the settings, store and URLs.
	 *
	 * @param Settings   $settings Settings.
	 * @param Copy_Store $store    Where llms.txt is stored.
	 * @param Url_Map    $urls     URLs.
	 */
	public function __construct(
		protected Settings $settings,
		protected Copy_Store $store,
		protected Url_Map $urls
	) {}

	/**
	 * The sitemap's file name.
	 *
	 * @return string
	 */
	public static function file_name(): string {
		return self::TYPE . '-sitemap.xml';
	}

	/**
	 * The sitemap's one entry, llms.txt.
	 *
	 * @return list<array{loc: string, mod: string}>
	 */
	public function links(): array {
		$path = $this->urls->index_path();
		if ( ! $this->settings->index_llms_txt() || ! $this->store->exists( $path ) ) {
			return array();
		}

		return array(
			array(
				'loc' => $this->urls->url_for_path( $path ),
				'mod' => gmdate( DATE_W3C, (int) filemtime( $this->store->local_file( $path ) ) ),
			),
		);
	}

	/**
	 * The sitemap as a sitemap index entry, when it has an entry.
	 *
	 * @param string $sitemap_url The sitemap's URL.
	 * @return list<array{loc: string, lastmod: string}>
	 */
	public function index_links( string $sitemap_url ): array {
		$links = $this->links();
		if ( array() === $links ) {
			return array();
		}

		return array(
			array(
				'loc'     => $sitemap_url,
				'lastmod' => $links[0]['mod'],
			),
		);
	}
}
