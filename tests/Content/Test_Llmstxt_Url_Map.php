<?php
/**
 * Tests for Llmstxt_Url_Map.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;

class Test_Llmstxt_Url_Map extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public function copy_path_provider(): array {
		return array(
			'home'           => array( 'http://example.org/', '/index.html.md' ),
			'home no slash'  => array( 'http://example.org', '/index.html.md' ),
			'page'           => array( 'http://example.org/about/', '/about/index.html.md' ),
			'child page'     => array( 'http://example.org/about/team/', '/about/team/index.html.md' ),
			'file permalink' => array( 'http://example.org/page.html', '/page.html.md' ),
			'no slash'       => array( 'http://example.org/about', '/about.md' ),
			'encoded'        => array( 'http://example.org/caf%C3%A9/', '/café/index.html.md' ),
			'query string'   => array( 'http://example.org/?page_id=2', '' ),
		);
	}

	/**
	 * @dataProvider copy_path_provider
	 */
	public function test_copy_path( string $permalink, string $expected ): void {
		$this->assertSame( $expected, ( new Llmstxt_Url_Map() )->copy_path( $permalink ) );
	}

	public function test_copy_url(): void {
		$urls = new Llmstxt_Url_Map();

		$this->assertSame( 'http://example.org/about/index.html.md', $urls->copy_url( 'http://example.org/about/' ) );
		$this->assertSame( 'http://example.org/page.html.md', $urls->copy_url( 'http://example.org/page.html' ) );
		$this->assertSame( '', $urls->copy_url( 'http://example.org/?p=1' ) );
	}

	public function test_is_copy_request(): void {
		$urls = new Llmstxt_Url_Map();

		$this->assertTrue( $urls->is_copy_request( '/about/index.html.md' ) );
		$this->assertTrue( $urls->is_copy_request( '/about.md' ) );
		$this->assertFalse( $urls->is_copy_request( '/about/' ) );
		$this->assertFalse( $urls->is_copy_request( '/llms.txt' ) );
		$this->assertFalse( $urls->is_copy_request( '/readme.md.html' ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function page_uri_provider(): array {
		return array(
			'index copy'    => array( '/about/index.html.md', '/about/' ),
			'home'          => array( '/index.html.md', '/' ),
			'file copy'     => array( '/page.html.md', '/page.html' ),
			'plain md'      => array( '/about.md', '/about' ),
			'index in name' => array( '/notindex.html.md', '/notindex.html' ),
		);
	}

	/**
	 * @dataProvider page_uri_provider
	 */
	public function test_page_uri( string $raw_path, string $expected ): void {
		$this->assertSame( $expected, ( new Llmstxt_Url_Map() )->page_uri( $raw_path ) );
	}

	public function test_index_path_at_the_root(): void {
		$this->assertSame( '/llms.txt', ( new Llmstxt_Url_Map() )->index_path() );
	}

	public function test_index_path_under_a_home_path(): void {
		update_option( 'home', 'http://example.org/blog' );

		$this->assertSame( '/blog/llms.txt', ( new Llmstxt_Url_Map() )->index_path() );
	}

	public function test_url_for_path(): void {
		$this->assertSame( 'http://example.org/caf%C3%A9/index.html.md', ( new Llmstxt_Url_Map() )->url_for_path( '/café/index.html.md' ) );
	}

	public function test_url_for_path_keeps_the_port(): void {
		update_option( 'home', 'https://example.org:8080' );

		$this->assertSame( 'https://example.org:8080/llms.txt', ( new Llmstxt_Url_Map() )->url_for_path( '/llms.txt' ) );
	}
}
