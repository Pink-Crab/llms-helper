<?php
/**
 * Keeps copies in step with post saves and deletes.
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
use PinkCrab\LLMs_Helper\Content\Copy_Builder;

/**
 * Builds a post's copy once it is saved, and removes it before the post is deleted.
 */
class Post_Events implements Hookable {

	/**
	 * Set the copy builder.
	 *
	 * @param Copy_Builder $copies Copy builder.
	 */
	public function __construct(
		protected Copy_Builder $copies
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( 'wp_after_insert_post', array( $this, 'after_insert_post' ), 10, 2 );
		$loader->action( 'before_delete_post', array( $this, 'before_delete_post' ) );
		$loader->action( 'llms_helper_build_post', array( $this, 'build_post' ) );
	}

	/**
	 * Build or remove a post's copy on request: do_action( 'llms_helper_build_post', $post_id ).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function build_post( int $post_id ): void {
		$this->copies->build( $post_id );
	}

	/**
	 * Build or remove a post's copy once it is saved with its terms and meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function after_insert_post( int $post_id, WP_Post $post ): void {
		if ( $this->copies->tracks( $post ) ) {
			$this->copies->build( $post_id );
		}
	}

	/**
	 * Remove a post's copy before it is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function before_delete_post( int $post_id ): void {
		$this->copies->remove( $post_id );
	}
}
