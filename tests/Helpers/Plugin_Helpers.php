<?php
/**
 * Shared test helpers.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Helpers;

use SplFileInfo;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PinkCrab\LLMs_Helper\Plugin;
use PinkCrab\LLMs_Helper\Container\Container;
use PinkCrab\LLMs_Helper\Content\Uploads_Store;
use PinkCrab\LLMs_Helper\Controllers\Post_Events;

/**
 * Files on disk are not rolled back with the database, so tests clean the store themselves.
 */
trait Plugin_Helpers {

	/**
	 * A container wired the way the plugin wires its own.
	 */
	protected function container(): Container {
		return new Container( Plugin::di_rules() );
	}

	/**
	 * Take the booted plugin's Post_Events callbacks off their hooks, so saving a post in a test
	 * does not also build a copy. WP_UnitTestCase restores hooks after each test.
	 */
	protected function unhook_plugin_post_events(): void {
		global $wp_filter;

		foreach ( array( 'wp_after_insert_post', 'before_delete_post' ) as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Post_Events ) {
						remove_action( $hook, $callback['function'], $priority );
					}
				}
			}
		}
	}

	protected function store_dir(): string {
		$uploads = wp_upload_dir( null, false );
		return untrailingslashit( $uploads['basedir'] ) . '/' . Uploads_Store::FOLDER;
	}

	protected function remove_store(): void {
		$dir = $this->store_dir();
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		/** @var SplFileInfo $item */
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $dir );
	}
}
