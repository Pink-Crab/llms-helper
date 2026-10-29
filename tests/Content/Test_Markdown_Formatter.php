<?php
/**
 * Tests for Markdown_Formatter.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Markdown_Formatter;
use PinkCrab\LLMs_Helper\Tests\Fakes\Echo_Converter;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Renderer;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Description;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Markdown_Formatter extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Fixed_Description $descriptions;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		$this->unhook_plugin_post_events();
		$this->descriptions = new Fixed_Description();
	}

	private function formatter(): Markdown_Formatter {
		return new Markdown_Formatter( new Fixed_Renderer( '<p>Body</p>' ), new Echo_Converter(), $this->descriptions );
	}

	public function test_the_posts_own_markdown_replaces_the_converted_content(): void {
		$post = self::factory()->post->create_and_get( array( 'post_status' => 'publish' ) );
		update_post_meta( $post->ID, Markdown_Formatter::META_MARKDOWN, "  Own **words**.\n" );

		$copy = $this->formatter()->format( $post );

		$this->assertStringEndsWith( "\n\nOwn **words**.\n", $copy );
		$this->assertStringNotContainsString( 'converted(', $copy );
		$this->assertStringStartsWith( "---\n", $copy );
	}

	public function test_front_matter_has_the_meta_description_when_there_is_one(): void {
		$post = self::factory()->post->create_and_get( array( 'post_status' => 'publish' ) );

		$this->assertArrayNotHasKey( 'description', $this->formatter()->front_matter( $post ) );

		$this->descriptions->post = 'From the SEO plugin.';

		$this->assertSame( 'From the SEO plugin.', $this->formatter()->front_matter( $post )['description'] );
	}

	public function test_format_joins_front_matter_title_and_converted_content(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'About Us',
				'post_name'   => 'about',
			)
		);

		$formatter = $this->formatter();

		$this->assertSame(
			$formatter->format_front_matter( $formatter->front_matter( $post ) )
				. "\n# About Us\n\n"
				. "converted(<p>Body</p> @ http://example.org/about/)\n",
			$formatter->format( $post )
		);
	}

	public function test_front_matter(): void {
		$author   = self::factory()->user->create( array( 'display_name' => 'Crab Author' ) );
		$category = self::factory()->category->create( array( 'name' => 'News' ) );
		$id       = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_title'    => 'Fish &amp; Chips',
				'post_excerpt'  => 'A <em>short</em> summary.',
				'post_author'   => $author,
				'post_category' => array( $category ),
				'tags_input'    => array( 'crabs', 'tide' ),
				'post_date_gmt' => '2026-01-02 03:04:05',
			)
		);
		$image    = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/crab.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $id,
			)
		);
		set_post_thumbnail( $id, $image );

		$data = $this->formatter()->front_matter( get_post( $id ) );

		$this->assertSame( 'Fish & Chips', $data['title'] );
		$this->assertSame( get_permalink( $id ), $data['url'] );
		$this->assertSame( 'post', $data['type'] );
		$this->assertSame( '2026-01-02T03:04:05+00:00', $data['published'] );
		$this->assertSame( 'Crab Author', $data['author'] );
		$this->assertSame( 'A short summary.', $data['excerpt'] );
		$this->assertStringEndsWith( '2026/01/crab.jpg', $data['featured_image'] );
		$this->assertSame( array( 'News' ), $data['category'] );
		$this->assertSame( array( 'crabs', 'tide' ), $data['post_tag'] );
	}

	public function test_front_matter_leaves_out_empty_values(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_excerpt' => '',
			)
		);

		$data = $this->formatter()->front_matter( $post );

		$this->assertArrayNotHasKey( 'excerpt', $data );
		$this->assertArrayNotHasKey( 'featured_image', $data );
	}

	public function test_untitled_post_uses_its_slug_as_the_title(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'publish',
				'post_title'  => '',
				'post_name'   => 'untitled-slug',
			)
		);

		$this->assertSame( 'untitled-slug', $this->formatter()->front_matter( $post )['title'] );
		$this->assertStringContainsString( "\n# untitled-slug\n", $this->formatter()->format( $post ) );
	}

	public function test_front_matter_leaves_out_an_empty_author(): void {
		$post = self::factory()->post->create_and_get( array( 'post_author' => 0 ) );

		$this->assertArrayNotHasKey( 'author', $this->formatter()->front_matter( $post ) );
	}

	public function test_front_matter_can_be_filtered(): void {
		add_filter(
			'llms_helper_front_matter',
			static function ( array $data ): array {
				$data['extra'] = 'value';
				return $data;
			}
		);

		$this->assertSame( 'value', $this->formatter()->front_matter( self::factory()->post->create_and_get() )['extra'] );
	}

	public function test_format_front_matter_encodes_values_as_json(): void {
		$this->assertSame(
			"---\ntitle: \"Say \\\"hi\\\"\"\nurl: \"http://example.org/a/\"\ntags: [\"a\",\"b\"]\n---\n",
			$this->formatter()->format_front_matter(
				array(
					'title' => 'Say "hi"',
					'url'   => 'http://example.org/a/',
					'Tags'  => array( 'a', 'b' ),
				)
			)
		);
	}
}
