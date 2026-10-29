<?php
/**
 * Index generator interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

/**
 * Produces the contents of llms.txt.
 */
interface Index_Generator {

	/**
	 * The llms.txt contents.
	 *
	 * @return string
	 */
	public function generate(): string;
}
