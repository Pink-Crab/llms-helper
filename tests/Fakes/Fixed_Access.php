<?php
/**
 * Access check with a fixed answer.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Access_Check;

class Fixed_Access implements Access_Check {

	public function __construct(
		public bool $restricted = false
	) {}

	public function restricted( WP_Post $post ): bool {
		return $this->restricted;
	}
}
