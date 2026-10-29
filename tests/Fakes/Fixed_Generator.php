<?php
/**
 * Index generator with fixed contents.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use PinkCrab\LLMs_Helper\Interfaces\Index_Generator;

class Fixed_Generator implements Index_Generator {

	public function __construct(
		public string $contents = "# Fixed\n"
	) {}

	public function generate(): string {
		return $this->contents;
	}
}
