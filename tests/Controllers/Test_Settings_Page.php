<?php
/**
 * Tests for Settings_Page.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Controllers;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Controllers\Settings_Page;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Settings_Page extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Settings_Page $page;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
		delete_option( Option_Settings::OPTION );
		$this->page = $this->container()->get( Settings_Page::class );
	}

	public function test_registers_its_hooks(): void {
		$loader = new Hook_Loader();
		$this->page->register( $loader );

		$this->assertSame(
			array( 'admin_menu', 'admin_init', 'admin_post_llms_helper_build', 'add_option_llms_helper_settings', 'update_option_llms_helper_settings' ),
			array_column( $loader->hooks(), 'hook' )
		);
	}

	public function test_sanitize_keeps_known_values_and_casts_the_rest(): void {
		$this->assertSame(
			array(
				'post_types'     => array( 'post', 'page' ),
				'redirect'       => true,
				'summary'        => 'A summary',
				'notes'          => "Line one\nLine two",
				'missing_copy'   => '410',
				'index_llms_txt' => true,
			),
			$this->page->sanitize(
				array(
					'post_types'     => array( 'post', 'page', 'attachment', 'not_a_type' ),
					'redirect'       => '1',
					'summary'        => '<b>A summary</b>',
					'notes'          => "Line one\nLine two<script>x</script>",
					'missing_copy'   => '410',
					'index_llms_txt' => '1',
				)
			)
		);
	}

	public function test_sanitize_handles_missing_and_bad_input(): void {
		$this->assertSame(
			array(
				'post_types'     => array(),
				'redirect'       => false,
				'summary'        => '',
				'notes'          => '',
				'missing_copy'   => 'page',
				'index_llms_txt' => false,
			),
			$this->page->sanitize( 'not an array' )
		);
	}

	public function test_sanitize_rejects_an_unknown_missing_copy_value(): void {
		$this->assertSame( 'page', $this->page->sanitize( array( 'missing_copy' => '500' ) )['missing_copy'] );
	}

	public function test_attachments_are_not_offered(): void {
		$types = $this->page->available_post_types();

		$this->assertArrayHasKey( 'post', $types );
		$this->assertArrayHasKey( 'page', $types );
		$this->assertArrayNotHasKey( 'attachment', $types );
	}

	public function test_register_setting(): void {
		$this->page->register_setting();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( Option_Settings::OPTION, $registered );
		$this->assertSame( Settings_Page::PAGE, $registered[ Option_Settings::OPTION ]['group'] );
	}

	private function clear_queue(): void {
		wp_unschedule_hook( Scheduler::HOOK_BATCH );
		wp_unschedule_hook( Scheduler::HOOK_INDEX );
	}

	public function test_first_save_that_changes_post_types_queues_a_build(): void {
		$this->clear_queue();

		update_option( Option_Settings::OPTION, array( 'post_types' => array( 'page' ) ) );

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ) );
	}

	public function test_saving_new_post_types_queues_a_build(): void {
		update_option( Option_Settings::OPTION, Option_Settings::defaults() );
		$this->clear_queue();

		update_option( Option_Settings::OPTION, array_merge( Option_Settings::defaults(), array( 'post_types' => array( 'post' ) ) ) );

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ) );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function llms_txt_change_provider(): array {
		return array(
			'summary'         => array( array( 'summary' => 'New' ) ),
			'notes'           => array( array( 'notes' => 'New' ) ),
			'redirect'        => array( array( 'redirect' => false ) ),
			'post type order' => array( array( 'post_types' => array( 'page', 'post' ) ) ),
		);
	}

	/**
	 * @dataProvider llms_txt_change_provider
	 */
	public function test_llms_txt_changes_queue_only_llms_txt( array $change ): void {
		$this->clear_queue();

		$this->page->settings_changed( Option_Settings::defaults(), array_merge( Option_Settings::defaults(), $change ) );

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_INDEX ) );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ) );
	}

	public function test_missing_copy_change_queues_nothing(): void {
		$this->clear_queue();

		$this->page->settings_changed( Option_Settings::defaults(), array_merge( Option_Settings::defaults(), array( 'missing_copy' => '410' ) ) );

		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_INDEX ) );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ) );
	}

	public function test_indexing_change_queues_nothing(): void {
		$this->clear_queue();

		$this->page->settings_changed( Option_Settings::defaults(), array_merge( Option_Settings::defaults(), array( 'index_llms_txt' => true ) ) );

		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_INDEX ) );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_BATCH, array( 0 ) ) );
	}

	public function test_copy_count(): void {
		foreach ( self::factory()->post->create_many( 2 ) as $id ) {
			update_post_meta( $id, Copy_Builder::META_FILE, '/x' . $id . '/index.html.md' );
		}

		$this->assertSame( 2, $this->page->copy_count() );
	}

	public function test_render_shows_the_form_to_admins(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		$this->page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="llms_helper_settings[post_types][]" value="page"', $html );
		$this->assertStringContainsString( 'Pages (page)', $html );
		$this->assertStringContainsString( 'name="llms_helper_settings[redirect]"', $html );
		$this->assertStringContainsString( 'name="llms_helper_settings[missing_copy]"', $html );
		$this->assertStringContainsString( 'name="llms_helper_settings[index_llms_txt]"', $html );
		$this->assertStringContainsString( 'value="410"', $html );
		$this->assertStringContainsString( 'value="' . Settings_Page::BUILD_ACTION . '"', $html );
		$this->assertStringContainsString( 'http://example.org/llms.txt', $html );
	}

	public function test_render_warns_of_overdue_jobs_and_a_failed_build(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		wp_unschedule_hook( Scheduler::HOOK_INDEX );
		delete_option( Scheduler::OPTION_FAILED );
		$render = function (): string {
			ob_start();
			$this->page->render();
			return (string) ob_get_clean();
		};

		$this->assertStringNotContainsString( 'minutes overdue', $render() );
		$this->assertStringNotContainsString( 'stopped after post', $render() );

		wp_schedule_single_event( time() - Scheduler::OVERDUE_AFTER - 60, Scheduler::HOOK_INDEX );
		update_option( Scheduler::OPTION_FAILED, 42 );

		$this->assertStringContainsString( 'more than 15 minutes overdue', $render() );
		$this->assertStringContainsString( 'The last build stopped after post 42', $render() );

		wp_unschedule_hook( Scheduler::HOOK_INDEX );
	}

	public function test_render_shows_nothing_to_other_users(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		ob_start();
		$this->page->render();

		$this->assertSame( '', ob_get_clean() );
	}
}
