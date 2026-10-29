<?php
/**
 * Tests for Llms_Txt_Sitemap.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Sitemap;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Sitemap\Llms_Txt_Sitemap;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fake_Settings;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Llms_Txt_Sitemap extends WP_UnitTestCase {

	use Plugin_Helpers;

	private const BUILT = 1700000000;

	private Fake_Settings $settings;

	private Copy_Store $store;

	public function set_up(): void {
		parent::set_up();
		$this->settings = new Fake_Settings( index_llms_txt: true );
		$this->store    = $this->container()->get( Copy_Store::class );
	}

	public function tear_down(): void {
		$this->remove_store();
		parent::tear_down();
	}

	private function sitemap(): Llms_Txt_Sitemap {
		return new Llms_Txt_Sitemap( $this->settings, $this->store, new Llmstxt_Url_Map() );
	}

	private function build_llms_txt(): void {
		$this->store->write( '/llms.txt', '# Site' );
		touch( $this->store->local_file( '/llms.txt' ), self::BUILT );
	}

	public function test_the_entry_is_llms_txt_at_its_url_and_build_time(): void {
		$this->build_llms_txt();

		$this->assertSame(
			array(
				array(
					'loc' => 'http://example.org/llms.txt',
					'mod' => gmdate( DATE_W3C, self::BUILT ),
				),
			),
			$this->sitemap()->links()
		);
	}

	public function test_no_entry_while_search_engines_may_not_index_llms_txt(): void {
		$this->build_llms_txt();
		$this->settings->index_llms_txt = false;

		$this->assertSame( array(), $this->sitemap()->links() );
		$this->assertSame( array(), $this->sitemap()->index_links( 'http://example.org/llms-sitemap.xml' ) );
	}

	public function test_no_entry_before_llms_txt_is_built(): void {
		$this->assertSame( array(), $this->sitemap()->links() );
		$this->assertSame( array(), $this->sitemap()->index_links( 'http://example.org/llms-sitemap.xml' ) );
	}

	public function test_the_index_entry_is_the_sitemap_at_llms_txt_build_time(): void {
		$this->build_llms_txt();

		$this->assertSame(
			array(
				array(
					'loc'     => 'http://example.org/llms-sitemap.xml',
					'lastmod' => gmdate( DATE_W3C, self::BUILT ),
				),
			),
			$this->sitemap()->index_links( 'http://example.org/llms-sitemap.xml' )
		);
	}

	public function test_the_file_name_is_the_one_the_seo_plugins_route_to_the_llms_type(): void {
		$this->assertSame( 'llms-sitemap.xml', Llms_Txt_Sitemap::file_name() );
	}
}
