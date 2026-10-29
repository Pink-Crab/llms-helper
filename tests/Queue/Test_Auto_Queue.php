<?php
/**
 * Tests for Auto_Queue.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Queue;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Queue\Auto_Queue;
use PinkCrab\LLMs_Helper\Queue\WP_Cron_Queue;
use PinkCrab\LLMs_Helper\Tests\Fakes\Recording_Queue;
use PinkCrab\LLMs_Helper\Queue\Action_Scheduler_Queue;

class Test_Auto_Queue extends WP_UnitTestCase {

	private Recording_Queue $cron_jobs;
	private Recording_Queue $as_jobs;

	public function set_up(): void {
		parent::set_up();
		$this->cron_jobs = new Recording_Queue();
		$this->as_jobs   = new Recording_Queue();
	}

	private function auto_queue( bool $as_ready ): Auto_Queue {
		$cron = new class( $this->cron_jobs ) extends WP_Cron_Queue {
			public function __construct( private Recording_Queue $jobs ) {}
			public function schedule( string $hook, int $timestamp, array $args = array() ): void {
				$this->jobs->schedule( $hook, $timestamp, $args );
			}
			public function is_scheduled( string $hook, ?array $args = null ): bool {
				return $this->jobs->is_scheduled( $hook, $args );
			}
			public function clear( string $hook ): void {
				$this->jobs->clear( $hook );
			}
			public function unschedule( string $hook, array $args ): void {
				$this->jobs->unschedule( $hook, $args );
			}
			public function next_due( string $hook ): ?int {
				return $this->jobs->next_due( $hook );
			}
		};

		$as = new class( $this->as_jobs ) extends Action_Scheduler_Queue {
			public function __construct( private Recording_Queue $jobs ) {}
			public function schedule( string $hook, int $timestamp, array $args = array() ): void {
				$this->jobs->schedule( $hook, $timestamp, $args );
			}
			public function is_scheduled( string $hook, ?array $args = null ): bool {
				return $this->jobs->is_scheduled( $hook, $args );
			}
			public function clear( string $hook ): void {
				$this->jobs->clear( $hook );
			}
			public function unschedule( string $hook, array $args ): void {
				$this->jobs->unschedule( $hook, $args );
			}
			public function next_due( string $hook ): ?int {
				return $this->jobs->next_due( $hook );
			}
		};

		return new class( $cron, $as, $as_ready ) extends Auto_Queue {
			public function __construct( WP_Cron_Queue $wp_cron, Action_Scheduler_Queue $action_scheduler, private bool $ready ) {
				parent::__construct( $wp_cron, $action_scheduler );
			}
			protected function action_scheduler_ready(): bool {
				return $this->ready;
			}
		};
	}

	public function test_uses_wp_cron_when_action_scheduler_is_not_ready(): void {
		$queue = $this->auto_queue( false );
		$queue->schedule( 'hook', 1, array( 1 ) );

		$this->assertCount( 1, $this->cron_jobs->jobs );
		$this->assertCount( 0, $this->as_jobs->jobs );
		$this->assertTrue( $queue->is_scheduled( 'hook' ) );
	}

	public function test_uses_action_scheduler_when_ready(): void {
		$queue = $this->auto_queue( true );
		$queue->schedule( 'hook', 1, array( 1 ) );

		$this->assertCount( 0, $this->cron_jobs->jobs );
		$this->assertCount( 1, $this->as_jobs->jobs );
		$this->assertTrue( $queue->is_scheduled( 'hook', array( 1 ) ) );
	}

	public function test_filter_can_switch_action_scheduler_off(): void {
		add_filter( 'llms_helper_use_action_scheduler', '__return_false' );

		$this->auto_queue( true )->schedule( 'hook', 1 );

		$this->assertCount( 1, $this->cron_jobs->jobs );
		$this->assertCount( 0, $this->as_jobs->jobs );
	}

	public function test_filter_cannot_switch_action_scheduler_on_when_it_is_not_ready(): void {
		add_filter( 'llms_helper_use_action_scheduler', '__return_true' );

		$this->auto_queue( false )->schedule( 'hook', 1 );

		$this->assertCount( 1, $this->cron_jobs->jobs );
	}

	public function test_clear_goes_to_the_current_driver(): void {
		$queue = $this->auto_queue( true );
		$queue->schedule( 'hook', 1 );

		$queue->clear( 'hook' );

		$this->assertFalse( $queue->is_scheduled( 'hook' ) );
	}

	public function test_unschedule_and_next_due_go_to_the_current_driver(): void {
		$queue = $this->auto_queue( true );
		$queue->schedule( 'hook', 20, array( 1 ) );
		$queue->schedule( 'hook', 10, array( 2 ) );

		$this->assertSame( 10, $queue->next_due( 'hook' ) );

		$queue->unschedule( 'hook', array( 2 ) );

		$this->assertSame( 20, $queue->next_due( 'hook' ) );
		$this->assertSame( array( array( 1 ) ), array_column( $this->as_jobs->jobs, 'args' ) );
		$this->assertSame( array(), $this->cron_jobs->jobs );
	}

	public function test_real_action_scheduler_is_not_ready_in_the_test_suite(): void {
		$this->assertFalse( Action_Scheduler_Queue::available() );
	}
}
