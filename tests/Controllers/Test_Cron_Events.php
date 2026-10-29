<?php
/**
 * Tests for Cron_Events.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Builder;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Content\Index_Builder;
use PinkCrab\LLMs_Helper\Controllers\Cron_Events;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Cron_Events extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function set_up(): void {
		parent::set_up();
		delete_option( Option_Settings::OPTION );
	}

	public function tear_down(): void {
		$this->remove_store();
		parent::tear_down();
	}

	public function test_registers_the_job_hooks(): void {
		$container = $this->container();
		$loader    = new Hook_Loader();
		$container->get( Cron_Events::class )->register( $loader );

		$hooks = array_map(
			static function ( array $hook ): array {
				return array( $hook['hook'], $hook['callback'] );
			},
			$loader->hooks()
		);

		$this->assertSame(
			array(
				array( Scheduler::HOOK_INDEX, array( $container->get( Index_Builder::class ), 'build' ) ),
				array( Scheduler::HOOK_BATCH, array( $container->get( Builder::class ), 'batch' ) ),
				array( Scheduler::HOOK_RETRY, array( $container->get( Builder::class ), 'retry' ) ),
				array( Scheduler::HOOK_POSTS, array( $container->get( Builder::class ), 'posts' ) ),
			),
			$hooks
		);
	}

	public function test_the_booted_plugin_builds_llms_txt_on_the_index_hook(): void {
		do_action( Scheduler::HOOK_INDEX );

		$this->assertTrue( $this->container()->get( Copy_Store::class )->exists( '/llms.txt' ) );
	}

	public function test_the_booted_plugin_runs_a_batch_on_the_batch_hook(): void {
		do_action( Scheduler::HOOK_BATCH, 0 );

		$this->assertTrue( $this->container()->get( Copy_Store::class )->exists( '/llms.txt' ) );
	}
}
