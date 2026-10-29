<?php
/**
 * Inclusion policy with a fixed answer.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Inclusion_Policy;

class Fixed_Policy implements Inclusion_Policy {

	public function __construct(
		public bool $includes = true
	) {}

	public function includes( WP_Post $post ): bool {
		return $this->includes;
	}
}
