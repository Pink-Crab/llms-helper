<?php
/**
 * Content renderer with a fixed answer.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Content_Renderer;

class Fixed_Renderer implements Content_Renderer {

	public function __construct(
		public string $html = '<p>Rendered</p>'
	) {}

	public function render( WP_Post $post ): string {
		return $this->html;
	}
}
