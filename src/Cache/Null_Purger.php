<?php
/**
 * Default cache purger.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Cache;

use PinkCrab\LLMs_Helper\Interfaces\Cache_Purger;

/**
 * Default purger: purges nothing, but fires an action other code can hook a cache clear onto.
 */
class Null_Purger implements Cache_Purger {

	/**
	 * {@inheritDoc}
	 */
	public function purge( array $urls ): void {
		/**
		 * Fires when cached responses for these URLs should be cleared.
		 *
		 * @param list<string> $urls Full URLs.
		 */
		do_action( 'llms_helper_purge_urls', $urls );
	}
}
