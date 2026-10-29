<?php
/**
 * Tests for Term_Events.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Controllers\Term_Events;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Term_Events extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Term_Events $events;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
		$this->events = $this->container()->get( Term_Events::class );
		wp_unschedule_hook( Scheduler::HOOK_POSTS );
	}

	/**
	 * A post in the category, with a copy or not.
	 */
	private function post_in( int $category, bool $with_copy ): int {
		$id = self::factory()->post->create( array( 'post_category' => array( $category ) ) );
		if ( $with_copy ) {
			update_post_meta( $id, Copy_Builder::META_FILE, '/p' . $id . '/index.html.md' );
		}

		return $id;
	}

	public function test_registers_its_hooks(): void {
		$loader = new Hook_Loader();
		$this->events->register( $loader );

		$this->assertSame(
			array(
				array( 'edited_term', array( $this->events, 'term_edited' ), 10, 3 ),
				array( 'delete_term', array( $this->events, 'term_deleted' ), 10, 5 ),
			),
			array_map(
				static function ( array $hook ): array {
					return array( $hook['hook'], $hook['callback'], $hook['priority'], $hook['args'] );
				},
				$loader->hooks()
			)
		);
	}

	public function test_an_edit_queues_the_terms_posts_that_have_copies(): void {
		$category = self::factory()->category->create();
		$copied   = $this->post_in( $category, true );
		$this->post_in( $category, false );

		$this->events->term_edited( $category, 0, 'category' );

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_POSTS, array( array( $copied ) ) ) );
	}

	public function test_a_delete_queues_the_posts_wordpress_gives(): void {
		$category = self::factory()->category->create();
		$copied   = $this->post_in( $category, true );
		$other    = $this->post_in( $category, false );

		$this->events->term_deleted( $category, 0, 'category', null, array( (string) $copied, $other ) );

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_POSTS, array( array( $copied ) ) ) );
	}

	public function test_the_booted_plugin_queues_posts_when_a_term_is_renamed(): void {
		$category = self::factory()->category->create();
		$copied   = $this->post_in( $category, true );

		wp_update_term( $category, 'category', array( 'name' => 'Renamed' ) );

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_POSTS, array( array( $copied ) ) ) );
	}
}
