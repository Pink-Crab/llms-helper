<?php
/**
 * Cache purger interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

/**
 * Clears cached responses (page cache, CDN) for URLs whose copy changed.
 */
interface Cache_Purger {

	/**
	 * Purge cached responses for URLs.
	 *
	 * @param list<string> $urls Full URLs.
	 * @return void
	 */
	public function purge( array $urls ): void;
}
