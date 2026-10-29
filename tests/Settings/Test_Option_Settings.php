<?php
/**
 * Tests for Option_Settings.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Settings;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;

class Test_Option_Settings extends WP_UnitTestCase {

	public function test_defaults_when_nothing_is_saved(): void {
		delete_option( Option_Settings::OPTION );

		$this->assertSame( Option_Settings::defaults(), ( new Option_Settings() )->all() );
	}

	public function test_saved_values_are_read_and_cast(): void {
		update_option(
			Option_Settings::OPTION,
			array(
				'post_types'     => array( 'page', 7 ),
				'redirect'       => 0,
				'summary'        => 'Summary',
				'notes'          => 'Notes',
				'missing_copy'   => '410',
				'index_llms_txt' => 1,
			)
		);
		$settings = new Option_Settings();

		$this->assertSame( array( 'page', '7' ), $settings->post_types() );
		$this->assertFalse( $settings->redirect() );
		$this->assertSame( 'Summary', $settings->summary() );
		$this->assertSame( 'Notes', $settings->notes() );
		$this->assertSame( '410', $settings->missing_copy() );
		$this->assertTrue( $settings->index_llms_txt() );
	}

	public function test_missing_keys_fall_back_to_defaults(): void {
		update_option( Option_Settings::OPTION, array( 'summary' => 'Only this' ) );
		$settings = new Option_Settings();

		$this->assertSame( array( 'post', 'page' ), $settings->post_types() );
		$this->assertTrue( $settings->redirect() );
		$this->assertSame( '', $settings->notes() );
		$this->assertSame( 'page', $settings->missing_copy() );
		$this->assertFalse( $settings->index_llms_txt() );
	}

	public function test_unknown_missing_copy_value_falls_back_to_page(): void {
		update_option( Option_Settings::OPTION, array( 'missing_copy' => '500' ) );

		$this->assertSame( 'page', ( new Option_Settings() )->missing_copy() );
	}

	public function test_a_non_array_option_gives_defaults(): void {
		update_option( Option_Settings::OPTION, 'broken' );

		$this->assertSame( Option_Settings::defaults(), ( new Option_Settings() )->all() );
	}

	public function test_parse_casts_a_value_without_reading_the_option(): void {
		update_option( Option_Settings::OPTION, array( 'summary' => 'Saved' ) );

		$values = Option_Settings::parse(
			array(
				'post_types' => array( 'page' ),
				'redirect'   => '',
			)
		);

		$this->assertSame( array( 'page' ), $values['post_types'] );
		$this->assertFalse( $values['redirect'] );
		$this->assertSame( '', $values['summary'] );
		$this->assertSame( Option_Settings::defaults(), Option_Settings::parse( 'broken' ) );
	}

	public function test_includes_type(): void {
		update_option( Option_Settings::OPTION, array( 'post_types' => array( 'page' ) ) );
		$settings = new Option_Settings();

		$this->assertTrue( $settings->includes_type( 'page' ) );
		$this->assertFalse( $settings->includes_type( 'post' ) );
	}
}
