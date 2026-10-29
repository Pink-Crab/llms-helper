<?php
/**
 * Content renderer interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

use WP_Post;

/**
 * Renders a post's content to the HTML its copy is converted from.
 */
interface Content_Renderer {

	/**
	 * Rendered HTML of the post content.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function render( WP_Post $post ): string;
}
