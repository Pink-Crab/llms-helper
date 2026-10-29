<?php
/**
 * Tests for Product_Front_Matter.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;
use PinkCrab\LLMs_Helper\Controllers\Product_Front_Matter;

class Test_Product_Front_Matter extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
	}

	public function test_registers_on_the_front_matter_filter(): void {
		$controller = new Product_Front_Matter();
		$loader     = new Hook_Loader();
		$controller->register( $loader );

		$hook = $loader->hooks()[0];
		$this->assertSame( array( 'llms_helper_front_matter', array( $controller, 'add' ), 10, 2 ), array( $hook['hook'], $hook['callback'], $hook['priority'], $hook['args'] ) );
	}

	public function test_other_post_types_are_left_alone(): void {
		$post = self::factory()->post->create_and_get();

		$this->assertSame( array( 'title' => 'x' ), ( new Product_Front_Matter() )->add( array( 'title' => 'x' ), $post ) );
	}

	public function test_products_are_left_alone_without_woocommerce(): void {
		register_post_type( 'product', array( 'public' => true ) );
		$product = self::factory()->post->create_and_get( array( 'post_type' => 'product' ) );

		$this->assertFalse( function_exists( 'wc_get_product' ) );
		$this->assertSame( array( 'title' => 'x' ), ( new Product_Front_Matter() )->add( array( 'title' => 'x' ), $product ) );

		unregister_post_type( 'product' );
	}
}
