<?php
/**
 * Cache purger that records the URLs it is given.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use PinkCrab\LLMs_Helper\Interfaces\Cache_Purger;

class Recording_Purger implements Cache_Purger {

	/**
	 * @var list<string>
	 */
	public array $urls = array();

	public function purge( array $urls ): void {
		array_push( $this->urls, ...$urls );
	}
}
