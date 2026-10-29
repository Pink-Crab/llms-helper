<?php
/**
 * Tests for Lifecycle.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Lifecycle;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Content\Index_Builder;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Lifecycle extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function test_activate_queues_a_build(): void {
		wp_unschedule_hook( Scheduler::HOOK_BATCH );

		$this->container()->get( Lifecycle::class )->activate();

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ) );
	}

	public function test_deactivate_clears_queued_work_and_the_root_file(): void {
		$container = $this->container();
		$index     = $container->get( Index_Builder::class );
		$scheduler = $container->get( Scheduler::class );
		$index->write_root_file( 'ours' );
		$scheduler->queue_index();
		$scheduler->queue_build();

		$container->get( Lifecycle::class )->deactivate();

		$this->assertFalse( $scheduler->index_queued() );
		$this->assertFalse( $scheduler->build_running() );
		$this->assertFileDoesNotExist( $index->root_file() );
	}
}
