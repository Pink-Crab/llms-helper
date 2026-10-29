<?php
/**
 * Renderer giving the content as the site's feeds show it.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Query;

/**
 * The whole post content as get_the_content_feed() gives it: rendered with the main query marked as a feed,
 * then through the_content_feed. Bind it to Content_Renderer with the llms_helper_di_rules filter for sites
 * whose plugins clean their output for feeds.
 */
class Feed_Content_Renderer extends Full_Content_Renderer {

	/**
	 * {@inheritDoc}
	 */
	protected function filter( string $content ): string {
		global $wp_query;

		$query   = $wp_query instanceof WP_Query ? $wp_query : null;
		$is_feed = null !== $query && $query->is_feed;
		if ( null !== $query ) {
			$query->is_feed = true;
		}

		try {
			$content = str_replace( ']]>', ']]&gt;', parent::filter( $content ) );

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
			return (string) apply_filters( 'the_content_feed', $content, get_default_feed() );
		} finally {
			if ( null !== $query ) {
				$query->is_feed = $is_feed;
			}
		}
	}
}
