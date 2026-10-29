<?php
/**
 * Tests for Copy_Builder.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Policy;
use PinkCrab\LLMs_Helper\Tests\Fakes\Memory_Store;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fake_Settings;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Formatter;
use PinkCrab\LLMs_Helper\Tests\Fakes\Recording_Queue;
use PinkCrab\LLMs_Helper\Tests\Fakes\Recording_Purger;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Description;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Copy_Builder extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Memory_Store $store;
	private Recording_Purger $purger;
	private Recording_Queue $queue;
	private Fixed_Policy $policy;
	private Fake_Settings $settings;
	private Fixed_Description $descriptions;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		$this->unhook_plugin_post_events();

		$this->store        = new Memory_Store();
		$this->purger       = new Recording_Purger();
		$this->queue        = new Recording_Queue();
		$this->policy       = new Fixed_Policy( true );
		$this->settings     = new Fake_Settings();
		$this->descriptions = new Fixed_Description();
	}

	public function tear_down(): void {
		// Saving posts also runs the plugin's own hooks, which write to the real store.
		$this->remove_store();
		parent::tear_down();
	}

	private function builder(): Copy_Builder {
		return new Copy_Builder(
			new Fixed_Formatter(),
			$this->store,
			new Llmstxt_Url_Map(),
			$this->policy,
			$this->settings,
			new Scheduler( $this->queue ),
			$this->purger,
			$this->descriptions
		);
	}

	private function page( string $slug = 'about' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
			)
		);
	}

	public function test_build_writes_the_formatted_copy_at_its_path(): void {
		$id = $this->page();

		$this->assertTrue( $this->builder()->build( $id ) );
		$this->assertSame( array( '/about/index.html.md' => 'copy of ' . $id ), $this->store->files );
		$this->assertSame( '/about/index.html.md', $this->builder()->copy_path( $id ) );
	}

	public function test_build_queues_an_llms_txt_build(): void {
		$this->builder()->build( $this->page() );

		$this->assertTrue( $this->queue->is_scheduled( Scheduler::HOOK_INDEX ) );
	}

	public function test_build_purges_the_copy_url_and_the_store_url(): void {
		$this->builder()->build( $this->page() );

		$this->assertSame(
			array( 'http://example.org/about/index.html.md', 'https://cdn.test/llms/about/index.html.md' ),
			$this->purger->urls
		);
	}

	public function test_store_without_a_public_url_only_purges_the_copy_url(): void {
		$this->store = new Memory_Store( '' );

		$this->builder()->build( $this->page() );

		$this->assertSame( array( 'http://example.org/about/index.html.md' ), $this->purger->urls );
	}

	public function test_excluded_post_has_its_copy_removed(): void {
		$id = $this->page();
		$this->builder()->build( $id );
		$this->purger->urls     = array();
		$this->queue->jobs      = array();
		$this->policy->includes = false;

		$this->assertFalse( $this->builder()->build( $id ) );
		$this->assertSame( array(), $this->store->files );
		$this->assertSame( '', $this->builder()->copy_path( $id ) );
		$this->assertContains( 'http://example.org/about/index.html.md', $this->purger->urls );
		$this->assertTrue( $this->queue->is_scheduled( Scheduler::HOOK_INDEX ) );
	}

	public function test_plain_permalinks_give_no_copy(): void {
		$id = $this->page();
		$this->set_permalink_structure( '' );

		$this->assertFalse( $this->builder()->build( $id ) );
		$this->assertSame( array(), $this->store->files );
	}

	public function test_a_failed_write_records_nothing(): void {
		$this->store = new Memory_Store( 'https://cdn.test/llms', '', false );
		$id          = $this->page();

		$this->assertFalse( $this->builder()->build( $id ) );
		$this->assertSame( '', $this->builder()->copy_path( $id ) );
		$this->assertSame( array(), $this->purger->urls );
		$this->assertSame( array(), $this->queue->jobs );
	}

	public function test_a_moved_permalink_removes_the_old_copy(): void {
		$id = $this->page();
		$this->builder()->build( $id );
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => 'about-us',
			)
		);
		$this->purger->urls = array();

		$this->builder()->build( $id );

		$this->assertSame( array( '/about-us/index.html.md' ), array_keys( $this->store->files ) );
		$this->assertSame( '/about-us/index.html.md', $this->builder()->copy_path( $id ) );
		$this->assertContains( 'http://example.org/about/index.html.md', $this->purger->urls );
		$this->assertContains( 'http://example.org/about-us/index.html.md', $this->purger->urls );
	}

	public function test_build_records_what_the_copy_was_built_from(): void {
		$id = $this->page();

		$this->builder()->build( $id );

		$this->assertSame( get_post( $id )->post_modified_gmt . '|' . PINKCRAB_LLMS_HELPER_VERSION, get_post_meta( $id, Copy_Builder::META_BUILT, true ) );
	}

	public function test_only_changed_skips_a_copy_that_is_up_to_date(): void {
		$id = $this->page();
		$this->builder()->build( $id );
		$this->store->files['/about/index.html.md'] = 'unchanged';
		$this->purger->urls                         = array();

		$this->assertFalse( $this->builder()->build( $id, true ) );
		$this->assertSame( 'unchanged', $this->store->files['/about/index.html.md'] );
		$this->assertSame( array(), $this->purger->urls );
	}

	public function test_without_only_changed_an_up_to_date_copy_is_written(): void {
		$id = $this->page();
		$this->builder()->build( $id );
		$this->store->files['/about/index.html.md'] = 'unchanged';

		$this->assertTrue( $this->builder()->build( $id ) );
		$this->assertSame( 'copy of ' . $id, $this->store->files['/about/index.html.md'] );
	}

	public function test_only_changed_writes_when_the_post_changed(): void {
		$id = $this->page();
		$this->builder()->build( $id );
		update_post_meta( $id, Copy_Builder::META_BUILT, 'older' );

		$this->assertTrue( $this->builder()->build( $id, true ) );
	}

	public function test_only_changed_writes_when_the_file_is_missing(): void {
		$id = $this->page();
		$this->builder()->build( $id );
		$this->store->files = array();

		$this->assertTrue( $this->builder()->build( $id, true ) );
		$this->assertTrue( $this->store->exists( '/about/index.html.md' ) );
	}

	public function test_only_changed_writes_when_the_permalink_moved(): void {
		$id = $this->page();
		$this->builder()->build( $id );
		update_post_meta( $id, Copy_Builder::META_FILE, '/old/index.html.md' );

		$this->assertTrue( $this->builder()->build( $id, true ) );
		$this->assertSame( '/about/index.html.md', $this->builder()->copy_path( $id ) );
	}

	public function test_build_keeps_the_meta_description_for_llms_txt(): void {
		$id                       = $this->page();
		$this->descriptions->post = 'From the SEO plugin.';

		$this->builder()->build( $id );

		$this->assertSame( 'From the SEO plugin.', get_post_meta( $id, Copy_Builder::META_DESCRIPTION, true ) );

		$this->descriptions->post = '';
		$this->builder()->build( $id );

		$this->assertSame( '', get_post_meta( $id, Copy_Builder::META_DESCRIPTION, true ) );
	}

	public function test_remove_clears_the_built_meta(): void {
		$id = $this->page();
		$this->builder()->build( $id );

		$this->builder()->remove( $id );

		$this->assertSame( '', get_post_meta( $id, Copy_Builder::META_BUILT, true ) );
	}

	public function test_remove_without_a_copy_does_nothing(): void {
		$id = $this->page();
		delete_post_meta( $id, Copy_Builder::META_FILE );

		$this->builder()->remove( $id );

		$this->assertSame( array(), $this->purger->urls );
		$this->assertSame( array(), $this->queue->jobs );
	}

	public function test_missing_post_gets_no_copy(): void {
		$this->assertFalse( $this->builder()->build( 999999 ) );
		$this->assertSame( array(), $this->store->files );
	}

	public function test_tracks(): void {
		$this->settings->post_types = array( 'page' );
		$post                       = self::factory()->post->create_and_get();

		$this->assertTrue( $this->builder()->tracks( get_post( $this->page() ) ) );
		$this->assertFalse( $this->builder()->tracks( $post ) );

		update_post_meta( $post->ID, Copy_Builder::META_FILE, '/old/index.html.md' );

		$this->assertTrue( $this->builder()->tracks( $post ) );
	}
}
