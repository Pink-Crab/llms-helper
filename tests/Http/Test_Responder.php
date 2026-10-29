<?php
/**
 * Tests for Responder.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Http;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Http\Route;
use PinkCrab\LLMs_Helper\Http\Responder;

/**
 * Redirect and send routes set headers and exit, so only the routes that return are tested here;
 * the e2e suite covers the others.
 */
class Test_Responder extends WP_UnitTestCase {

	private ?string $request_uri;

	public function set_up(): void {
		parent::set_up();
		$this->request_uri      = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/about/index.html.md';
	}

	public function tear_down(): void {
		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		parent::tear_down();
	}

	public function test_render_page_rewrites_the_request_uri(): void {
		( new Responder() )->respond( Route::render_page( '/about/?x=1' ) );

		$this->assertSame( '/about/?x=1', $_SERVER['REQUEST_URI'] );
	}

	public function test_none_leaves_the_request_alone(): void {
		( new Responder() )->respond( Route::none() );

		$this->assertSame( '/about/index.html.md', $_SERVER['REQUEST_URI'] );
	}
}
