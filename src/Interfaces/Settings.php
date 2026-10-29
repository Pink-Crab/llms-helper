<?php
/**
 * Plugin settings interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

/**
 * Plugin settings.
 */
interface Settings {

	/**
	 * Post types that get copies.
	 *
	 * @return list<string>
	 */
	public function post_types(): array;

	/**
	 * Whether a post type gets copies.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function includes_type( string $post_type ): bool;

	/**
	 * Whether requests get a 301 to the copy's public URL, when the store has one.
	 *
	 * @return bool
	 */
	public function redirect(): bool;

	/**
	 * The llms.txt summary.
	 *
	 * @return string
	 */
	public function summary(): string;

	/**
	 * The llms.txt notes.
	 *
	 * @return string
	 */
	public function notes(): string;

	/**
	 * What a copy URL with no copy returns.
	 *
	 * @return 'page'|'404'|'410' The page it belongs to, or a plain 404 or 410.
	 */
	public function missing_copy(): string;

	/**
	 * Whether search engines may index llms.txt, which also lists it in the Yoast SEO or Rank Math sitemap.
	 *
	 * @return bool
	 */
	public function index_llms_txt(): bool;
}
