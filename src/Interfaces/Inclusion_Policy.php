<?php
/**
 * Inclusion policy interface.
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
 * Decides which posts get a copy.
 */
interface Inclusion_Policy {

	/**
	 * Whether a post should have a copy.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public function includes( WP_Post $post ): bool;
}
