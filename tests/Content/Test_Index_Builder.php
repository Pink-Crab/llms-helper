<?php
/**
 * Tests for Index_Builder.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Index_Builder;
use PinkCrab\LLMs_Helper\Tests\Fakes\Memory_Store;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fake_Settings;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Generator;
use PinkCrab\LLMs_Helper\Tests\Fakes\Recording_Purger;

class Test_Index_Builder extends WP_UnitTestCase {

	private Memory_Store $store;
	private Recording_Purger $purger;
	private Fake_Settings $settings;
	private Index_Builder $index;

	public function set_up(): void {
		parent::set_up();
		$this->store    = new Memory_Store();
		$this->purger   = new Recording_Purger();
		$this->settings = new Fake_Settings();
		$this->index    = new Index_Builder( new Fixed_Generator( "# Generated\n" ), $this->store, new Llmstxt_Url_Map(), $this->settings, $this->purger );
	}

	public function tear_down(): void {
		$this->index->remove_root_file();
		parent::tear_down();
	}

	public function test_build_writes_the_generated_contents_to_the_store(): void {
		$this->index->build();

		$this->assertSame( array( '/llms.txt' => "# Generated\n" ), $this->store->files );
	}

	public function test_build_purges_the_llms_txt_urls(): void {
		$this->index->build();

		$this->assertSame( array( 'http://example.org/llms.txt', 'https://cdn.test/llms/llms.txt' ), $this->purger->urls );
	}

	public function test_build_follows_the_home_path(): void {
		update_option( 'home', 'http://example.org/blog' );

		$this->index->build();

		$this->assertSame( array( '/blog/llms.txt' ), array_keys( $this->store->files ) );
	}

	public function test_build_with_redirect_does_not_write_the_root_file(): void {
		$this->index->build();

		$this->assertFileDoesNotExist( $this->index->root_file() );
	}

	public function test_build_without_redirect_writes_the_root_file(): void {
		$this->settings->redirect = false;

		$this->index->build();

		$this->assertSame( "# Generated\n", file_get_contents( $this->index->root_file() ) );
		$this->assertSame( $this->index->root_file(), get_option( Index_Builder::OPTION_ROOT_FILE ) );
	}

	public function test_build_with_redirect_removes_the_root_file_it_wrote(): void {
		$this->settings->redirect = false;
		$this->index->build();
		$this->settings->redirect = true;

		$this->index->build();

		$this->assertFileDoesNotExist( $this->index->root_file() );
		$this->assertFalse( get_option( Index_Builder::OPTION_ROOT_FILE ) );
	}

	public function test_another_root_llms_txt_is_left_alone(): void {
		$file = $this->index->root_file();
		file_put_contents( $file, 'someone else' );

		try {
			$this->assertFalse( $this->index->write_root_file( 'ours' ) );
			$this->index->remove_root_file();

			$this->assertSame( 'someone else', file_get_contents( $file ) );
		} finally {
			unlink( $file );
		}
	}

	public function test_root_file_can_be_rewritten_once_owned(): void {
		$this->assertTrue( $this->index->write_root_file( 'first' ) );
		$this->assertTrue( $this->index->write_root_file( 'second' ) );

		$this->assertSame( 'second', file_get_contents( $this->index->root_file() ) );
	}
}
