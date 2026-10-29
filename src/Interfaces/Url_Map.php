<?php
/**
 * URL map interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

/**
 * Maps pages to the URL paths of their copies and llms.txt, and back.
 */
interface Url_Map {

	/**
	 * Decoded copy path for a permalink.
	 *
	 * @param string $permalink Permalink.
	 * @return string Empty when the permalink cannot have a copy.
	 */
	public function copy_path( string $permalink ): string;

	/**
	 * Copy URL for a permalink.
	 *
	 * @param string $permalink Permalink.
	 * @return string Empty when the permalink cannot have a copy.
	 */
	public function copy_url( string $permalink ): string;

	/**
	 * Whether a requested path (raw, as in the request) is a copy URL.
	 *
	 * @param string $raw_path Requested path.
	 * @return bool
	 */
	public function is_copy_request( string $raw_path ): bool;

	/**
	 * The page URI a requested copy path belongs to.
	 *
	 * @param string $raw_path Requested copy path.
	 * @return string
	 */
	public function page_uri( string $raw_path ): string;

	/**
	 * Decoded path of llms.txt.
	 *
	 * @return string
	 */
	public function index_path(): string;

	/**
	 * Full URL of a decoded path on this site.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public function url_for_path( string $path ): string;
}
