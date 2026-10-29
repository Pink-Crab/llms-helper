<?php
/**
 * Tests for Meta_Box.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Controllers\Meta_Box;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;
use PinkCrab\LLMs_Helper\Content\Default_Inclusion_Policy;
use PinkCrab\LLMs_Helper\Content\Markdown_Formatter;

class Test_Meta_Box extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Meta_Box $box;
	private int $post_id;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		delete_option( Option_Settings::OPTION );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->box     = $this->container()->get( Meta_Box::class );
		$this->post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'boxed',
			)
		);
	}

	public function tear_down(): void {
		$_POST = array();
		$this->remove_store();
		parent::tear_down();
	}

	public function test_registers_its_hooks(): void {
		$loader = new Hook_Loader();
		$this->box->register( $loader );

		$this->assertSame( array( 'add_meta_boxes', 'save_post', 'admin_post_llms_helper_build_copy' ), array_column( $loader->hooks(), 'hook' ) );
	}

	public function test_box_is_added_to_included_post_types_only(): void {
		global $wp_meta_boxes;
		update_option( Option_Settings::OPTION, array( 'post_types' => array( 'page' ) ) );

		$this->box->add( 'page' );
		$this->box->add( 'post' );

		$this->assertArrayHasKey( 'llms-helper', $wp_meta_boxes['page']['side']['low'] );
		$this->assertArrayNotHasKey( 'post', $wp_meta_boxes ?? array() );
	}

	public function test_save_sets_and_clears_the_exclude_flag(): void {
		$_POST[ Meta_Box::NONCE ] = wp_create_nonce( Meta_Box::NONCE );
		$_POST[ Meta_Box::FIELD ] = '1';

		$this->box->save( $this->post_id );
		$this->assertSame( '1', get_post_meta( $this->post_id, Default_Inclusion_Policy::META_EXCLUDE, true ) );

		unset( $_POST[ Meta_Box::FIELD ] );
		$this->box->save( $this->post_id );
		$this->assertSame( '', get_post_meta( $this->post_id, Default_Inclusion_Policy::META_EXCLUDE, true ) );
	}

	public function test_save_sets_and_clears_the_markdown_override(): void {
		$_POST[ Meta_Box::NONCE ]          = wp_create_nonce( Meta_Box::NONCE );
		$_POST[ Meta_Box::MARKDOWN_FIELD ] = wp_slash( "  ## Own words\n\nWith a \\ backslash.  " );

		$this->box->save( $this->post_id );
		$this->assertSame( "## Own words\n\nWith a \\ backslash.", get_post_meta( $this->post_id, Markdown_Formatter::META_MARKDOWN, true ) );

		$_POST[ Meta_Box::MARKDOWN_FIELD ] = '   ';
		$this->box->save( $this->post_id );
		$this->assertSame( '', get_post_meta( $this->post_id, Markdown_Formatter::META_MARKDOWN, true ) );

		unset( $_POST[ Meta_Box::MARKDOWN_FIELD ] );
	}

	public function test_markdown_override_is_filtered_for_users_without_unfiltered_html(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$post_id                           = self::factory()->post->create( array( 'post_author' => $author ) );
		$_POST[ Meta_Box::NONCE ]          = wp_create_nonce( Meta_Box::NONCE );
		$_POST[ Meta_Box::MARKDOWN_FIELD ] = 'Text<script>alert(1)</script>';

		$this->box->save( $post_id );

		$this->assertSame( 'Textalert(1)', get_post_meta( $post_id, Markdown_Formatter::META_MARKDOWN, true ) );

		unset( $_POST[ Meta_Box::MARKDOWN_FIELD ] );
	}

	public function test_render_has_the_markdown_override_field(): void {
		update_post_meta( $this->post_id, Markdown_Formatter::META_MARKDOWN, 'Saved <b>markdown</b>' );

		ob_start();
		$this->box->render( get_post( $this->post_id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="' . Meta_Box::MARKDOWN_FIELD . '"', $html );
		$this->assertStringContainsString( 'Saved &lt;b&gt;markdown&lt;/b&gt;', $html );
	}

	public function test_save_needs_a_valid_nonce(): void {
		$_POST[ Meta_Box::NONCE ] = 'bad';
		$_POST[ Meta_Box::FIELD ] = '1';

		$this->box->save( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, Default_Inclusion_Policy::META_EXCLUDE, true ) );
	}

	public function test_save_needs_permission(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST[ Meta_Box::NONCE ] = wp_create_nonce( Meta_Box::NONCE );
		$_POST[ Meta_Box::FIELD ] = '1';

		$this->box->save( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, Default_Inclusion_Policy::META_EXCLUDE, true ) );
	}

	public function test_render_links_to_the_copy(): void {
		ob_start();
		$this->box->render( get_post( $this->post_id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="' . Meta_Box::FIELD . '"', $html );
		$this->assertStringContainsString( 'http://example.org/boxed/index.html.md', $html );
	}

	public function test_render_has_a_build_button_for_published_posts(): void {
		ob_start();
		$this->box->render( get_post( $this->post_id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( esc_url( $this->box->build_url( $this->post_id ) ), $html );
		$this->assertStringContainsString( 'action=llms_helper_build_copy', $this->box->build_url( $this->post_id ) );
		$this->assertStringContainsString( '_wpnonce=', $this->box->build_url( $this->post_id ) );
	}

	public function test_render_has_no_build_button_for_drafts(): void {
		$draft = self::factory()->post->create_and_get( array( 'post_status' => 'draft' ) );

		ob_start();
		$this->box->render( $draft );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'llms_helper_build_copy', $html );
	}
}
