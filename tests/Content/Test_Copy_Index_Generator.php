<?php
/**
 * Tests for Copy_Index_Generator.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fake_Settings;
use PinkCrab\LLMs_Helper\Content\Copy_Index_Generator;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Description;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Copy_Index_Generator extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Fake_Settings $settings;
	private Fixed_Description $descriptions;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
		update_option( 'blogname', 'Crab Shack' );
		update_option( 'blogdescription', 'Fresh crabs' );
		$this->settings     = new Fake_Settings();
		$this->descriptions = new Fixed_Description();
	}

	private function generate(): string {
		return ( new Copy_Index_Generator( $this->settings, new Llmstxt_Url_Map(), $this->descriptions ) )->generate();
	}

	public function test_seo_site_description_comes_before_the_tagline(): void {
		$this->descriptions->site = 'From the SEO plugin';

		$this->assertSame( "# Crab Shack\n\n> From the SEO plugin\n", $this->generate() );
	}

	public function test_the_summary_setting_comes_before_the_seo_site_description(): void {
		$this->descriptions->site   = 'From the SEO plugin';
		$this->settings->summary    = 'From settings';
		$this->settings->post_types = array();

		$this->assertStringContainsString( '> From settings', $this->generate() );
	}

	public function test_a_link_is_noted_with_the_meta_description_kept_at_build_before_the_excerpt(): void {
		$this->settings->post_types = array( 'post' );
		$id                         = $this->with_copy( 'post', 'Noted', 'noted', array( 'post_excerpt' => 'The excerpt.' ) );

		$this->assertStringContainsString( '- [Noted](http://example.org/noted/index.html.md): The excerpt.', $this->generate() );

		update_post_meta( $id, Copy_Builder::META_DESCRIPTION, 'The meta description.' );

		$this->assertStringContainsString( '- [Noted](http://example.org/noted/index.html.md): The meta description.', $this->generate() );
	}

	/**
	 * A published post with a copy at /{slug}/index.html.md.
	 */
	private function with_copy( string $type, string $title, string $slug, array $args = array() ): int {
		$id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => $type,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $slug,
					// The test factory fills in an excerpt by default.
					'post_excerpt' => '',
				),
				$args
			)
		);
		update_post_meta( $id, Copy_Builder::META_FILE, '/' . $slug . '/index.html.md' );

		return $id;
	}

	public function test_header_uses_the_site_name_and_tagline(): void {
		$this->assertSame( "# Crab Shack\n\n> Fresh crabs\n", $this->generate() );
	}

	public function test_summary_and_notes_from_settings(): void {
		$this->settings->summary = 'Our summary';
		$this->settings->notes   = 'Some notes.';

		$this->assertSame( "# Crab Shack\n\n> Our summary\n\nSome notes.\n", $this->generate() );
	}

	public function test_no_summary_line_without_a_tagline_or_summary(): void {
		update_option( 'blogdescription', '' );

		$this->assertSame( "# Crab Shack\n", $this->generate() );
	}

	public function test_one_section_per_post_type_linking_each_copy(): void {
		$this->with_copy( 'page', 'Second', 'second', array( 'menu_order' => 2 ) );
		$this->with_copy(
			'page',
			'First',
			'first',
			array(
				'menu_order'   => 1,
				'post_excerpt' => 'The first page.',
			)
		);
		$this->with_copy( 'post', 'Old [news]', 'old-news', array( 'post_date' => '2026-01-01 00:00:00' ) );
		$this->with_copy( 'post', 'New news', 'new-news', array( 'post_date' => '2026-02-01 00:00:00' ) );

		$expected = implode(
			"\n",
			array(
				'# Crab Shack',
				'',
				'> Fresh crabs',
				'',
				'## Posts',
				'',
				'- [New news](http://example.org/new-news/index.html.md)',
				'- [Old news](http://example.org/old-news/index.html.md)',
				'',
				'## Pages',
				'',
				'- [First](http://example.org/first/index.html.md): The first page.',
				'- [Second](http://example.org/second/index.html.md)',
			)
		) . "\n";

		$this->assertSame( $expected, $this->generate() );
	}

	public function test_untitled_post_is_listed_by_its_slug(): void {
		$this->settings->post_types = array( 'page' );
		$this->with_copy( 'page', '', 'untitled-slug' );

		$this->assertStringContainsString( '- [untitled-slug](http://example.org/untitled-slug/index.html.md)', $this->generate() );
	}

	public function test_posts_without_a_copy_are_not_listed(): void {
		$this->with_copy( 'page', 'Listed', 'listed' );
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'No copy',
			)
		);

		$contents = $this->generate();

		$this->assertStringContainsString( '[Listed]', $contents );
		$this->assertStringNotContainsString( '[No copy]', $contents );
	}

	public function test_unpublished_posts_with_a_copy_are_not_listed(): void {
		$this->with_copy( 'page', 'Draft', 'draft', array( 'post_status' => 'draft' ) );

		$this->assertStringNotContainsString( '[Draft]', $this->generate() );
	}

	public function test_types_not_in_settings_are_not_listed(): void {
		$this->with_copy( 'post', 'A post', 'a-post' );
		$this->settings->post_types = array( 'page' );

		$this->assertStringNotContainsString( '## Posts', $this->generate() );
	}

	public function test_unknown_post_types_are_skipped(): void {
		$this->settings->post_types = array( 'not_a_type', 'page' );
		$this->with_copy( 'page', 'Page', 'page-one' );

		$this->assertStringContainsString( '## Pages', $this->generate() );
	}

	public function test_lists_more_than_one_page_of_rows(): void {
		$this->settings->post_types = array( 'page' );
		for ( $i = 1; $i <= Copy_Index_Generator::PAGE_SIZE + 1; $i++ ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			);
			update_post_meta( $id, Copy_Builder::META_FILE, '/p' . $i . '/index.html.md' );
		}

		$this->assertSame( Copy_Index_Generator::PAGE_SIZE + 1, substr_count( $this->generate(), "\n- [" ) );
	}
}
