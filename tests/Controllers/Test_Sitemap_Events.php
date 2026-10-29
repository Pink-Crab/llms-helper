<?php
/**
 * Tests for Sitemap_Events. The providers and cache clearing run against Yoast SEO and Rank Math in
 * e2e/specs/sitemap.spec.js, as the test suite does not load them.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Sitemap\Llms_Txt_Sitemap;
use PinkCrab\LLMs_Helper\Controllers\Sitemap_Events;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fake_Settings;
use PinkCrab\LLMs_Helper\Tests\Fakes\Memory_Store;

class Test_Sitemap_Events extends WP_UnitTestCase {

	/**
	 * Sitemap_Events that counts cache clears.
	 *
	 * @return Sitemap_Events&object{cleared: int}
	 */
	private function events(): Sitemap_Events {
		$urls = new Llmstxt_Url_Map();

		return new class( new Llms_Txt_Sitemap( new Fake_Settings(), new Memory_Store(), $urls ), $urls ) extends Sitemap_Events {

			public int $cleared = 0;

			public function clear_caches(): void {
				++$this->cleared;
			}
		};
	}

	public function test_registers_its_hooks(): void {
		$loader = new Hook_Loader();
		$this->events()->register( $loader );

		$this->assertSame(
			array( 'wpseo_sitemaps_providers', 'rank_math/sitemap/providers', 'llms_helper_purge_urls', 'add_option_' . Option_Settings::OPTION, 'update_option_' . Option_Settings::OPTION ),
			array_column( $loader->hooks(), 'hook' )
		);
	}

	public function test_providers_are_left_as_they_are_without_the_seo_plugins(): void {
		$events = $this->events();

		$this->assertSame( array( 'theirs' ), $events->yoast_providers( array( 'theirs' ) ) );
		$this->assertSame( array( 'theirs' ), $events->rank_math_providers( array( 'theirs' ) ) );
	}

	public function test_purging_llms_txt_clears_the_sitemap_caches(): void {
		$events = $this->events();

		$events->purged( array( 'http://example.org/about/index.html.md' ) );
		$this->assertSame( 0, $events->cleared );

		$events->purged( array( 'http://example.org/llms.txt', 'http://example.org/wp-content/uploads/llms-helper/llms.txt' ) );
		$this->assertSame( 1, $events->cleared );
	}

	public function test_turning_indexing_on_or_off_clears_the_sitemap_caches(): void {
		$events = $this->events();
		$on     = array_merge( Option_Settings::defaults(), array( 'index_llms_txt' => true ) );

		$events->settings_changed( Option_Settings::defaults(), array_merge( Option_Settings::defaults(), array( 'summary' => 'Changed' ) ) );
		$this->assertSame( 0, $events->cleared );

		$events->settings_changed( Option_Settings::defaults(), $on );
		$events->settings_changed( $on, Option_Settings::defaults() );
		$events->settings_added( Option_Settings::OPTION, $on );
		$this->assertSame( 3, $events->cleared );
	}
}
