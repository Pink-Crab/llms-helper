<?php
/**
 * Keeps copies in step with term edits and deletes.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Controllers;

use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;

/**
 * A copy carries its post's term names and may have them in its URL, but editing or deleting a term does not
 * save its posts. So the copies of the term's posts are queued to be built again.
 */
class Term_Events implements Hookable {

	/**
	 * Set the dependencies.
	 *
	 * @param Copy_Builder $copies    Copy builder.
	 * @param Scheduler    $scheduler Background jobs.
	 */
	public function __construct(
		protected Copy_Builder $copies,
		protected Scheduler $scheduler
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( 'edited_term', array( $this, 'term_edited' ), 10, 3 );
		$loader->action( 'delete_term', array( $this, 'term_deleted' ), 10, 5 );
	}

	/**
	 * Queue the term's posts after an edit.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public function term_edited( int $term_id, int $tt_id, string $taxonomy ): void {
		$post_ids = get_objects_in_term( $term_id, $taxonomy );
		$this->queue( is_array( $post_ids ) ? $post_ids : array() );
	}

	/**
	 * Queue the posts that had the term, which WordPress passes as the term is already gone.
	 *
	 * @param int        $term_id      Term ID.
	 * @param int        $tt_id        Term taxonomy ID.
	 * @param string     $taxonomy     Taxonomy.
	 * @param mixed      $deleted_term Deleted term.
	 * @param array<int> $object_ids   IDs of the posts that had the term.
	 * @return void
	 */
	public function term_deleted( int $term_id, int $tt_id, string $taxonomy, $deleted_term, array $object_ids ): void {
		$this->queue( $object_ids );
	}

	/**
	 * Queue the posts among these that have a copy.
	 *
	 * @param array<int|string> $post_ids Post IDs.
	 * @return void
	 */
	protected function queue( array $post_ids ): void {
		$post_ids = array_map( 'intval', $post_ids );
		update_meta_cache( 'post', $post_ids );

		$with_copies = array_filter(
			$post_ids,
			function ( int $post_id ): bool {
				return '' !== $this->copies->copy_path( $post_id );
			}
		);

		$this->scheduler->queue_posts( array_values( $with_copies ) );
	}
}
