<?php
/**
 * Plain text helpers.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

/**
 * Plain text helpers.
 */
class Text {

	/**
	 * Single line of plain text from HTML or text.
	 *
	 * @param string $text HTML or text.
	 * @return string
	 */
	public static function plain( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Plain text safe inside Markdown link brackets.
	 *
	 * @param string $text HTML or text.
	 * @return string
	 */
	public static function link_text( string $text ): string {
		return str_replace( array( '[', ']' ), '', self::plain( $text ) );
	}
}
