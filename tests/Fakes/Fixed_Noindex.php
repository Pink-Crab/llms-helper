<?php
/**
 * Noindex check with a fixed answer.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Noindex_Check;

class Fixed_Noindex implements Noindex_Check {

	public function __construct(
		public bool $noindex = false
	) {}

	public function noindex( WP_Post $post ): bool {
		return $this->noindex;
	}
}
