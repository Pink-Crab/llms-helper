<?php
/**
 * Discovery link for a page's copy.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Controllers;

use WP_Post;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;

/**
 * Prints a rel="alternate" link to the current page's copy, as llmstxt.org suggests.
 */
class Discovery_Link implements Hookable {

	/**
	 * Set the dependencies.
	 *
	 * @param Copy_Builder $copies Copy builder.
	 * @param Url_Map      $urls   URL mapping.
	 */
	public function __construct(
		protected Copy_Builder $copies,
		protected Url_Map $urls
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( 'wp_head', array( $this, 'output' ), 1 );
	}

	/**
	 * Print the link when the current page has a copy.
	 *
	 * @return void
	 */
	public function output(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || '' === $this->copies->copy_path( $post->ID ) ) {
			return;
		}

		$url = $this->urls->copy_url( (string) get_permalink( $post ) );
		if ( '' === $url ) {
			return;
		}

		printf( '<link rel="alternate" type="text/markdown" href="%s" />' . "\n", esc_url( $url ) );
	}
}
