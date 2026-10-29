<?php
/**
 * Queues the plugin's background jobs.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Cron;

use PinkCrab\LLMs_Helper\Interfaces\Queue;

/**
 * Queues the llms.txt build and the batched copy build.
 */
class Scheduler {

	/**
	 * Hook that builds llms.txt.
	 *
	 * @var string
	 */
	public const HOOK_INDEX = 'llms_helper_build_index';

	/**
	 * Hook that builds one batch of copies.
	 *
	 * @var string
	 */
	public const HOOK_BATCH = 'llms_helper_build_batch';

	/**
	 * Hook that runs a batch again when its first run did not finish.
	 *
	 * @var string
	 */
	public const HOOK_RETRY = 'llms_helper_build_retry';

	/**
	 * Seconds before a batch that has not finished is run again.
	 *
	 * @var int
	 */
	public const RETRY_DELAY = 300;

	/**
	 * Runs of one batch before the build is given up as failed.
	 *
	 * @var int
	 */
	public const MAX_ATTEMPTS = 3;

	/**
	 * Option holding the post ID a failed build stopped after.
	 *
	 * @var string
	 */
	public const OPTION_FAILED = 'llms_helper_build_failed';

	/**
	 * Seconds past due before a queued job counts as stuck, which means WP-Cron is not running.
	 *
	 * @var int
	 */
	public const OVERDUE_AFTER = 900;

	/**
	 * Hook that builds a list of posts' copies.
	 *
	 * @var string
	 */
	public const HOOK_POSTS = 'llms_helper_build_posts';

	/**
	 * Posts per queued list.
	 *
	 * @var int
	 */
	public const POSTS_PER_JOB = 50;

	/**
	 * Seconds before a queued llms.txt build runs, so bulk edits share one.
	 *
	 * @var int
	 */
	public const INDEX_DELAY = 60;

	/**
	 * Option holding the ID of the current build. A newer build replaces it, and batches of older builds stop.
	 *
	 * @var string
	 */
	public const OPTION_BUILD = 'llms_helper_build';

	/**
	 * Set the queue.
	 *
	 * @param Queue $queue Background job queue.
	 */
	public function __construct(
		protected Queue $queue
	) {}

	/**
	 * Queue an llms.txt build, unless one is already queued.
	 *
	 * @return void
	 */
	public function queue_index(): void {
		$this->queue->schedule( self::HOOK_INDEX, time() + self::INDEX_DELAY );
	}

	/**
	 * Queue a build from the first post, replacing any build queued or running.
	 *
	 * @return void
	 */
	public function queue_build(): void {
		$this->start_build();
		$this->queue_batch( 0 );
	}

	/**
	 * Make a new build the current one: drop the queued batches of any other, and record its ID so a batch
	 * still running for an older build stops when it finishes.
	 *
	 * @return string Build ID.
	 */
	public function start_build(): string {
		$build = wp_generate_uuid4();
		update_option( self::OPTION_BUILD, $build, false );
		$this->queue->clear( self::HOOK_BATCH );
		$this->queue->clear( self::HOOK_RETRY );
		delete_option( self::OPTION_FAILED );

		return $build;
	}

	/**
	 * Queue a batch to run again in case its current run does not finish.
	 *
	 * @param int $after_id Last post ID before the batch.
	 * @param int $attempt  Which run the retry would be.
	 * @return void
	 */
	public function queue_retry( int $after_id, int $attempt ): void {
		$this->queue->schedule( self::HOOK_RETRY, time() + self::RETRY_DELAY, array( $after_id, $attempt ) );
	}

	/**
	 * Drop a batch's retry once the batch has finished.
	 *
	 * @param int $after_id Last post ID before the batch.
	 * @param int $attempt  Which run the retry would have been.
	 * @return void
	 */
	public function unqueue_retry( int $after_id, int $attempt ): void {
		$this->queue->unschedule( self::HOOK_RETRY, array( $after_id, $attempt ) );
	}

	/**
	 * Record where the current build stops if its last allowed run does not finish.
	 *
	 * @param int $after_id Last post ID before the batch.
	 * @return void
	 */
	public function mark_failed( int $after_id ): void {
		update_option( self::OPTION_FAILED, $after_id, false );
	}

	/**
	 * Clear the failure record once a run finishes.
	 *
	 * @return void
	 */
	public function clear_failed(): void {
		delete_option( self::OPTION_FAILED );
	}

	/**
	 * The post ID a failed build stopped after.
	 *
	 * @return int|null Null when no build has failed.
	 */
	public function failed_after(): ?int {
		$after_id = get_option( self::OPTION_FAILED );

		return false === $after_id ? null : (int) $after_id;
	}

	/**
	 * Whether any of this plugin's jobs is overdue by more than OVERDUE_AFTER, which means WP-Cron is not running.
	 *
	 * @return bool
	 */
	public function overdue(): bool {
		foreach ( array( self::HOOK_INDEX, self::HOOK_BATCH, self::HOOK_RETRY, self::HOOK_POSTS ) as $hook ) {
			$due = $this->queue->next_due( $hook );
			if ( null !== $due && $due < time() - self::OVERDUE_AFTER ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * ID of the current build, read from the database, as another request may have started a newer one.
	 *
	 * @return string Empty before the first build.
	 */
	public function current_build(): string {
		wp_cache_delete( self::OPTION_BUILD, 'options' );

		return (string) get_option( self::OPTION_BUILD, '' );
	}

	/**
	 * Queue the batch after a post ID.
	 *
	 * @param int $after_id Last post ID done.
	 * @return void
	 */
	public function queue_batch( int $after_id ): void {
		$this->queue->schedule( self::HOOK_BATCH, time(), array( $after_id ) );
	}

	/**
	 * Queue the copies of these posts to be built, in lists of POSTS_PER_JOB.
	 *
	 * @param list<int> $post_ids Post IDs.
	 * @return void
	 */
	public function queue_posts( array $post_ids ): void {
		foreach ( array_chunk( array_values( array_unique( $post_ids ) ), self::POSTS_PER_JOB ) as $ids ) {
			$this->queue->schedule( self::HOOK_POSTS, time(), array( $ids ) );
		}
	}

	/**
	 * Whether an llms.txt build is queued.
	 *
	 * @return bool
	 */
	public function index_queued(): bool {
		return $this->queue->is_scheduled( self::HOOK_INDEX );
	}

	/**
	 * Whether a build batch, or a batch's retry, is queued.
	 *
	 * @return bool
	 */
	public function build_running(): bool {
		return $this->queue->is_scheduled( self::HOOK_BATCH ) || $this->queue->is_scheduled( self::HOOK_RETRY );
	}

	/**
	 * Remove every queued job.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->queue->clear( self::HOOK_INDEX );
		$this->queue->clear( self::HOOK_BATCH );
		$this->queue->clear( self::HOOK_RETRY );
		$this->queue->clear( self::HOOK_POSTS );
	}
}
