<?php
/**
 * Description source with fixed answers.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Description_Source;

class Fixed_Description implements Description_Source {

	/**
	 * @param string $post Description for every post.
	 * @param string $site Site description.
	 */
	public function __construct(
		public string $post = '',
		public string $site = ''
	) {}

	public function post( WP_Post $post ): string {
		return $this->post;
	}

	public function site(): string {
		return $this->site;
	}
}
