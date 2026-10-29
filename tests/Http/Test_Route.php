<?php
/**
 * Tests for Route.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Http;

use PHPUnit\Framework\TestCase;
use PinkCrab\LLMs_Helper\Http\Route;

class Test_Route extends TestCase {

	public function test_none(): void {
		$route = Route::none();

		$this->assertSame( Route::NONE, $route->kind() );
		$this->assertSame( '', $route->target() );
		$this->assertSame( '', $route->content_type() );
		$this->assertTrue( $route->with_body() );
		$this->assertSame( 200, $route->status_code() );
	}

	public function test_redirect(): void {
		$route = Route::redirect( 'https://example.org/wp-content/uploads/llms-helper/llms.txt' );

		$this->assertSame( Route::REDIRECT, $route->kind() );
		$this->assertSame( 'https://example.org/wp-content/uploads/llms-helper/llms.txt', $route->target() );
	}

	public function test_send_file(): void {
		$route = Route::send_file( '/tmp/file.md', 'text/markdown', false );

		$this->assertSame( Route::SEND_FILE, $route->kind() );
		$this->assertSame( '/tmp/file.md', $route->target() );
		$this->assertSame( 'text/markdown', $route->content_type() );
		$this->assertFalse( $route->with_body() );
	}

	public function test_send_contents(): void {
		$route = Route::send_contents( '# Copy', 'text/plain', true );

		$this->assertSame( Route::SEND_CONTENTS, $route->kind() );
		$this->assertSame( '# Copy', $route->target() );
		$this->assertSame( 'text/plain', $route->content_type() );
		$this->assertTrue( $route->with_body() );
	}

	public function test_render_page(): void {
		$route = Route::render_page( '/about/?x=1' );

		$this->assertSame( Route::RENDER_PAGE, $route->kind() );
		$this->assertSame( '/about/?x=1', $route->target() );
	}

	public function test_with_noindex_returns_a_flagged_copy(): void {
		$route   = Route::send_contents( 'x', 'text/plain', true );
		$flagged = $route->with_noindex();

		$this->assertFalse( $route->noindex() );
		$this->assertTrue( $flagged->noindex() );
		$this->assertSame( array( Route::SEND_CONTENTS, 'x', 'text/plain', true ), array( $flagged->kind(), $flagged->target(), $flagged->content_type(), $flagged->with_body() ) );
	}

	public function test_status(): void {
		$route = Route::status( 410 );

		$this->assertSame( Route::STATUS, $route->kind() );
		$this->assertSame( 410, $route->status_code() );
		$this->assertSame( 'text/plain', $route->content_type() );
	}
}
