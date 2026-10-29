<?php
/**
 * Writes llms.txt.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Interfaces\Cache_Purger;
use PinkCrab\LLMs_Helper\Interfaces\Index_Generator;

/**
 * Writes the generated llms.txt to the store, and to the site root when not redirecting.
 */
class Index_Builder {

	/**
	 * Option holding the site root llms.txt this plugin wrote, so it never touches anyone else's.
	 *
	 * @var string
	 */
	public const OPTION_ROOT_FILE = 'llms_helper_root_file';

	/**
	 * Set the dependencies.
	 *
	 * @param Index_Generator $generator Produces the llms.txt contents.
	 * @param Copy_Store      $store     Where llms.txt is kept.
	 * @param Url_Map         $urls      URL mapping.
	 * @param Settings        $settings  Settings.
	 * @param Cache_Purger    $purger    Clears cached responses.
	 */
	public function __construct(
		protected Index_Generator $generator,
		protected Copy_Store $store,
		protected Url_Map $urls,
		protected Settings $settings,
		protected Cache_Purger $purger
	) {}

	/**
	 * Generate and write llms.txt.
	 *
	 * @return void
	 */
	public function build(): void {
		$contents = $this->generator->generate();
		$path     = $this->urls->index_path();

		$this->store->write( $path, $contents );

		if ( $this->settings->redirect() ) {
			$this->remove_root_file();
		} else {
			$this->write_root_file( $contents );
		}

		$this->purger->purge( array_values( array_filter( array( $this->urls->url_for_path( $path ), $this->store->public_url( $path ) ) ) ) );
	}

	/**
	 * Path of llms.txt in the site root.
	 *
	 * @return string
	 */
	public function root_file(): string {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return get_home_path() . basename( $this->urls->index_path() );
	}

	/**
	 * Write llms.txt to the site root, unless another llms.txt is already there.
	 *
	 * @param string $contents Contents.
	 * @return bool Whether it was written.
	 */
	public function write_root_file( string $contents ): bool {
		$file  = $this->root_file();
		$owned = get_option( self::OPTION_ROOT_FILE ) === $file;

		if ( file_exists( $file ) && ! $owned ) {
			return false;
		}

		if ( ! wp_is_writable( file_exists( $file ) ? $file : dirname( $file ) ) ) {
			return false;
		}

		if ( false === file_put_contents( $file, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}

		update_option( self::OPTION_ROOT_FILE, $file, false );
		return true;
	}

	/**
	 * Remove the site root llms.txt this plugin wrote.
	 *
	 * @return void
	 */
	public function remove_root_file(): void {
		$file = get_option( self::OPTION_ROOT_FILE );
		if ( ! is_string( $file ) || '' === $file ) {
			return;
		}

		wp_delete_file( $file );
		delete_option( self::OPTION_ROOT_FILE );
	}
}
