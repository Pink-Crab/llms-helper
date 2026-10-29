<?php
/**
 * Tests for Builder.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Content\Builder;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Builder extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Builder $builder;
	private Copy_Builder $copies;
	private Copy_Store $store;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		$this->unhook_plugin_post_events();
		delete_option( Option_Settings::OPTION );

		$container     = $this->container();
		$this->builder = $container->get( Builder::class );
		$this->copies  = $container->get( Copy_Builder::class );
		$this->store   = $container->get( Copy_Store::class );
		wp_unschedule_hook( Scheduler::HOOK_BATCH );
		wp_unschedule_hook( Scheduler::HOOK_RETRY );
		delete_option( Scheduler::OPTION_FAILED );
	}

	public function tear_down(): void {
		$this->remove_store();
		parent::tear_down();
	}

	/**
	 * @return list<int>
	 */
	private function published_pages( int $count ): array {
		return self::factory()->post->create_many(
			$count,
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
	}

	public function test_next_ids_returns_published_posts_of_included_types_after_an_id(): void {
		update_option( Option_Settings::OPTION, array( 'post_types' => array( 'page' ) ) );
		$pages = $this->published_pages( 3 );
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->assertSame( $pages, $this->builder->next_ids( 0 ) );
		$this->assertSame( array_slice( $pages, 1 ), $this->builder->next_ids( $pages[0] ) );
	}

	public function test_next_ids_is_empty_with_no_post_types(): void {
		$this->published_pages( 1 );
		update_option( Option_Settings::OPTION, array( 'post_types' => array() ) );

		$this->assertSame( array(), $this->builder->next_ids( 0 ) );
	}

	public function test_a_short_batch_builds_every_copy_and_llms_txt(): void {
		$pages = $this->published_pages( 2 );

		$this->builder->batch( 0 );

		foreach ( $pages as $id ) {
			$this->assertNotSame( '', $this->copies->copy_path( $id ) );
		}
		$this->assertTrue( $this->store->exists( '/llms.txt' ) );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( end( $pages ) ) ) );
	}

	public function test_a_full_batch_queues_the_next_one(): void {
		$pages = $this->published_pages( Builder::BATCH_SIZE + 1 );

		$this->builder->batch( 0 );

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( $pages[ Builder::BATCH_SIZE - 1 ] ) ) );
		$this->assertFalse( $this->store->exists( '/llms.txt' ) );
	}

	public function test_first_batch_removes_copies_that_no_longer_belong(): void {
		$page  = $this->published_pages( 1 )[0];
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $page, Copy_Builder::META_FILE, '/page/index.html.md' );
		update_post_meta( $draft, Copy_Builder::META_FILE, '/draft/index.html.md' );
		$this->store->write( '/draft/index.html.md', 'x' );
		update_option( Option_Settings::OPTION, array( 'post_types' => array( 'post' ) ) );

		$this->builder->batch( 0 );

		$this->assertSame( '', $this->copies->copy_path( $page ) );
		$this->assertSame( '', $this->copies->copy_path( $draft ) );
		$this->assertFalse( $this->store->exists( '/draft/index.html.md' ) );
	}

	public function test_later_batches_do_not_remove_anything(): void {
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $draft, Copy_Builder::META_FILE, '/draft/index.html.md' );

		$this->builder->batch( 999999 );

		$this->assertSame( '/draft/index.html.md', $this->copies->copy_path( $draft ) );
	}

	public function test_remove_unlisted_clears_copies_of_deleted_posts(): void {
		update_post_meta( 999999, Copy_Builder::META_FILE, '/gone/index.html.md' );
		$this->store->write( '/gone/index.html.md', 'x' );

		$this->builder->remove_unlisted();

		$this->assertFalse( $this->store->exists( '/gone/index.html.md' ) );
		$this->assertSame( '', $this->copies->copy_path( 999999 ) );
	}

	public function test_remove_unlisted_with_no_post_types_clears_everything(): void {
		$page = $this->published_pages( 1 )[0];
		update_post_meta( $page, Copy_Builder::META_FILE, '/page/index.html.md' );
		update_option( Option_Settings::OPTION, array( 'post_types' => array() ) );

		$this->builder->remove_unlisted();

		$this->assertSame( '', $this->copies->copy_path( $page ) );
	}

	public function test_all_builds_everything_and_reports_progress(): void {
		$pages    = $this->published_pages( Builder::BATCH_SIZE + 2 );
		$progress = array();

		$built = $this->builder->all(
			static function ( int $after, int $count ) use ( &$progress ): void {
				$progress[] = array( $after, $count );
			}
		);

		$this->assertSame( Builder::BATCH_SIZE + 2, $built );
		$this->assertSame(
			array(
				array( $pages[ Builder::BATCH_SIZE - 1 ], Builder::BATCH_SIZE ),
				array( end( $pages ), Builder::BATCH_SIZE + 2 ),
			),
			$progress
		);
		$this->assertTrue( $this->store->exists( '/llms.txt' ) );
	}

	/**
	 * Start a newer build while the first copy of a batch is being written.
	 */
	private function start_newer_build_mid_batch(): void {
		$scheduler = $this->container()->get( Scheduler::class );
		$started   = false;
		add_filter(
			'llms_helper_front_matter',
			static function ( array $data ) use ( $scheduler, &$started ): array {
				if ( ! $started ) {
					$started = true;
					$scheduler->queue_build();
				}
				return $data;
			}
		);
	}

	public function test_a_batch_queues_no_next_batch_once_a_newer_build_starts(): void {
		$pages = $this->published_pages( Builder::BATCH_SIZE + 1 );
		$this->start_newer_build_mid_batch();

		$this->builder->batch( 0 );

		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( $pages[ Builder::BATCH_SIZE - 1 ] ) ) );
		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ), 'the newer build is queued from the start' );
	}

	public function test_a_last_batch_writes_no_llms_txt_once_a_newer_build_starts(): void {
		$this->published_pages( 2 );
		$this->start_newer_build_mid_batch();

		$this->builder->batch( 0 );

		$this->assertFalse( $this->store->exists( '/llms.txt' ) );
	}

	/**
	 * Make the batch's work die, as a fatal error or timeout would.
	 */
	private function die_mid_batch(): void {
		add_filter(
			'llms_helper_front_matter',
			static function (): array {
				throw new \RuntimeException( 'Died mid batch' );
			}
		);
	}

	public function test_a_batch_has_its_retry_queued_while_it_works_and_dropped_when_done(): void {
		$this->published_pages( 1 );
		$queued_during = false;
		add_filter(
			'llms_helper_front_matter',
			static function ( array $data ) use ( &$queued_during ): array {
				$queued_during = false !== wp_next_scheduled( Scheduler::HOOK_RETRY, array( 0, 2 ) );
				return $data;
			}
		);

		$this->builder->batch( 0 );

		$this->assertTrue( $queued_during );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_RETRY, array( 0, 2 ) ) );
	}

	public function test_a_batch_that_dies_leaves_its_retry_queued(): void {
		$this->published_pages( 1 );
		$this->die_mid_batch();
		$died = false;

		try {
			$this->builder->batch( 0 );
		} catch ( \RuntimeException $e ) {
			$died = true;
		}

		$this->assertTrue( $died );
		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_RETRY, array( 0, 2 ) ) );
	}

	public function test_the_last_allowed_run_records_a_failure_that_a_finished_run_clears(): void {
		$scheduler = $this->container()->get( Scheduler::class );
		$this->published_pages( 1 );
		$this->die_mid_batch();
		$died = false;

		try {
			$this->builder->retry( 0, Scheduler::MAX_ATTEMPTS );
		} catch ( \RuntimeException $e ) {
			$died = true;
		}

		$this->assertTrue( $died );
		$this->assertSame( 0, $scheduler->failed_after() );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_RETRY, array( 0, Scheduler::MAX_ATTEMPTS + 1 ) ) );

		remove_all_filters( 'llms_helper_front_matter' );
		$this->builder->retry( 0, Scheduler::MAX_ATTEMPTS );

		$this->assertNull( $scheduler->failed_after() );
	}

	public function test_all_replaces_a_queued_build(): void {
		$this->container()->get( Scheduler::class )->queue_build();

		$this->builder->all();

		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ) );
	}

	public function test_posts_writes_their_copies_even_when_the_posts_are_unchanged(): void {
		$page = $this->published_pages( 1 )[0];
		$this->builder->all();
		$path = $this->copies->copy_path( $page );
		$this->store->write( $path, 'stale' );

		$this->builder->posts( array( (string) $page ) );

		$this->assertNotSame( 'stale', $this->store->read( $path ) );
	}

	public function test_a_second_build_skips_copies_that_are_up_to_date(): void {
		$pages = $this->published_pages( 3 );
		$this->builder->all();
		update_post_meta( $pages[1], Copy_Builder::META_BUILT, 'older' );

		$this->assertSame( 1, $this->builder->all() );
	}
}
