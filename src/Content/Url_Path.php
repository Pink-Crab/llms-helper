<?php
/**
 * URL path helpers.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

/**
 * Decoded URL path helpers.
 */
class Url_Path {

	/**
	 * Percent-encode each segment of a path.
	 *
	 * @param string $path Decoded path.
	 * @return string
	 */
	public static function encode( string $path ): string {
		return implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
	}

	/**
	 * Whether a path is safe to map onto a file system.
	 *
	 * @param string $path Decoded path.
	 * @return bool
	 */
	public static function is_safe( string $path ): bool {
		return '' !== $path
			&& ! str_contains( $path, "\0" )
			&& ! preg_match( '#(^|/)\.\.(/|$)#', $path );
	}
}
