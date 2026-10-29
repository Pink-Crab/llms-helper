<?php
/**
 * Tests for Request_Router.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Http;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Http\Route;
use PinkCrab\LLMs_Helper\Http\Request_Router;
use PinkCrab\LLMs_Helper\Tests\Fakes\Memory_Store;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fake_Settings;

class Test_Request_Router extends WP_UnitTestCase {

	private Memory_Store $store;
	private Fake_Settings $settings;

	public function set_up(): void {
		parent::set_up();
		$this->store    = new Memory_Store( 'https://cdn.test/llms', '/srv/llms' );
		$this->settings = new Fake_Settings();
	}

	private function route( string $uri, string $method = 'GET' ): Route {
		return $this->router()->route(
			array(
				'REQUEST_URI'    => $uri,
				'REQUEST_METHOD' => $method,
			)
		);
	}

	private function router(): Request_Router {
		return new Request_Router( $this->store, new Llmstxt_Url_Map(), $this->settings );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function ignored_request_provider(): array {
		return array(
			'no uri'       => array( array( 'REQUEST_METHOD' => 'GET' ) ),
			'empty uri'    => array( array( 'REQUEST_URI' => '' ) ),
			'non string'   => array( array( 'REQUEST_URI' => array( '/x.md' ) ) ),
			'post request' => array(
				array(
					'REQUEST_URI'    => '/about/index.html.md',
					'REQUEST_METHOD' => 'POST',
				),
			),
			'other path'   => array( array( 'REQUEST_URI' => '/about/' ) ),
			'md in query'  => array( array( 'REQUEST_URI' => '/about/?file=x.md' ) ),
			'nested llms'  => array( array( 'REQUEST_URI' => '/docs/llms.txt' ) ),
		);
	}

	/**
	 * @dataProvider ignored_request_provider
	 */
	public function test_other_requests_are_left_to_wordpress( array $server ): void {
		$this->store->write( '/docs/llms.txt', 'x' );
		$this->store->write( '/about/index.html.md', 'x' );

		$this->assertSame( Route::NONE, $this->router()->route( $server )->kind() );
	}

	public function test_method_defaults_to_get_and_is_case_insensitive(): void {
		$this->store->write( '/about/index.html.md', 'x' );

		$this->assertSame( Route::REDIRECT, $this->router()->route( array( 'REQUEST_URI' => '/about/index.html.md' ) )->kind() );
		$this->assertSame( Route::REDIRECT, $this->route( '/about/index.html.md', 'head' )->kind() );
	}

	public function test_saved_copy_redirects_to_the_store_url(): void {
		$this->store->write( '/about/index.html.md', 'x' );

		$route = $this->route( '/about/index.html.md' );

		$this->assertSame( Route::REDIRECT, $route->kind() );
		$this->assertSame( 'https://cdn.test/llms/about/index.html.md', $route->target() );
	}

	public function test_saved_llms_txt_redirects_to_the_store_url(): void {
		$this->store->write( '/llms.txt', 'x' );

		$route = $this->route( '/llms.txt?v=1' );

		$this->assertSame( Route::REDIRECT, $route->kind() );
		$this->assertSame( 'https://cdn.test/llms/llms.txt', $route->target() );
	}

	public function test_without_redirect_a_local_copy_is_sent_as_markdown(): void {
		$this->settings->redirect = false;
		$this->store->write( '/about/index.html.md', 'x' );

		$route = $this->route( '/about/index.html.md' );

		$this->assertSame( Route::SEND_FILE, $route->kind() );
		$this->assertSame( '/srv/llms/about/index.html.md', $route->target() );
		$this->assertSame( 'text/markdown', $route->content_type() );
		$this->assertTrue( $route->with_body() );
	}

	public function test_without_redirect_llms_txt_is_sent_as_plain_text(): void {
		$this->settings->redirect = false;
		$this->store->write( '/llms.txt', 'x' );

		$this->assertSame( 'text/plain', $this->route( '/llms.txt' )->content_type() );
	}

	public function test_llms_txt_is_sent_noindex_and_copies_are_not(): void {
		$this->settings->redirect = false;
		$this->store->write( '/llms.txt', 'x' );
		$this->store->write( '/about/index.html.md', 'x' );

		$this->assertTrue( $this->route( '/llms.txt' )->noindex() );
		$this->assertFalse( $this->route( '/about/index.html.md' )->noindex() );
	}

	public function test_llms_txt_is_not_sent_noindex_when_search_engines_may_index_it(): void {
		$this->settings->redirect       = false;
		$this->settings->index_llms_txt = true;
		$this->store->write( '/llms.txt', 'x' );

		$this->assertFalse( $this->route( '/llms.txt' )->noindex() );
	}

	public function test_store_without_a_public_url_is_sent_even_in_redirect_mode(): void {
		$this->store = new Memory_Store( '', '/srv/llms' );
		$this->store->write( '/about/index.html.md', 'x' );

		$this->assertSame( Route::SEND_FILE, $this->route( '/about/index.html.md' )->kind() );
	}

	public function test_store_without_a_local_file_sends_its_contents(): void {
		$this->settings->redirect = false;
		$this->store              = new Memory_Store( 'https://cdn.test/llms', '' );
		$this->store->write( '/about/index.html.md', '# About' );

		$route = $this->route( '/about/index.html.md' );

		$this->assertSame( Route::SEND_CONTENTS, $route->kind() );
		$this->assertSame( '# About', $route->target() );
		$this->assertSame( 'text/markdown', $route->content_type() );
	}

	public function test_head_request_sends_no_body(): void {
		$this->settings->redirect = false;
		$this->store->write( '/about/index.html.md', 'x' );

		$this->assertFalse( $this->route( '/about/index.html.md', 'HEAD' )->with_body() );
	}

	public function test_encoded_paths_find_the_decoded_file(): void {
		$this->store->write( '/café/index.html.md', 'x' );

		$this->assertSame( Route::REDIRECT, $this->route( '/caf%C3%A9/index.html.md' )->kind() );
	}

	public function test_missing_copy_renders_the_page_by_default(): void {
		$route = $this->route( '/about/index.html.md?utm=x' );

		$this->assertSame( Route::RENDER_PAGE, $route->kind() );
		$this->assertSame( '/about/?utm=x', $route->target() );
	}

	public function test_missing_plain_md_copy_renders_the_page(): void {
		$this->assertSame( '/about', $this->route( '/about.md' )->target() );
	}

	public function test_missing_copy_can_return_404(): void {
		$this->settings->missing_copy = '404';

		$route = $this->route( '/about/index.html.md' );

		$this->assertSame( Route::STATUS, $route->kind() );
		$this->assertSame( 404, $route->status_code() );
	}

	public function test_missing_copy_can_return_410(): void {
		$this->settings->missing_copy = '410';

		$this->assertSame( 410, $this->route( '/about/index.html.md' )->status_code() );
	}

	public function test_missing_llms_txt_is_left_to_wordpress(): void {
		$this->settings->missing_copy = '404';

		$this->assertSame( Route::NONE, $this->route( '/llms.txt' )->kind() );
	}

	public function test_llms_txt_follows_the_home_path(): void {
		update_option( 'home', 'http://example.org/blog' );
		$this->store->write( '/blog/llms.txt', 'x' );
		$this->store->write( '/llms.txt', 'x' );

		$this->assertSame( Route::REDIRECT, $this->route( '/blog/llms.txt' )->kind() );
		$this->assertSame( Route::NONE, $this->route( '/llms.txt' )->kind() );
	}
}
