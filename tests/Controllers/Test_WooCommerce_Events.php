<?php
/**
 * Tests for WooCommerce_Events.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;
use PinkCrab\LLMs_Helper\Controllers\WooCommerce_Events;

class Test_WooCommerce_Events extends WP_UnitTestCase {

	use Plugin_Helpers;

	private WooCommerce_Events $events;
	private Copy_Builder $copies;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		$this->unhook_plugin_post_events();
		delete_option( Option_Settings::OPTION );

		$container    = $this->container();
		$this->events = $container->get( WooCommerce_Events::class );
		$this->copies = $container->get( Copy_Builder::class );
	}

	public function tear_down(): void {
		$this->remove_store();
		parent::tear_down();
	}

	public function test_registers_its_hooks(): void {
		$loader = new Hook_Loader();
		$this->events->register( $loader );

		$this->assertSame(
			array(
				array( 'woocommerce_new_product', array( $this->events, 'product_saved' ) ),
				array( 'woocommerce_update_product', array( $this->events, 'product_saved' ) ),
			),
			array_map(
				static function ( array $hook ): array {
					return array( $hook['hook'], $hook['callback'] );
				},
				$loader->hooks()
			)
		);
	}

	public function test_the_booted_plugin_builds_the_copy_once_woocommerce_has_saved(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'saved-by-woo',
			)
		);
		$this->assertSame( '', $this->copies->copy_path( $id ) );

		do_action( 'woocommerce_update_product', $id );

		$this->assertSame( '/saved-by-woo/index.html.md', $this->copies->copy_path( $id ) );
	}

	public function test_untracked_post_types_are_left_alone(): void {
		update_option( Option_Settings::OPTION, array( 'post_types' => array( 'post' ) ) );
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$this->events->product_saved( $id );

		$this->assertSame( '', $this->copies->copy_path( $id ) );
	}

	public function test_a_missing_post_does_nothing(): void {
		$this->events->product_saved( 999999 );

		$this->assertSame( '', $this->copies->copy_path( 999999 ) );
	}
}
