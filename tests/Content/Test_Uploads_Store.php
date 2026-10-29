<?php
/**
 * Tests for Uploads_Store.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Content\Uploads_Store;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;

class Test_Uploads_Store extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function tear_down(): void {
		$this->remove_store();
		parent::tear_down();
	}

	public function test_location_is_empty_before_the_first_refresh(): void {
		delete_option( Uploads_Store::OPTION );
		$store = new Uploads_Store();

		$this->assertSame(
			array(
				'dir' => '',
				'url' => '',
			),
			$store->location()
		);
		$this->assertSame( '', $store->local_file( '/llms.txt' ) );
		$this->assertSame( '', $store->public_url( '/llms.txt' ) );
		$this->assertFalse( $store->exists( '/llms.txt' ) );
	}

	public function test_refresh_saves_the_uploads_folder(): void {
		$uploads = wp_upload_dir( null, false );
		$store   = new Uploads_Store();
		$store->refresh();

		$this->assertSame(
			array(
				'dir' => untrailingslashit( $uploads['basedir'] ) . '/llms-helper',
				'url' => untrailingslashit( $uploads['baseurl'] ) . '/llms-helper',
			),
			$store->location()
		);
	}

	public function test_first_write_saves_the_location(): void {
		delete_option( Uploads_Store::OPTION );

		( new Uploads_Store() )->write( '/llms.txt', 'x' );

		$this->assertNotSame( '', ( new Uploads_Store() )->location()['dir'] );
	}

	public function test_write_recovers_when_the_saved_location_is_removed(): void {
		$store = new Uploads_Store();
		$store->write( '/first.md', 'x' );
		delete_option( Uploads_Store::OPTION );

		$this->assertTrue( $store->write( '/second.md', 'y' ) );
		$this->assertSame( 'y', $store->read( '/second.md' ) );
	}

	public function test_local_file_and_public_url_for_a_path(): void {
		$store = new Uploads_Store();
		$store->refresh();
		$location = $store->location();

		$this->assertSame( $location['dir'] . '/café/index.html.md', $store->local_file( '/café/index.html.md' ) );
		$this->assertSame( $location['url'] . '/caf%C3%A9/index.html.md', $store->public_url( '/café/index.html.md' ) );
	}

	public function test_unsafe_paths_map_to_nothing(): void {
		$store = new Uploads_Store();
		$store->refresh();

		$this->assertSame( '', $store->local_file( '/../wp-config.php' ) );
		$this->assertSame( '', $store->public_url( '/../wp-config.php' ) );
		$this->assertFalse( $store->exists( '/../wp-config.php' ) );
		$this->assertFalse( $store->write( '/../escape.md', 'x' ) );
	}

	public function test_write_read_exists_and_delete(): void {
		$store = new Uploads_Store();

		$this->assertFalse( $store->exists( '/about/index.html.md' ) );
		$this->assertSame( '', $store->read( '/about/index.html.md' ) );

		$this->assertTrue( $store->write( '/about/index.html.md', '# About' ) );
		$this->assertTrue( $store->exists( '/about/index.html.md' ) );
		$this->assertSame( '# About', $store->read( '/about/index.html.md' ) );
		$this->assertSame( '# About', file_get_contents( $this->store_dir() . '/about/index.html.md' ) );

		$store->delete( '/about/index.html.md' );

		$this->assertFalse( $store->exists( '/about/index.html.md' ) );
	}

	public function test_delete_removes_folders_left_empty(): void {
		$store = new Uploads_Store();
		$store->write( '/parent/child/index.html.md', 'x' );

		$store->delete( '/parent/child/index.html.md' );

		$this->assertDirectoryDoesNotExist( $this->store_dir() . '/parent' );
		$this->assertDirectoryExists( $this->store_dir() );
	}

	public function test_delete_keeps_folders_that_still_hold_files(): void {
		$store = new Uploads_Store();
		$store->write( '/parent/index.html.md', 'x' );
		$store->write( '/parent/child/index.html.md', 'y' );

		$store->delete( '/parent/child/index.html.md' );

		$this->assertDirectoryDoesNotExist( $this->store_dir() . '/parent/child' );
		$this->assertTrue( $store->exists( '/parent/index.html.md' ) );
	}

	public function test_a_folder_is_not_a_copy(): void {
		$store = new Uploads_Store();
		$store->write( '/about/index.html.md', 'x' );

		$this->assertFalse( $store->exists( '/about' ) );
	}

	public function test_deleting_a_missing_file_does_nothing(): void {
		$store = new Uploads_Store();
		$store->refresh();
		$store->delete( '/missing/index.html.md' );

		$this->assertFalse( $store->exists( '/missing/index.html.md' ) );
	}
}
