<?php
/**
 * Copy store interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

/**
 * Where the copies and llms.txt are kept, addressed by their decoded URL path (/about/index.html.md, /llms.txt).
 */
interface Copy_Store {

	/**
	 * Save the file for a path.
	 *
	 * @param string $path     Path.
	 * @param string $contents Contents.
	 * @return bool Whether it was saved.
	 */
	public function write( string $path, string $contents ): bool;

	/**
	 * Remove the file for a path.
	 *
	 * @param string $path Path.
	 * @return void
	 */
	public function delete( string $path ): void;

	/**
	 * Whether a file exists for a path.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public function exists( string $path ): bool;

	/**
	 * Contents of the file for a path.
	 *
	 * @param string $path Path.
	 * @return string Empty when there is none.
	 */
	public function read( string $path ): string;

	/**
	 * Public URL the file is served from, for redirects.
	 *
	 * @param string $path Path.
	 * @return string Empty when the store has no public URL.
	 */
	public function public_url( string $path ): string;

	/**
	 * File on local disk, so PHP can send it without reading it into memory.
	 *
	 * @param string $path Path.
	 * @return string Empty when the store is not on local disk.
	 */
	public function local_file( string $path ): string;
}
