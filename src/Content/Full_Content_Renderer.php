<?php
/**
 * Renders the whole post content.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Content_Renderer;

/**
 * The whole post content, every page and past any more tag, as a logged-out visitor sees it.
 */
class Full_Content_Renderer implements Content_Renderer {

	/**
	 * {@inheritDoc}
	 */
	public function render( WP_Post $post ): string {
		$user_id   = get_current_user_id();
		$prev_post = $GLOBALS['post'] ?? null;

		wp_set_current_user( 0 );
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );

		try {
			return $this->filter( $post->post_content );
		} finally {
			$GLOBALS['post'] = $prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			if ( $prev_post instanceof WP_Post ) {
				setup_postdata( $prev_post );
			}
			wp_set_current_user( $user_id );
		}
	}

	/**
	 * Run the raw content through the_content.
	 *
	 * @param string $content Raw post content.
	 * @return string
	 */
	protected function filter( string $content ): string {
		// the_content() would cut at <!--more--> and return one page outside the post's own view.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		return (string) apply_filters( 'the_content', $content );
	}
}
