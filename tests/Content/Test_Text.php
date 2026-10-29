<?php
/**
 * Tests for Text.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use PHPUnit\Framework\TestCase;
use PinkCrab\LLMs_Helper\Content\Text;

class Test_Text extends TestCase {

	public function test_plain_strips_tags_and_decodes_entities(): void {
		$this->assertSame( 'Fish & Chips "today"', Text::plain( '<strong>Fish &amp; Chips</strong> &quot;today&quot;' ) );
	}

	public function test_plain_collapses_whitespace_to_one_line(): void {
		$this->assertSame( 'one two three', Text::plain( "  one\n\n two\t three  " ) );
	}

	public function test_plain_removes_script_contents(): void {
		$this->assertSame( 'Hello', Text::plain( 'Hello<script>alert(1)</script>' ) );
	}

	public function test_link_text_removes_brackets(): void {
		$this->assertSame( 'Title tag', Text::link_text( '[Title] <em>tag</em>' ) );
	}
}
