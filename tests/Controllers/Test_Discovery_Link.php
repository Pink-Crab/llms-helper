<?php
/**
 * Tests for Discovery_Link.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Controllers\Discovery_Link;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Discovery_Link extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Discovery_Link $link;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		delete_option( Option_Settings::OPTION );
		$this->link = $this->container()->get( Discovery_Link::class );
	}

	public function tear_down(): void {
		$this->remove_store();
		parent::tear_down();
	}

	private function output(): string {
		ob_start();
		$this->link->output();
		return (string) ob_get_clean();
	}

	public function test_registers_on_wp_head_early(): void {
		$loader = new Hook_Loader();
		$this->link->register( $loader );

		$this->assertSame( 'wp_head', $loader->hooks()[0]['hook'] );
		$this->assertSame( 1, $loader->hooks()[0]['priority'] );
	}

	public function test_page_with_a_copy_prints_the_alternate_link(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'linked',
			)
		);
		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			'<link rel="alternate" type="text/markdown" href="http://example.org/linked/index.html.md" />' . "\n",
			$this->output()
		);
	}

	public function test_page_without_a_copy_prints_nothing(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		delete_post_meta( $id, Copy_Builder::META_FILE );
		$this->go_to( get_permalink( $id ) );

		$this->assertSame( '', $this->output() );
	}

	public function test_archives_print_nothing(): void {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->output() );
	}
}
