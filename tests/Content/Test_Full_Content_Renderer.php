<?php
/**
 * Tests for Full_Content_Renderer.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Full_Content_Renderer;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Full_Content_Renderer extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
	}

	private function render( string $content ): string {
		$post = self::factory()->post->create_and_get(
			array(
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);

		return ( new Full_Content_Renderer() )->render( $post );
	}

	public function test_content_filters_are_applied(): void {
		$this->assertStringContainsString( '<p>Plain text</p>', $this->render( 'Plain text' ) );
	}

	public function test_content_past_the_more_tag_is_kept(): void {
		$html = $this->render( "Intro\n<!--more-->\nRest of the post" );

		$this->assertStringContainsString( 'Intro', $html );
		$this->assertStringContainsString( 'Rest of the post', $html );
		$this->assertStringNotContainsString( 'more-link', $html );
	}

	public function test_every_page_is_kept(): void {
		$html = $this->render( 'Page one<!--nextpage-->Page two<!--nextpage-->Page three' );

		$this->assertStringContainsString( 'Page one', $html );
		$this->assertStringContainsString( 'Page two', $html );
		$this->assertStringContainsString( 'Page three', $html );
	}

	public function test_renders_as_a_logged_out_visitor_and_restores_the_user(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		add_shortcode(
			'llms_helper_who',
			static function (): string {
				return is_user_logged_in() ? 'member' : 'visitor';
			}
		);

		$html = $this->render( '[llms_helper_who]' );

		$this->assertStringContainsString( 'visitor', $html );
		$this->assertSame( $admin, get_current_user_id() );
	}

	public function test_restores_the_global_post(): void {
		$other           = self::factory()->post->create_and_get();
		$GLOBALS['post'] = $other;

		$this->render( 'Anything' );

		$this->assertSame( $other, $GLOBALS['post'] );
	}
}
