<?php
/**
 * Noindex check interface.
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
 * Decides whether a post is marked noindex.
 */
interface Noindex_Check {

	/**
	 * Whether the post is marked noindex.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public function noindex( WP_Post $post ): bool;
}
