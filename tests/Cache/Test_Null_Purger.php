<?php
/**
 * Tests for Null_Purger.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Cache;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cache\Null_Purger;

class Test_Null_Purger extends WP_UnitTestCase {

	public function test_purge_fires_the_purge_action_with_the_urls(): void {
		$received = null;
		add_action(
			'llms_helper_purge_urls',
			static function ( array $urls ) use ( &$received ): void {
				$received = $urls;
			}
		);

		( new Null_Purger() )->purge( array( 'http://example.org/a/index.html.md', 'http://example.org/llms.txt' ) );

		$this->assertSame( array( 'http://example.org/a/index.html.md', 'http://example.org/llms.txt' ), $received );
	}
}
