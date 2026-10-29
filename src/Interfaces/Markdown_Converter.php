<?php
/**
 * Markdown converter interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

/**
 * Converts rendered HTML to Markdown.
 */
interface Markdown_Converter {

	/**
	 * Convert an HTML fragment.
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $base_url Page URL, for relative links.
	 * @return string
	 */
	public function convert( string $html, string $base_url ): string;
}
