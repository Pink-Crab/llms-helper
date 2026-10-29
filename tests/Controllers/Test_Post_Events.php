<?php
/**
 * Tests for Post_Events.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Controllers\Post_Events;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Post_Events extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Post_Events $events;
	private Copy_Builder $copies;
	private Copy_Store $store;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		delete_option( Option_Settings::OPTION );

		$container    = $this->container();
		$this->events = $container->get( Post_Events::class );
		$this->copies = $container->get( Copy_Builder::class );
		$this->store  = $container->get( Copy_Store::class );
	}

	public function tear_down(): void {
		$this->remove_store();
		parent::tear_down();
	}

	private function page( string $slug ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
			)
		);
	}

	public function test_registers_its_hooks(): void {
		$loader = new Hook_Loader();
		$this->events->register( $loader );

		$hooks = array_map(
			static function ( array $hook ): array {
				return array( $hook['hook'], $hook['callback'], $hook['priority'], $hook['args'] );
			},
			$loader->hooks()
		);

		$this->assertSame(
			array(
				array( 'wp_after_insert_post', array( $this->events, 'after_insert_post' ), 10, 2 ),
				array( 'before_delete_post', array( $this->events, 'before_delete_post' ), 10, 1 ),
				array( 'llms_helper_build_post', array( $this->events, 'build_post' ), 10, 1 ),
			),
			$hooks
		);
	}

	public function test_the_booted_plugin_builds_a_copy_on_the_build_post_action(): void {
		$id = $this->page( 'on-request' );
		// Excluding through meta alone does not save the post, so the copy is still there.
		update_post_meta( $id, '_llms_helper_exclude', 1 );
		$this->assertTrue( $this->store->exists( '/on-request/index.html.md' ) );

		do_action( 'llms_helper_build_post', $id );

		$this->assertFalse( $this->store->exists( '/on-request/index.html.md' ) );
	}

	public function test_the_booted_plugin_builds_a_copy_when_a_page_is_saved(): void {
		$id = $this->page( 'saved' );

		$this->assertSame( '/saved/index.html.md', $this->copies->copy_path( $id ) );
		$this->assertTrue( $this->store->exists( '/saved/index.html.md' ) );
	}

	public function test_the_booted_plugin_removes_the_copy_when_a_page_is_deleted(): void {
		$id = $this->page( 'deleted' );

		wp_delete_post( $id, true );

		$this->assertFalse( $this->store->exists( '/deleted/index.html.md' ) );
	}

	public function test_after_insert_post_builds_tracked_posts(): void {
		$this->unhook_plugin_post_events();
		$id = $this->page( 'tracked' );

		$this->events->after_insert_post( $id, get_post( $id ) );

		$this->assertSame( '/tracked/index.html.md', $this->copies->copy_path( $id ) );
	}

	public function test_after_insert_post_ignores_untracked_post_types(): void {
		$this->unhook_plugin_post_events();
		update_option( Option_Settings::OPTION, array( 'post_types' => array( 'page' ) ) );
		$post = self::factory()->post->create_and_get( array( 'post_status' => 'publish' ) );

		$this->events->after_insert_post( $post->ID, $post );

		$this->assertSame( '', $this->copies->copy_path( $post->ID ) );
	}

	public function test_before_delete_post_removes_the_copy(): void {
		$id = $this->page( 'gone' );

		$this->events->before_delete_post( $id );

		$this->assertSame( '', $this->copies->copy_path( $id ) );
		$this->assertFalse( $this->store->exists( '/gone/index.html.md' ) );
	}
}
