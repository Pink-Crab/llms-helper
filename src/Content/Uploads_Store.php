<?php
/**
 * Copy store in the uploads folder.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;

/**
 * Files in uploads/llms-helper, laid out by URL path. The folder's dir and url are saved to an option
 * so the early request route can find files before the uploads filters of other plugins load.
 */
class Uploads_Store implements Copy_Store {

	/**
	 * Option holding the folder dir and url.
	 *
	 * @var string
	 */
	public const OPTION = 'llms_helper_store';

	/**
	 * Folder inside uploads.
	 *
	 * @var string
	 */
	public const FOLDER = 'llms-helper';

	/**
	 * Whether the option was refreshed this request.
	 *
	 * @var bool
	 */
	protected bool $refreshed = false;

	/**
	 * Folder dir and url.
	 *
	 * @return array{dir: string, url: string} Empty strings before the first write.
	 */
	public function location(): array {
		$store = get_option( self::OPTION );

		if ( is_array( $store ) && ! empty( $store['dir'] ) && ! empty( $store['url'] ) ) {
			return array(
				'dir' => (string) $store['dir'],
				'url' => (string) $store['url'],
			);
		}

		return array(
			'dir' => '',
			'url' => '',
		);
	}

	/**
	 * Save the folder dir and url from the current uploads settings.
	 *
	 * @return void
	 */
	public function refresh(): void {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}

		$store = array(
			'dir' => untrailingslashit( $uploads['basedir'] ) . '/' . self::FOLDER,
			'url' => untrailingslashit( $uploads['baseurl'] ) . '/' . self::FOLDER,
		);

		if ( $this->location() !== $store ) {
			update_option( self::OPTION, $store, true );
		}

		$this->refreshed = true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( string $path, string $contents ): bool {
		if ( ! $this->refreshed || '' === $this->location()['dir'] ) {
			$this->refresh();
		}

		$file = $this->local_file( $path );
		if ( '' === $file || ! wp_mkdir_p( dirname( $file ) ) ) {
			return false;
		}

		return false !== file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $path ): void {
		if ( ! $this->exists( $path ) ) {
			return;
		}

		$file = $this->local_file( $path );
		wp_delete_file( $file );
		$this->remove_empty_folders( dirname( $file ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists( string $path ): bool {
		$file = $this->local_file( $path );

		return '' !== $file && is_file( $file );
	}

	/**
	 * {@inheritDoc}
	 */
	public function read( string $path ): string {
		if ( ! $this->exists( $path ) ) {
			return '';
		}

		return (string) file_get_contents( $this->local_file( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * {@inheritDoc}
	 */
	public function public_url( string $path ): string {
		$url = $this->location()['url'];

		return '' !== $url && Url_Path::is_safe( $path ) ? $url . Url_Path::encode( '/' . ltrim( $path, '/' ) ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function local_file( string $path ): string {
		$dir = $this->location()['dir'];

		return '' !== $dir && Url_Path::is_safe( $path ) ? $dir . '/' . ltrim( $path, '/' ) : '';
	}

	/**
	 * Remove empty folders from a folder upwards, stopping at the store folder.
	 *
	 * @param string $dir Folder a file was deleted from.
	 * @return void
	 */
	protected function remove_empty_folders( string $dir ): void {
		$root = $this->location()['dir'];

		while ( '' !== $root && str_starts_with( $dir, $root . '/' ) && is_dir( $dir ) && array() === array_diff( (array) scandir( $dir ), array( '.', '..' ) ) ) {
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$dir = dirname( $dir );
		}
	}
}
