<?php
/**
 * Tests for WP_Cron_Queue.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Queue;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Queue\WP_Cron_Queue;

class Test_WP_Cron_Queue extends WP_UnitTestCase {

	private const HOOK = 'llms_helper_test_job';

	public function set_up(): void {
		parent::set_up();
		wp_unschedule_hook( self::HOOK );
	}

	public function test_schedule_adds_a_cron_event(): void {
		$time = time() + 100;

		( new WP_Cron_Queue() )->schedule( self::HOOK, $time, array( 5 ) );

		$this->assertSame( $time, wp_next_scheduled( self::HOOK, array( 5 ) ) );
	}

	public function test_unschedule_removes_only_the_job_with_those_args(): void {
		$queue = new WP_Cron_Queue();
		$queue->schedule( self::HOOK, time() + 100, array( 1 ) );
		$queue->schedule( self::HOOK, time() + 100, array( 2 ) );

		$queue->unschedule( self::HOOK, array( 1 ) );

		$this->assertFalse( $queue->is_scheduled( self::HOOK, array( 1 ) ) );
		$this->assertTrue( $queue->is_scheduled( self::HOOK, array( 2 ) ) );
	}

	public function test_next_due_is_the_earliest_job_or_null(): void {
		$queue = new WP_Cron_Queue();
		$this->assertNull( $queue->next_due( self::HOOK ) );

		$queue->schedule( self::HOOK, time() + 500, array( 1 ) );
		$queue->schedule( self::HOOK, time() - 900, array( 2 ) );

		$this->assertSame( wp_next_scheduled( self::HOOK, array( 2 ) ), $queue->next_due( self::HOOK ) );
	}

	public function test_schedule_skips_a_job_already_queued_with_the_same_args(): void {
		$queue = new WP_Cron_Queue();
		$queue->schedule( self::HOOK, time() + 100, array( 1 ) );
		$queue->schedule( self::HOOK, time() + 500, array( 1 ) );

		$this->assertSame( 1, $this->count_events() );
	}

	public function test_is_scheduled_with_args(): void {
		$queue = new WP_Cron_Queue();
		$queue->schedule( self::HOOK, time() + 100, array( 1 ) );

		$this->assertTrue( $queue->is_scheduled( self::HOOK, array( 1 ) ) );
		$this->assertFalse( $queue->is_scheduled( self::HOOK, array( 2 ) ) );
	}

	public function test_is_scheduled_with_any_args(): void {
		$queue = new WP_Cron_Queue();

		$this->assertFalse( $queue->is_scheduled( self::HOOK ) );

		$queue->schedule( self::HOOK, time() + 100, array( 7 ) );

		$this->assertTrue( $queue->is_scheduled( self::HOOK ) );
	}

	public function test_clear_removes_every_job_for_the_hook(): void {
		$queue = new WP_Cron_Queue();
		$queue->schedule( self::HOOK, time() + 100, array( 1 ) );
		$queue->schedule( self::HOOK, time() + 100, array( 2 ) );

		$queue->clear( self::HOOK );

		$this->assertFalse( $queue->is_scheduled( self::HOOK ) );
	}

	private function count_events(): int {
		$count = 0;
		foreach ( (array) _get_cron_array() as $events ) {
			$count += isset( $events[ self::HOOK ] ) ? count( $events[ self::HOOK ] ) : 0;
		}
		return $count;
	}
}
