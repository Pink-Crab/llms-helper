<?php
/**
 * Tests for Seo_Plugin_Noindex.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_Post;
use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Seo_Plugin_Noindex;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Seo_Plugin_Noindex extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
	}

	/**
	 * Check with only these plugins active.
	 *
	 * @param list<string> $active Plugin keys.
	 */
	private function check( array $active ): Seo_Plugin_Noindex {
		return new class( $active ) extends Seo_Plugin_Noindex {
			public function __construct( private array $active ) {}
			protected function active( string $plugin ): bool {
				return in_array( $plugin, $this->active, true );
			}
		};
	}

	private function page(): WP_Post {
		return self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
	}

	public function test_nothing_is_noindex_without_seo_plugins(): void {
		$page = $this->page();
		update_post_meta( $page->ID, '_yoast_wpseo_meta-robots-noindex', '1' );
		update_post_meta( $page->ID, 'slim_seo', array( 'noindex' => 1 ) );

		$this->assertFalse( ( new Seo_Plugin_Noindex() )->noindex( $page ) );
		$this->assertFalse( $this->check( array() )->noindex( $page ) );
	}

	/**
	 * @return array<string, array{string, array<string, bool>, bool}>
	 */
	public function yoast_provider(): array {
		return array(
			'post noindex'               => array( '1', array(), true ),
			'post index beats post type' => array( '2', array( 'noindex-page' => true ), false ),
			'default follows post type'  => array( '0', array( 'noindex-page' => true ), true ),
			'post type disabled'         => array( '', array( 'disable-page' => true ), true ),
			'nothing set'                => array( '', array(), false ),
		);
	}

	/**
	 * @dataProvider yoast_provider
	 */
	public function test_yoast( string $setting, array $titles, bool $expected ): void {
		$page = $this->page();
		update_post_meta( $page->ID, '_yoast_wpseo_meta-robots-noindex', $setting );
		update_option( 'wpseo_titles', $titles );

		$this->assertSame( $expected, $this->check( array( 'yoast' ) )->noindex( $page ) );
	}

	/**
	 * @return array<string, array{mixed, mixed, bool}>
	 */
	public function slim_seo_provider(): array {
		return array(
			'post noindex'       => array( array( 'noindex' => 1 ), array(), true ),
			'post type noindex'  => array( '', array( 'page' => array( 'noindex' => 1 ) ), true ),
			'other post type'    => array( '', array( 'post' => array( 'noindex' => 1 ) ), false ),
			'nothing set'        => array( array( 'noindex' => 0 ), array(), false ),
			'robots feature on'  => array( array( 'noindex' => 1 ), array( 'features' => array( 'meta_title', 'meta_robots' ) ), true ),
			'robots feature off' => array( array( 'noindex' => 1 ), array( 'features' => array( 'meta_title' ) ), false ),
		);
	}

	/**
	 * @dataProvider slim_seo_provider
	 */
	public function test_slim_seo( $meta, $options, bool $expected ): void {
		$page = $this->page();
		update_post_meta( $page->ID, 'slim_seo', $meta );
		update_option( 'slim_seo', $options );

		$this->assertSame( $expected, $this->check( array( 'slim_seo' ) )->noindex( $page ) );
	}

	public function test_any_active_plugin_marking_noindex_is_enough(): void {
		$page = $this->page();
		update_post_meta( $page->ID, 'slim_seo', array( 'noindex' => 1 ) );

		$this->assertTrue( $this->check( array( 'yoast', 'slim_seo' ) )->noindex( $page ) );
		$this->assertFalse( $this->check( array( 'yoast' ) )->noindex( $page ) );
	}
}
