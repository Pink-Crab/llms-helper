<?php
/**
 * Access check interface.
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
 * Decides whether a logged out visitor is kept from a post's content.
 */
interface Access_Check {

	/**
	 * Whether a logged out visitor is kept from the post's content.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public function restricted( WP_Post $post ): bool;
}
