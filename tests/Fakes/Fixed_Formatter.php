<?php
/**
 * Copy formatter with predictable output.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Formatter;

class Fixed_Formatter implements Copy_Formatter {

	public function format( WP_Post $post ): string {
		return 'copy of ' . $post->ID;
	}
}
