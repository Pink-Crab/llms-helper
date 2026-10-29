<?php
/**
 * Description source interface.
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
 * Meta descriptions for posts and the site.
 */
interface Description_Source {

	/**
	 * The post's meta description.
	 *
	 * @param WP_Post $post Post.
	 * @return string Empty when there is none.
	 */
	public function post( WP_Post $post ): string;

	/**
	 * The site's home page meta description.
	 *
	 * @return string Empty when there is none.
	 */
	public function site(): string;
}
