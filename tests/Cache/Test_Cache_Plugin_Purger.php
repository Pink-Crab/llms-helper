<?php
/**
 * Tests for Cache_Plugin_Purger. The cache plugins' own purges are covered by e2e/specs/cache.spec.js.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Cache;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cache\Cache_Plugin_Purger;

class Test_Cache_Plugin_Purger extends WP_UnitTestCase {

	private const URLS = array( 'http://example.org/a/index.html.md', 'http://example.org/llms.txt' );

	public function test_purge_fires_the_purge_action_with_the_urls(): void {
		$received = null;
		add_action(
			'llms_helper_purge_urls',
			static function ( array $urls ) use ( &$received ): void {
				$received = $urls;
			}
		);

		( new Cache_Plugin_Purger() )->purge( self::URLS );

		$this->assertSame( self::URLS, $received );
	}

	public function test_purge_gives_litespeed_cache_each_url(): void {
		$received = array();
		add_action(
			'litespeed_purge_url',
			static function ( string $url ) use ( &$received ): void {
				$received[] = $url;
			}
		);

		( new Cache_Plugin_Purger() )->purge( self::URLS );

		$this->assertSame( self::URLS, $received );
	}
}
