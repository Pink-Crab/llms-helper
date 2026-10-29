<?php
/**
 * Builds the copies of the selected post types and llms.txt.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Interfaces\Settings;

/**
 * Builds the copies of the selected post types and llms.txt, in queued batches or in one run.
 * Copies already up to date are skipped.
 */
class Builder {

	/**
	 * Posts per batch.
	 *
	 * @var int
	 */
	public const BATCH_SIZE = 50;

	/**
	 * Set the dependencies.
	 *
	 * @param Copy_Builder  $copies    Copy builder.
	 * @param Index_Builder $index     Writes llms.txt.
	 * @param Settings      $settings  Settings.
	 * @param Scheduler     $scheduler Background jobs.
	 */
	public function __construct(
		protected Copy_Builder $copies,
		protected Index_Builder $index,
		protected Settings $settings,
		protected Scheduler $scheduler
	) {}

	/**
	 * Build one batch, the queued job's first run.
	 *
	 * @param int $after_id Last post ID done by the previous batch, 0 to start.
	 * @return void
	 */
	public function batch( int $after_id = 0 ): void {
		$this->run( $after_id, 1 );
	}

	/**
	 * Run a batch again, as its earlier run did not finish.
	 *
	 * @param int $after_id Last post ID before the batch.
	 * @param int $attempt  Which run this is.
	 * @return void
	 */
	public function retry( int $after_id, int $attempt ): void {
		$this->run( $after_id, $attempt );
	}

	/**
	 * Build one batch, then queue the next or finish with llms.txt.
	 *
	 * @param int $after_id Last post ID before the batch.
	 * @param int $attempt  Which run this is.
	 * @return void
	 */
	protected function run( int $after_id, int $attempt ): void {
		$build = $this->scheduler->current_build();

		// If this run dies, a fatal error or a timeout, the retry carries the build on. The last run allowed
		// records the build as failed, which stays if it dies too.
		if ( $attempt < Scheduler::MAX_ATTEMPTS ) {
			$this->scheduler->queue_retry( $after_id, $attempt + 1 );
		} else {
			$this->scheduler->mark_failed( $after_id );
		}

		if ( 0 === $after_id ) {
			$this->remove_unlisted();
		}

		$ids = $this->next_ids( $after_id );
		foreach ( $ids as $id ) {
			$this->copies->build( $id, true );
		}

		$this->scheduler->unqueue_retry( $after_id, $attempt + 1 );
		$this->scheduler->clear_failed();

		// A newer build started while this batch ran, and it goes through every post, so this one stops here.
		if ( $this->scheduler->current_build() !== $build ) {
			return;
		}

		if ( count( $ids ) === self::BATCH_SIZE ) {
			$this->scheduler->queue_batch( (int) end( $ids ) );
			return;
		}

		$this->index->build();
	}

	/**
	 * Build these posts' copies, even when the posts themselves are unchanged, such as after a term edit.
	 *
	 * @param array<int|string> $post_ids Post IDs.
	 * @return void
	 */
	public function posts( array $post_ids ): void {
		foreach ( $post_ids as $id ) {
			$this->copies->build( (int) $id );
		}
	}

	/**
	 * Build in one run.
	 *
	 * @param callable(int, int): void|null $progress Called after each batch with the last post ID and copies built so far.
	 * @return int Copies built.
	 */
	public function all( ?callable $progress = null ): int {
		// Replaces any queued build, which would only repeat this one.
		$this->scheduler->start_build();
		$this->remove_unlisted();

		$after = 0;
		$built = 0;

		do {
			$ids = $this->next_ids( $after );
			foreach ( $ids as $id ) {
				if ( $this->copies->build( $id, true ) ) {
					++$built;
				}
				$after = $id;
			}

			if ( null !== $progress ) {
				$progress( $after, $built );
			}

			$fetched = count( $ids );
		} while ( self::BATCH_SIZE === $fetched );

		$this->index->build();

		return $built;
	}

	/**
	 * Next published post IDs of the included types.
	 *
	 * @param int $after_id Only IDs above this.
	 * @return list<int>
	 */
	public function next_ids( int $after_id ): array {
		global $wpdb;

		$types = $this->settings->post_types();
		if ( array() === $types ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is one %s per post type.
		$ids = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d",
				...array_merge( $types, array( $after_id, self::BATCH_SIZE ) )
			)
		);
		// phpcs:enable

		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * Remove copies whose post is gone, unpublished or of a type no longer included.
	 *
	 * @return void
	 */
	public function remove_unlisted(): void {
		global $wpdb;

		$types = $this->settings->post_types();
		$args  = array( Copy_Builder::META_FILE );
		$where = '1 = 1';

		if ( array() !== $types ) {
			$where = "p.ID IS NULL OR p.post_status <> 'publish' OR p.post_type NOT IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')';
			$args  = array_merge( $args, $types );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds one %s per post type.
		$ids = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND ({$where})",
				...$args
			)
		);
		// phpcs:enable

		foreach ( $ids as $id ) {
			$this->copies->remove( (int) $id );
		}
	}
}
