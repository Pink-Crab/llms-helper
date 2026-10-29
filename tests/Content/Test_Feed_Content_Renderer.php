<?php
/**
 * Tests for Feed_Content_Renderer.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Feed_Content_Renderer;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Feed_Content_Renderer extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
	}

	public function test_the_content_feed_runs_with_the_default_feed_type(): void {
		$post = self::factory()->post->create_and_get( array( 'post_content' => 'Body' ) );
		$type = null;
		add_filter(
			'the_content_feed',
			static function ( string $content, string $feed_type ) use ( &$type ): string {
				$type = $feed_type;
				return $content . ' FEED';
			},
			10,
			2
		);

		$html = ( new Feed_Content_Renderer() )->render( $post );

		$this->assertStringContainsString( 'Body', $html );
		$this->assertStringEndsWith( ' FEED', $html );
		$this->assertSame( get_default_feed(), $type );
	}

	public function test_content_renders_as_a_feed_and_the_query_is_restored(): void {
		$post    = self::factory()->post->create_and_get( array( 'post_content' => 'Body' ) );
		$in_feed = null;
		add_filter(
			'the_content',
			static function ( string $content ) use ( &$in_feed ): string {
				$in_feed = is_feed();
				return $content;
			}
		);

		( new Feed_Content_Renderer() )->render( $post );

		$this->assertTrue( $in_feed );
		$this->assertFalse( is_feed() );
	}

	public function test_the_whole_content_is_kept_past_a_more_tag(): void {
		$post = self::factory()->post->create_and_get( array( 'post_content' => "Intro\n<!--more-->\nRest" ) );

		$html = ( new Feed_Content_Renderer() )->render( $post );

		$this->assertStringContainsString( 'Intro', $html );
		$this->assertStringContainsString( 'Rest', $html );
	}
}
