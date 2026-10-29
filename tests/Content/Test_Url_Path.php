<?php
/**
 * Tests for Url_Path.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use PHPUnit\Framework\TestCase;
use PinkCrab\LLMs_Helper\Content\Url_Path;

class Test_Url_Path extends TestCase {

	public function test_encode_encodes_each_segment(): void {
		$this->assertSame( '/a%20b/c%26d/index.html.md', Url_Path::encode( '/a b/c&d/index.html.md' ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function is_safe_provider(): array {
		return array(
			'normal'       => array( '/about/index.html.md', true ),
			'dots in name' => array( '/a..b/index.html.md', true ),
			'empty'        => array( '', false ),
			'parent'       => array( '/../index.html.md', false ),
			'parent end'   => array( '/about/..', false ),
			'parent start' => array( '../x.md', false ),
			'null byte'    => array( "/about\0/index.html.md", false ),
		);
	}

	/**
	 * @dataProvider is_safe_provider
	 */
	public function test_is_safe( string $path, bool $expected ): void {
		$this->assertSame( $expected, Url_Path::is_safe( $path ) );
	}
}
