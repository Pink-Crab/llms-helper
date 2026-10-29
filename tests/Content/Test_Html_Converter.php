<?php
/**
 * Tests for Html_Converter.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use DOMElement;
use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Html_Converter;

class Test_Html_Converter extends WP_UnitTestCase {

	private const BASE = 'http://example.org/about/';

	private function convert( string $html ): string {
		return ( new Html_Converter() )->convert( $html, self::BASE );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function conversion_provider(): array {
		return array(
			'heading and paragraph'    => array( '<h2>Title</h2><p>Hello <strong>world</strong>.</p>', "## Title\n\nHello **world**." ),
			'heading levels'           => array( '<h1>One</h1><h6>Six</h6>', "# One\n\n###### Six" ),
			'relative and anchor link' => array( '<p><a href="/contact/">Contact</a> and <a href="#top">top</a></p>', '[Contact](http://example.org/contact/) and [top](http://example.org/about/#top)' ),
			'link without text'        => array( '<p><a href="https://x.test/"></a></p>', '[https://x.test/](https://x.test/)' ),
			'script link'              => array( '<p><a href="javascript:alert(1)">Click</a></p>', 'Click' ),
			'link url escaping'        => array( '<p><a href="https://x.test/a (b).pdf">File</a></p>', '[File](https://x.test/a%20%28b%29.pdf)' ),
			'image with caption'       => array( '<figure><img src="/wp-content/uploads/a.jpg" alt="A [crab]"><figcaption>Caption</figcaption></figure>', "![A crab](http://example.org/wp-content/uploads/a.jpg)\n\nCaption" ),
			'relative image'           => array( '<p><img src="img.png" alt="x"></p>', '![x](http://example.org/about/img.png)' ),
			'lazy image'               => array( '<p><img src="data:image/gif;base64,xx" data-src="https://cdn.test/b.jpg" alt=""></p>', '![](https://cdn.test/b.jpg)' ),
			'nested list'              => array( '<ul><li>One<ul><li>Two</li></ul></li><li>Three</li></ul>', "- One\n  - Two\n- Three" ),
			'ordered list with start'  => array( '<ol start="3"><li>A</li><li>B</li></ol>', "3. A\n4. B" ),
			'ordered list nesting'     => array( '<ol><li>A<ul><li>B</li></ul></li></ol>', "1. A\n   - B" ),
			'list item paragraphs'     => array( '<ul><li><p>One</p><p>Two</p></li></ul>', "- One\n  Two" ),
			'table'                    => array( '<table><tr><th>Name</th><th>Size</th></tr><tr><td>Crab | big</td><td>2</td></tr></table>', "| Name | Size |\n| --- | --- |\n| Crab \\| big | 2 |" ),
			'code block language'      => array( '<pre class="wp-block-code"><code class="language-php">echo 1;</code></pre>', "```php\necho 1;\n```" ),
			'code block fence'         => array( '<pre>```inner```</pre>', "````\n```inner```\n````" ),
			'code block line breaks'   => array( '<pre>line1<br>line2</pre>', "```\nline1\nline2\n```" ),
			'inline code backtick'     => array( '<p>Use <code>a`b</code></p>', 'Use ``a`b``' ),
			'blockquote'               => array( '<blockquote><p>One</p><p>Two</p></blockquote>', "> One\n>\n> Two" ),
			'iframe embed'             => array( '<figure><div><iframe src="https://www.youtube.com/embed/x" title="Video title"></iframe></div></figure>', '[Video title](https://www.youtube.com/embed/x)' ),
			'video source'             => array( '<video><source src="/v.mp4"></video>', '[Video](http://example.org/v.mp4)' ),
			'skipped elements'         => array( '<p>Keep</p><script>alert(1)</script><style>p{}</style>', 'Keep' ),
			'emphasis spacing'         => array( '<p>a<em> b </em>c</p>', 'a *b* c' ),
			'strikethrough'            => array( '<p><del>old</del></p>', '~~old~~' ),
			'line break'               => array( '<p>One<br>Two</p>', "One\nTwo" ),
			'horizontal rule'          => array( '<p>A</p><hr><p>B</p>', "A\n\n---\n\nB" ),
			'details'                  => array( '<details><summary>More</summary><p>Hidden</p></details>', "**More**\n\nHidden" ),
			'definition list'          => array( '<dl><dt>Term</dt><dd>Def</dd></dl>', "**Term**\n\nDef" ),
			'loose inline content'     => array( 'Loose <em>text</em><p>Para</p>', "Loose *text*\n\nPara" ),
		);
	}

	/**
	 * @dataProvider conversion_provider
	 */
	public function test_converts( string $html, string $expected ): void {
		$this->assertSame( $expected . "\n", $this->convert( $html ) );
	}

	public function test_empty_html_gives_empty_markdown(): void {
		$this->assertSame( '', $this->convert( "  \n " ) );
	}

	public function test_html_with_only_skipped_content_gives_empty_markdown(): void {
		$this->assertSame( '', $this->convert( '<script>x</script>' ) );
	}

	public function test_html_filter_changes_the_input(): void {
		add_filter(
			'llms_helper_convert_html',
			static function ( string $html, string $base_url ): string {
				return str_replace( 'Old', 'New', $html ) . '<p>' . $base_url . '</p>';
			},
			10,
			2
		);

		$this->assertSame( "New becomes New\n\n" . self::BASE . "\n", $this->convert( '<p>Old becomes Old</p>' ) );
	}

	public function test_markdown_filter_changes_the_output(): void {
		add_filter(
			'llms_helper_convert_markdown',
			static function ( string $markdown, string $html ): string {
				return strtoupper( $markdown ) . strlen( $html );
			},
			10,
			2
		);

		$this->assertSame( "HELLO\n12", $this->convert( '<p>Hello</p>' ) );
	}

	public function test_markdown_filter_runs_on_empty_html(): void {
		add_filter(
			'llms_helper_convert_markdown',
			static function (): string {
				return 'fallback';
			}
		);

		$this->assertSame( 'fallback', $this->convert( '' ) );
	}

	public function test_element_filter_replaces_an_inline_element(): void {
		add_filter(
			'llms_helper_convert_element',
			static function ( $markdown, DOMElement $el, string $context, Html_Converter $converter ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
				return 'mark' === $el->tagName && 'inline' === $context ? '==' . $converter->children_inline( $el ) . '==' : $markdown;
			},
			10,
			4
		);

		$this->assertSame( "A ==highlight== here\n", $this->convert( '<p>A <mark>highlight</mark> here</p>' ) );
	}

	public function test_element_filter_replaces_a_block_element(): void {
		add_filter(
			'llms_helper_convert_element',
			static function ( $markdown, DOMElement $el, string $context, Html_Converter $converter ) {
				if ( 'block' === $context && 'callout' === $el->getAttribute( 'class' ) ) {
					return '> **Note:** ' . $converter->children_blocks( $el );
				}
				return $markdown;
			},
			10,
			4
		);

		$this->assertSame( "Before\n\n> **Note:** Careful\n\nAfter\n", $this->convert( '<p>Before</p><div class="callout"><p>Careful</p></div><p>After</p>' ) );
	}

	public function test_element_filter_returning_null_keeps_the_default(): void {
		add_filter( 'llms_helper_convert_element', '__return_null' );

		$this->assertSame( "## Title\n", $this->convert( '<h2>Title</h2>' ) );
	}

	public function test_skip_elements_filter(): void {
		add_filter(
			'llms_helper_skip_elements',
			static function ( array $tags ): array {
				$tags[] = 'FIGURE';
				return $tags;
			}
		);

		$this->assertSame( "Kept\n", $this->convert( '<p>Kept</p><figure><img src="/a.jpg" alt="a"></figure>' ) );
	}

	public function test_inline_elements_filter(): void {
		add_filter(
			'llms_helper_inline_elements',
			static function ( array $tags ): array {
				$tags[] = 'section';
				return $tags;
			}
		);

		$this->assertSame( "one two\n", $this->convert( 'one <section>two</section>' ) );
	}

	public function test_a_non_array_list_filter_keeps_the_defaults(): void {
		add_filter( 'llms_helper_skip_elements', '__return_false' );

		$this->assertSame( "Keep\n", $this->convert( '<p>Keep</p><script>x</script>' ) );
	}
}
