<?php
/**
 * Tests for Loopback_Content_Renderer.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_Post;
use WP_Error;
use WP_UnitTestCase;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;
use PinkCrab\LLMs_Helper\Content\Full_Content_Renderer;
use PinkCrab\LLMs_Helper\Content\Loopback_Content_Renderer;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Loopback_Content_Renderer extends WP_UnitTestCase {

	use Plugin_Helpers;

	/**
	 * Requests made, as url and args.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	private array $requests = array();

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
		$this->requests = array();
	}

	private function post(): WP_Post {
		return self::factory()->post->create_and_get(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Rendered here',
			)
		);
	}

	/**
	 * Answer every HTTP request with this response, recording the request.
	 *
	 * @param array<string, mixed>|WP_Error $response Response.
	 */
	private function respond_with( $response ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, array $args, string $url ) use ( $response ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				return $response;
			},
			10,
			3
		);
	}

	/**
	 * A response as Render_Endpoint gives it.
	 *
	 * @param int                  $code    Status.
	 * @param array<string,string> $headers Headers.
	 * @return array<string, mixed>
	 */
	private function response( int $code = 200, array $headers = array( 'x-llms-helper-render' => '1' ) ): array {
		return array(
			'response' => array( 'code' => $code ),
			'headers'  => new CaseInsensitiveDictionary( $headers ),
			'body'     => '<p>Rendered by the visitor request</p>',
		);
	}

	public function test_uses_the_content_from_the_visitor_request(): void {
		$this->respond_with( $this->response() );

		$html = ( new Loopback_Content_Renderer( new Full_Content_Renderer() ) )->render( $this->post() );

		$this->assertSame( '<p>Rendered by the visitor request</p>', $html );
	}

	public function test_requests_the_posts_page_with_a_one_time_key_for_the_post(): void {
		$post  = $this->post();
		$keys  = array();
		$saved = array();
		add_filter(
			'pre_http_request',
			function ( $pre, array $args, string $url ) use ( $post, &$keys, &$saved ) {
				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
				$keys[]  = $query[ Loopback_Content_Renderer::QUERY_VAR ];
				$saved[] = (int) get_transient( Loopback_Content_Renderer::transient( end( $keys ) ) );
				$this->assertSame( get_permalink( $post ), remove_query_arg( Loopback_Content_Renderer::QUERY_VAR, $url ) );
				return $this->response();
			},
			10,
			3
		);

		$renderer = new Loopback_Content_Renderer( new Full_Content_Renderer() );
		$renderer->render( $post );
		$renderer->render( $post );

		$this->assertSame( array( $post->ID, $post->ID ), $saved );
		$this->assertNotSame( $keys[0], $keys[1] );
		$this->assertSame( $keys[0], sanitize_key( $keys[0] ) );
		$this->assertFalse( get_transient( Loopback_Content_Renderer::transient( $keys[1] ) ) );
	}

	public function test_requests_as_a_visitor_without_following_redirects(): void {
		$this->respond_with( $this->response() );

		( new Loopback_Content_Renderer( new Full_Content_Renderer() ) )->render( $this->post() );

		$this->assertSame( array(), $this->requests[0]['args']['cookies'] );
		$this->assertSame( 0, $this->requests[0]['args']['redirection'] );
	}

	/**
	 * @return array<string, array{array<string, mixed>|WP_Error}>
	 */
	public function failed_response_provider(): array {
		return array(
			'request error'         => array( new WP_Error( 'http_request_failed', 'Could not connect' ) ),
			'redirected to a login' => array( $this->response( 302 ) ),
			'not found'             => array( $this->response( 404 ) ),
			'some other page'       => array( $this->response( 200, array() ) ),
		);
	}

	/**
	 * @dataProvider failed_response_provider
	 * @param array<string, mixed>|WP_Error $response Response.
	 */
	public function test_renders_here_when_the_visitor_request_fails( $response ): void {
		$this->respond_with( $response );

		$html = ( new Loopback_Content_Renderer( new Full_Content_Renderer() ) )->render( $this->post() );

		$this->assertStringContainsString( '<p>Rendered here</p>', $html );
		$this->assertStringNotContainsString( 'visitor request', $html );
	}

	public function test_the_request_can_be_changed_through_the_filter(): void {
		$this->respond_with( $this->response() );
		add_filter(
			'llms_helper_render_request',
			static function ( array $request, WP_Post $post ): array {
				$request['url']                     = 'http://internal.test/?p=' . $post->ID;
				$request['args']['headers']['Host'] = 'example.org';
				return $request;
			},
			10,
			2
		);
		$post = $this->post();

		( new Loopback_Content_Renderer( new Full_Content_Renderer() ) )->render( $post );

		$this->assertSame( 'http://internal.test/?p=' . $post->ID, $this->requests[0]['url'] );
		$this->assertSame( 'example.org', $this->requests[0]['args']['headers']['Host'] );
	}

	public function test_render_here_renders_in_this_request(): void {
		$this->respond_with( $this->response() );

		$html = ( new Loopback_Content_Renderer( new Full_Content_Renderer() ) )->render_here( $this->post() );

		$this->assertStringContainsString( '<p>Rendered here</p>', $html );
		$this->assertSame( array(), $this->requests );
	}
}
