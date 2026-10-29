<?php
/**
 * URL map following llmstxt.org.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use PinkCrab\LLMs_Helper\Interfaces\Url_Map;

/**
 * Copies sit at the page URL plus .md: /about/ has /about/index.html.md, /page.html has /page.html.md.
 * llms.txt sits under the home path.
 */
class Llmstxt_Url_Map implements Url_Map {

	/**
	 * Copy file name for URLs ending in a slash.
	 *
	 * @var string
	 */
	public const INDEX_COPY = 'index.html.md';

	/**
	 * Copy extension.
	 *
	 * @var string
	 */
	public const EXTENSION = '.md';

	/**
	 * File name of llms.txt.
	 *
	 * @var string
	 */
	public const INDEX_FILE = 'llms.txt';

	/**
	 * {@inheritDoc}
	 */
	public function copy_path( string $permalink ): string {
		$parts = wp_parse_url( $permalink );
		if ( ! is_array( $parts ) || isset( $parts['query'] ) ) {
			return '';
		}

		$path = rawurldecode( $parts['path'] ?? '' );

		return $this->with_extension( '' === $path ? '/' : $path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function copy_url( string $permalink ): string {
		return str_contains( $permalink, '?' ) ? '' : $this->with_extension( $permalink );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_copy_request( string $raw_path ): bool {
		return str_ends_with( $raw_path, self::EXTENSION );
	}

	/**
	 * {@inheritDoc}
	 */
	public function page_uri( string $raw_path ): string {
		if ( str_ends_with( $raw_path, '/' . self::INDEX_COPY ) ) {
			return substr( $raw_path, 0, -strlen( self::INDEX_COPY ) );
		}

		return substr( $raw_path, 0, -strlen( self::EXTENSION ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function index_path(): string {
		return untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) . '/' . self::INDEX_FILE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function url_for_path( string $path ): string {
		$home = wp_parse_url( home_url() );
		if ( ! is_array( $home ) || empty( $home['host'] ) ) {
			return Url_Path::encode( $path );
		}

		$port = isset( $home['port'] ) ? ':' . $home['port'] : '';

		return ( $home['scheme'] ?? 'https' ) . '://' . $home['host'] . $port . Url_Path::encode( $path );
	}

	/**
	 * Add the copy ending to a path or URL.
	 *
	 * @param string $value Path or URL.
	 * @return string
	 */
	protected function with_extension( string $value ): string {
		return str_ends_with( $value, '/' ) ? $value . self::INDEX_COPY : $value . self::EXTENSION;
	}
}
