<?php
/**
 * Yoast SEO provider for the llms sitemap.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Sitemap;

use WPSEO_Sitemaps_Router;
use WPSEO_Sitemap_Provider;

/**
 * Adds llms-sitemap.xml to Yoast SEO's sitemap index. Only built when Yoast SEO has loaded its interface.
 */
class Yoast_Provider implements WPSEO_Sitemap_Provider {

	/**
	 * Set the sitemap.
	 *
	 * @param Llms_Txt_Sitemap $sitemap Sitemap.
	 */
	public function __construct(
		protected Llms_Txt_Sitemap $sitemap
	) {}

	/**
	 * Whether this provider builds a sitemap type.
	 *
	 * @param string $type Sitemap type.
	 * @return bool
	 */
	public function handles_type( $type ) {
		return Llms_Txt_Sitemap::TYPE === $type;
	}

	/**
	 * Sitemap index entries.
	 *
	 * @param int $max_entries Entries per sitemap.
	 * @return list<array{loc: string, lastmod: string}>
	 */
	public function get_index_links( $max_entries ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Yoast SEO's signature.
		return $this->sitemap->index_links( WPSEO_Sitemaps_Router::get_base_url( Llms_Txt_Sitemap::file_name() ) );
	}

	/**
	 * Sitemap entries.
	 *
	 * @param string $type         Sitemap type.
	 * @param int    $max_entries  Entries per sitemap.
	 * @param int    $current_page Sitemap page.
	 * @return list<array{loc: string, mod: string}>
	 */
	public function get_sitemap_links( $type, $max_entries, $current_page ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Yoast SEO's signature.
		return $this->sitemap->links();
	}
}
