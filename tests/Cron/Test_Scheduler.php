<?php
/**
 * Tests for Scheduler.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Cron;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Tests\Fakes\Recording_Queue;

class Test_Scheduler extends WP_UnitTestCase {

	private Recording_Queue $queue;
	private Scheduler $scheduler;

	public function set_up(): void {
		parent::set_up();
		$this->queue     = new Recording_Queue();
		$this->scheduler = new Scheduler( $this->queue );
	}

	public function test_start_build_gives_a_new_build_and_drops_queued_batches(): void {
		$this->scheduler->queue_batch( 42 );

		$first  = $this->scheduler->start_build();
		$second = $this->scheduler->start_build();

		$this->assertNotSame( $first, $second );
		$this->assertSame( $second, $this->scheduler->current_build() );
		$this->assertFalse( $this->scheduler->build_running() );
	}

	public function test_queue_build_replaces_the_current_build(): void {
		$before = $this->scheduler->start_build();

		$this->scheduler->queue_build();

		$this->assertNotSame( $before, $this->scheduler->current_build() );
	}

	public function test_current_build_reads_past_the_option_cache(): void {
		global $wpdb;
		$this->scheduler->start_build();
		$this->scheduler->current_build();

		// Another request starting a build writes the database, not this request's cache.
		$wpdb->update( $wpdb->options, array( 'option_value' => 'from-another-request' ), array( 'option_name' => Scheduler::OPTION_BUILD ) );

		$this->assertSame( 'from-another-request', $this->scheduler->current_build() );
	}

	public function test_queue_index_runs_after_the_delay(): void {
		$before = time();

		$this->scheduler->queue_index();

		$this->assertCount( 1, $this->queue->jobs );
		$this->assertSame( Scheduler::HOOK_INDEX, $this->queue->jobs[0]['hook'] );
		$this->assertGreaterThanOrEqual( $before + Scheduler::INDEX_DELAY, $this->queue->jobs[0]['timestamp'] );
		$this->assertSame( array(), $this->queue->jobs[0]['args'] );
		$this->assertTrue( $this->scheduler->index_queued() );
	}

	public function test_queue_index_twice_queues_once(): void {
		$this->scheduler->queue_index();
		$this->scheduler->queue_index();

		$this->assertCount( 1, $this->queue->jobs );
	}

	public function test_queue_batch(): void {
		$this->scheduler->queue_batch( 42 );

		$this->assertSame( Scheduler::HOOK_BATCH, $this->queue->jobs[0]['hook'] );
		$this->assertSame( array( 42 ), $this->queue->jobs[0]['args'] );
		$this->assertLessThanOrEqual( time(), $this->queue->jobs[0]['timestamp'] );
		$this->assertTrue( $this->scheduler->build_running() );
	}

	public function test_queue_build_restarts_from_the_first_post(): void {
		$this->scheduler->queue_batch( 42 );
		$this->scheduler->queue_index();

		$this->scheduler->queue_build();

		$batches = array_values(
			array_filter(
				$this->queue->jobs,
				static function ( array $job ): bool {
					return Scheduler::HOOK_BATCH === $job['hook'];
				}
			)
		);
		$this->assertCount( 1, $batches );
		$this->assertSame( array( 0 ), $batches[0]['args'] );
		$this->assertTrue( $this->scheduler->index_queued() );
	}

	public function test_retries_are_queued_after_the_delay_and_dropped_by_attempt(): void {
		$before = time();

		$this->scheduler->queue_retry( 42, 2 );

		$this->assertSame( Scheduler::HOOK_RETRY, $this->queue->jobs[0]['hook'] );
		$this->assertSame( array( 42, 2 ), $this->queue->jobs[0]['args'] );
		$this->assertGreaterThanOrEqual( $before + Scheduler::RETRY_DELAY, $this->queue->jobs[0]['timestamp'] );
		$this->assertTrue( $this->scheduler->build_running() );

		$this->scheduler->unqueue_retry( 42, 3 );
		$this->assertCount( 1, $this->queue->jobs );

		$this->scheduler->unqueue_retry( 42, 2 );
		$this->assertSame( array(), $this->queue->jobs );
	}

	public function test_a_failed_build_is_recorded_until_cleared(): void {
		$this->assertNull( $this->scheduler->failed_after() );

		$this->scheduler->mark_failed( 42 );
		$this->assertSame( 42, $this->scheduler->failed_after() );

		$this->scheduler->clear_failed();
		$this->assertNull( $this->scheduler->failed_after() );
	}

	public function test_start_build_drops_retries_and_the_failure(): void {
		$this->scheduler->queue_retry( 42, 2 );
		$this->scheduler->mark_failed( 42 );

		$this->scheduler->start_build();

		$this->assertFalse( $this->queue->is_scheduled( Scheduler::HOOK_RETRY ) );
		$this->assertNull( $this->scheduler->failed_after() );
	}

	/**
	 * @return array<string, array{string, int, bool}>
	 */
	public function overdue_provider(): array {
		return array(
			'nothing queued'       => array( '', 0, false ),
			'due now'              => array( Scheduler::HOOK_INDEX, 0, false ),
			'just inside the time' => array( Scheduler::HOOK_BATCH, -( Scheduler::OVERDUE_AFTER - 60 ), false ),
			'stuck index'          => array( Scheduler::HOOK_INDEX, -( Scheduler::OVERDUE_AFTER + 60 ), true ),
			'stuck batch'          => array( Scheduler::HOOK_BATCH, -( Scheduler::OVERDUE_AFTER + 60 ), true ),
			'stuck retry'          => array( Scheduler::HOOK_RETRY, -( Scheduler::OVERDUE_AFTER + 60 ), true ),
			'stuck post list'      => array( Scheduler::HOOK_POSTS, -( Scheduler::OVERDUE_AFTER + 60 ), true ),
			'other plugin'         => array( 'other_plugin_job', -( Scheduler::OVERDUE_AFTER + 60 ), false ),
		);
	}

	/**
	 * @dataProvider overdue_provider
	 */
	public function test_overdue( string $hook, int $offset, bool $expected ): void {
		if ( '' !== $hook ) {
			$this->queue->schedule( $hook, time() + $offset, array( 1 ) );
		}

		$this->assertSame( $expected, $this->scheduler->overdue() );
	}

	public function test_queue_posts_queues_lists_of_posts_without_repeats(): void {
		$ids = range( 1, Scheduler::POSTS_PER_JOB + 1 );

		$this->scheduler->queue_posts( array_merge( $ids, array( 1 ) ) );

		$this->assertSame(
			array(
				array( range( 1, Scheduler::POSTS_PER_JOB ) ),
				array( array( Scheduler::POSTS_PER_JOB + 1 ) ),
			),
			array_column( $this->queue->jobs, 'args' )
		);
		$this->assertSame( array( Scheduler::HOOK_POSTS ), array_values( array_unique( array_column( $this->queue->jobs, 'hook' ) ) ) );
	}

	public function test_clear_removes_post_lists_too(): void {
		$this->scheduler->queue_posts( array( 1 ) );

		$this->scheduler->clear();

		$this->assertFalse( $this->queue->is_scheduled( Scheduler::HOOK_POSTS ) );
	}

	public function test_clear_removes_both_jobs(): void {
		$this->scheduler->queue_index();
		$this->scheduler->queue_batch( 1 );

		$this->scheduler->clear();

		$this->assertFalse( $this->scheduler->index_queued() );
		$this->assertFalse( $this->scheduler->build_running() );
	}
}
