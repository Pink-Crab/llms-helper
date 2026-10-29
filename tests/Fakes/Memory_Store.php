<?php
/**
 * Copy store held in memory.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;

class Memory_Store implements Copy_Store {

	/**
	 * @var array<string, string> Contents by path.
	 */
	public array $files = array();

	/**
	 * @param string $url_base  Public URL prefix, empty for a store with no public URL.
	 * @param string $file_base Local folder prefix, empty for a store not on local disk.
	 * @param bool   $writable  Whether writes succeed.
	 */
	public function __construct(
		public string $url_base = 'https://cdn.test/llms',
		public string $file_base = '',
		public bool $writable = true
	) {}

	public function write( string $path, string $contents ): bool {
		if ( ! $this->writable ) {
			return false;
		}
		$this->files[ $path ] = $contents;
		return true;
	}

	public function delete( string $path ): void {
		unset( $this->files[ $path ] );
	}

	public function exists( string $path ): bool {
		return isset( $this->files[ $path ] );
	}

	public function read( string $path ): string {
		return $this->files[ $path ] ?? '';
	}

	public function public_url( string $path ): string {
		return '' === $this->url_base ? '' : $this->url_base . $path;
	}

	public function local_file( string $path ): string {
		return '' === $this->file_base ? '' : $this->file_base . $path;
	}
}
