<?php
/**
 * Tests for Render_Endpoint.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Controllers\Render_Endpoint;
use PinkCrab\LLMs_Helper\Content\Full_Content_Renderer;
use PinkCrab\LLMs_Helper\Content\Loopback_Content_Renderer;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Render_Endpoint extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Render_Endpoint $endpoint;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
		$this->set_permalink_structure( '/%postname%/' );
		$this->endpoint = new Render_Endpoint( new Loopback_Content_Renderer( new Full_Content_Renderer() ) );
	}

	private function post_id( string $content = 'Content' ): int {
		return self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
	}

	private function key_for( int $post_id ): string {
		$key = 'testkey' . $post_id;
		set_transient( Loopback_Content_Renderer::transient( $key ), $post_id, Loopback_Content_Renderer::KEY_TTL );
		return $key;
	}

	private function visit( string $url, string $key ): void {
		$this->go_to( add_query_arg( Loopback_Content_Renderer::QUERY_VAR, $key, $url ) );
	}

	public function test_registers_on_template_redirect_first(): void {
		$loader = new Hook_Loader();
		$this->endpoint->register( $loader );

		$this->assertSame( 'template_redirect', $loader->hooks()[0]['hook'] );
		$this->assertSame( 0, $loader->hooks()[0]['priority'] );
	}

	public function test_a_page_without_a_key_is_left_alone(): void {
		$this->go_to( (string) get_permalink( $this->post_id() ) );

		$this->assertNull( $this->endpoint->requested_post() );
	}

	public function test_an_unknown_key_is_left_alone(): void {
		$id = $this->post_id();
		$this->visit( (string) get_permalink( $id ), 'notissued' );

		$this->assertNull( $this->endpoint->requested_post() );
	}

	public function test_a_key_on_its_posts_page_gives_the_post_once(): void {
		$id  = $this->post_id();
		$key = $this->key_for( $id );
		$this->visit( (string) get_permalink( $id ), $key );

		$this->assertSame( $id, $this->endpoint->requested_post()->ID );
		$this->assertNull( $this->endpoint->requested_post() );
	}

	public function test_a_key_on_another_posts_page_is_refused_and_used_up(): void {
		$id    = $this->post_id();
		$other = $this->post_id();
		$key   = $this->key_for( $id );
		$this->visit( (string) get_permalink( $other ), $key );

		$this->assertNull( $this->endpoint->requested_post() );
		$this->assertFalse( get_transient( Loopback_Content_Renderer::transient( $key ) ) );
	}

	public function test_a_key_on_a_listing_page_is_refused(): void {
		$id = $this->post_id();
		$this->visit( home_url( '/' ), $this->key_for( $id ) );

		$this->assertNull( $this->endpoint->requested_post() );
	}

	public function test_renders_inside_the_main_loop(): void {
		add_shortcode(
			'llms_helper_loop',
			static function (): string {
				return in_the_loop() ? 'in-the-loop-' . get_the_ID() : 'outside-the-loop';
			}
		);
		$id = $this->post_id( '[llms_helper_loop]' );
		$this->visit( (string) get_permalink( $id ), $this->key_for( $id ) );

		$html = $this->endpoint->render( $this->endpoint->requested_post() );

		$this->assertStringContainsString( 'in-the-loop-' . $id, $html );
	}
}
