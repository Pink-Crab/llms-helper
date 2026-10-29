<?php
/**
 * Rank Math provider for the llms sitemap.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Sitemap;

use RankMath\Sitemap\Router;
use RankMath\Sitemap\Providers\Provider;

/**
 * Adds llms-sitemap.xml to Rank Math's sitemap index. Only built when Rank Math has loaded its interface.
 */
class Rank_Math_Provider implements Provider {

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
	public function get_index_links( $max_entries ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Rank Math's signature.
		return $this->sitemap->index_links( Router::get_base_url( Llms_Txt_Sitemap::file_name() ) );
	}

	/**
	 * Sitemap entries.
	 *
	 * @param string $type         Sitemap type.
	 * @param int    $max_entries  Entries per sitemap.
	 * @param int    $current_page Sitemap page.
	 * @return list<array{loc: string, mod: string}>
	 */
	public function get_sitemap_links( $type, $max_entries, $current_page ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Rank Math's signature.
		return $this->sitemap->links();
	}
}
