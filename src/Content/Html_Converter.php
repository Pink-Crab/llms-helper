<?php
/**
 * HTML to Markdown converter.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP's DOM API.

use DOMNode;
use DOMText;
use DOMElement;
use DOMDocument;
use PinkCrab\LLMs_Helper\Interfaces\Markdown_Converter;

/**
 * Converts rendered post HTML to Markdown, keeping links, images, embeds, lists, tables and code.
 */
class Html_Converter implements Markdown_Converter {

	/**
	 * Elements dropped along with their contents.
	 *
	 * @var list<string>
	 */
	public const SKIP = array( 'script', 'style', 'noscript', 'template', 'svg', 'form', 'input', 'select', 'textarea', 'button', 'canvas', 'link', 'meta', 'title', 'head', 'object', 'map' );

	/**
	 * Elements treated as inline text. Anything else is a block.
	 *
	 * @var list<string>
	 */
	public const INLINE = array( 'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'embed', 'font', 'i', 'iframe', 'img', 'ins', 'kbd', 'label', 'mark', 'picture', 'q', 's', 'samp', 'small', 'source', 'span', 'strike', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'video', 'audio', 'wbr' );

	/**
	 * Attributes lazy loaders put the real URL in.
	 *
	 * @var list<string>
	 */
	public const LAZY_ATTRIBUTES = array( 'data-src', 'data-lazy-src', 'data-original' );

	/**
	 * URL relative links and images resolve against.
	 *
	 * @var string
	 */
	protected string $base = '';

	/**
	 * Elements dropped for the current conversion.
	 *
	 * @var list<string>
	 */
	protected array $skip = self::SKIP;

	/**
	 * Inline elements for the current conversion.
	 *
	 * @var list<string>
	 */
	protected array $inline = self::INLINE;

	/**
	 * {@inheritDoc}
	 */
	public function convert( string $html, string $base_url ): string {
		$this->base = $base_url;

		/**
		 * Filter the elements dropped along with their contents.
		 *
		 * @param list<string> $tags Tag names.
		 */
		$this->skip = $this->tag_list( apply_filters( 'llms_helper_skip_elements', self::SKIP ), self::SKIP );

		/**
		 * Filter the elements treated as inline text.
		 *
		 * @param list<string> $tags Tag names.
		 */
		$this->inline = $this->tag_list( apply_filters( 'llms_helper_inline_elements', self::INLINE ), self::INLINE );

		/**
		 * Filter the HTML before it is converted.
		 *
		 * @param string $html     Rendered HTML.
		 * @param string $base_url Page URL.
		 */
		$html = (string) apply_filters( 'llms_helper_convert_html', $html, $base_url );

		$markdown = '' === trim( $html ) ? '' : $this->document_markdown( $html );

		/**
		 * Filter the Markdown after conversion.
		 *
		 * @param string $markdown Markdown.
		 * @param string $html     HTML it was converted from.
		 * @param string $base_url Page URL.
		 */
		return (string) apply_filters( 'llms_helper_convert_markdown', $markdown, $html, $base_url );
	}

	/**
	 * Render a node's children as blocks, grouping runs of inline content into paragraphs.
	 *
	 * @param DOMNode $node      Parent node.
	 * @param string  $separator Between blocks.
	 * @return string
	 */
	public function children_blocks( DOMNode $node, string $separator = "\n\n" ): string {
		$parts  = array();
		$inline = '';

		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && ! in_array( strtolower( $child->tagName ), $this->inline, true ) ) {
				$parts[] = $this->tidy( $inline );
				$parts[] = $this->block( $child );
				$inline  = '';
				continue;
			}
			$inline .= $this->inline( $child );
		}
		$parts[] = $this->tidy( $inline );

		$parts = array_filter(
			array_map(
				static function ( string $part ): string {
					return trim( $part, "\n" );
				},
				$parts
			),
			static function ( string $part ): bool {
				return '' !== trim( $part );
			}
		);

		return implode( $separator, $parts );
	}

	/**
	 * Concatenate the inline rendering of each child.
	 *
	 * @param DOMNode $node Parent node.
	 * @return string
	 */
	public function children_inline( DOMNode $node ): string {
		$text = '';
		foreach ( $node->childNodes as $child ) {
			$text .= $this->inline( $child );
		}

		return $text;
	}

	/**
	 * Parse the HTML and convert the whole fragment.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	protected function document_markdown( string $html ): string {
		$doc      = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $doc->getElementsByTagName( 'div' )->item( 0 );
		if ( null === $root ) {
			return '';
		}

		$markdown = $this->children_blocks( $root );

		return '' === $markdown ? '' : $markdown . "\n";
	}

	/**
	 * Lower-case tag names from a filtered value, or the defaults when it is not an array.
	 *
	 * @param mixed        $tags     Filtered value.
	 * @param list<string> $defaults Default tags.
	 * @return list<string>
	 */
	protected function tag_list( $tags, array $defaults ): array {
		return is_array( $tags ) ? array_values( array_map( 'strtolower', array_map( 'strval', $tags ) ) ) : $defaults;
	}

	/**
	 * Markdown from the llms_helper_convert_element filter, if a callback supplies it.
	 *
	 * @param DOMElement       $el      Element.
	 * @param 'block'|'inline' $context How the element is being converted.
	 * @return string|null Null to use the default conversion.
	 */
	protected function custom( DOMElement $el, string $context ): ?string {
		/**
		 * Filter the Markdown for one element. Return a string to replace the default conversion.
		 *
		 * @param string|null      $markdown  Null for the default conversion.
		 * @param DOMElement       $el        Element.
		 * @param 'block'|'inline' $context   How the element is being converted.
		 * @param Html_Converter   $converter This converter, for children_blocks() and children_inline().
		 */
		$markdown = apply_filters( 'llms_helper_convert_element', null, $el, $context, $this );

		return is_string( $markdown ) ? $markdown : null;
	}

	/**
	 * Render one block element.
	 *
	 * @param DOMElement $el Element.
	 * @return string
	 */
	protected function block( DOMElement $el ): string {
		$custom = $this->custom( $el, 'block' );
		if ( null !== $custom ) {
			return $custom;
		}

		$tag = strtolower( $el->tagName );

		if ( in_array( $tag, $this->skip, true ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = str_replace( "\n", ' ', $this->tidy( $this->children_inline( $el ) ) );
				return '' === $text ? '' : str_repeat( '#', (int) $tag[1] ) . ' ' . $text;

			case 'p':
			case 'dd':
			case 'figcaption':
				return $this->tidy( $this->children_inline( $el ) );

			case 'dt':
			case 'summary':
				$text = $this->tidy( $this->children_inline( $el ) );
				return '' === $text ? '' : '**' . $text . '**';

			case 'hr':
				return '---';

			case 'pre':
				return $this->code_block( $el );

			case 'blockquote':
				return $this->quote( $this->children_blocks( $el ) );

			case 'ul':
			case 'ol':
				return $this->list_items( $el );

			case 'table':
				return $this->table( $el );

			default:
				return $this->children_blocks( $el );
		}
	}

	/**
	 * Render one node as inline Markdown.
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	protected function inline( DOMNode $node ): string {
		if ( $node instanceof DOMText ) {
			return (string) preg_replace( '/\s+/u', ' ', (string) $node->nodeValue );
		}

		if ( ! $node instanceof DOMElement ) {
			return '';
		}

		$custom = $this->custom( $node, 'inline' );
		if ( null !== $custom ) {
			return $custom;
		}

		$tag = strtolower( $node->tagName );

		if ( in_array( $tag, $this->skip, true ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'br':
				return "\n";

			case 'img':
				return $this->image( $node );

			case 'a':
				return $this->link( $node );

			case 'strong':
			case 'b':
				return $this->wrap( $this->children_inline( $node ), '**' );

			case 'em':
			case 'i':
				return $this->wrap( $this->children_inline( $node ), '*' );

			case 'del':
			case 's':
			case 'strike':
				return $this->wrap( $this->children_inline( $node ), '~~' );

			case 'code':
			case 'kbd':
			case 'samp':
				return $this->code_span( (string) $node->textContent );

			case 'iframe':
			case 'embed':
				return $this->media_link( $node, __( 'Embedded content', 'llms-helper' ) );

			case 'video':
				return $this->media_link( $node, __( 'Video', 'llms-helper' ) );

			case 'audio':
				return $this->media_link( $node, __( 'Audio', 'llms-helper' ) );

			case 'source':
				return '';
		}

		// A block inside inline content (a <p> in a <td>) keeps its own line.
		if ( ! in_array( $tag, $this->inline, true ) ) {
			return "\n" . $this->children_inline( $node ) . "\n";
		}

		return $this->children_inline( $node );
	}

	/**
	 * Collapse spaces and blank lines in inline text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected function tidy( string $text ): string {
		$text = (string) preg_replace( '/[ \t]+/', ' ', $text );
		$text = (string) preg_replace( '/ *\n */', "\n", $text );
		$text = (string) preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Wrap text in an emphasis marker, keeping outer spaces outside it.
	 *
	 * @param string $text   Text.
	 * @param string $marker Marker.
	 * @return string
	 */
	protected function wrap( string $text, string $marker ): string {
		$inner = trim( $text );
		if ( '' === $inner ) {
			return $text;
		}

		$lead  = preg_match( '/^\s/', $text ) ? ' ' : '';
		$trail = preg_match( '/\s$/', $text ) ? ' ' : '';

		return $lead . $marker . $inner . $marker . $trail;
	}

	/**
	 * Markdown link.
	 *
	 * @param DOMElement $el Anchor.
	 * @return string
	 */
	protected function link( DOMElement $el ): string {
		$text = str_replace( "\n", ' ', $this->tidy( $this->children_inline( $el ) ) );
		$href = $this->absolute( $el->getAttribute( 'href' ) );

		if ( '' === $href ) {
			return $text;
		}

		return '[' . ( '' !== $text ? $text : $href ) . '](' . $href . ')';
	}

	/**
	 * Markdown image.
	 *
	 * @param DOMElement $el Image.
	 * @return string
	 */
	protected function image( DOMElement $el ): string {
		$src = $this->absolute( $this->source_url( $el ) );
		if ( '' === $src ) {
			return '';
		}

		$alt = $this->tidy( str_replace( array( '[', ']' ), '', $el->getAttribute( 'alt' ) ) );

		return '![' . $alt . '](' . $src . ')';
	}

	/**
	 * Link to an iframe, embed, video or audio source.
	 *
	 * @param DOMElement $el    Element.
	 * @param string     $label Fallback link text.
	 * @return string
	 */
	protected function media_link( DOMElement $el, string $label ): string {
		$src = $this->source_url( $el );

		if ( '' === $src ) {
			foreach ( $el->getElementsByTagName( 'source' ) as $source ) {
				$src = $source->getAttribute( 'src' );
				if ( '' !== $src ) {
					break;
				}
			}
		}

		$src = $this->absolute( $src );
		if ( '' === $src ) {
			return '';
		}

		$title = $this->tidy( $el->getAttribute( 'title' ) );

		return ' [' . ( '' !== $title ? $title : $label ) . '](' . $src . ') ';
	}

	/**
	 * The element's src, or the lazy loader's real URL when src is empty or a placeholder.
	 *
	 * @param DOMElement $el Element.
	 * @return string
	 */
	protected function source_url( DOMElement $el ): string {
		$src = trim( $el->getAttribute( 'src' ) );

		if ( '' === $src || 0 === stripos( $src, 'data:' ) ) {
			foreach ( self::LAZY_ATTRIBUTES as $attribute ) {
				$lazy = trim( $el->getAttribute( $attribute ) );
				if ( '' !== $lazy ) {
					return $lazy;
				}
			}
		}

		return $src;
	}

	/**
	 * Absolute URL safe to put inside Markdown link brackets.
	 *
	 * @param string $url URL as written in the HTML.
	 * @return string Empty for script and data URLs.
	 */
	protected function absolute( string $url ): string {
		$url = trim( $url );

		if ( '' === $url || 0 === stripos( $url, 'javascript:' ) || 0 === stripos( $url, 'data:' ) ) {
			return '';
		}

		if ( str_starts_with( $url, '#' ) ) {
			$url = $this->base . $url;
		} elseif ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
			$url = \WP_Http::make_absolute_url( $url, $this->base );
		}

		return str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), $url );
	}

	/**
	 * Inline code span, widening the backtick fence past any backticks inside.
	 *
	 * @param string $text Code.
	 * @return string
	 */
	protected function code_span( string $text ): string {
		$text = str_replace( array( "\r", "\n" ), ' ', $text );
		if ( '' === trim( $text ) ) {
			return $text;
		}

		$ticks = str_repeat( '`', $this->longest_run( '/`+/', $text ) + 1 );
		$pad   = ( str_starts_with( $text, '`' ) || str_ends_with( $text, '`' ) ) ? ' ' : '';

		return $ticks . $pad . $text . $pad . $ticks;
	}

	/**
	 * Fenced code block, with the language from a language-* or lang-* class.
	 *
	 * @param DOMElement $pre Pre element.
	 * @return string
	 */
	protected function code_block( DOMElement $pre ): string {
		foreach ( iterator_to_array( $pre->getElementsByTagName( 'br' ) ) as $br ) {
			if ( null !== $br->parentNode && null !== $pre->ownerDocument ) {
				$br->parentNode->replaceChild( $pre->ownerDocument->createTextNode( "\n" ), $br );
			}
		}

		$code = rtrim( trim( (string) $pre->textContent, "\r\n" ) );
		if ( '' === $code ) {
			return '';
		}

		$class = $pre->getAttribute( 'class' );
		$first = $pre->getElementsByTagName( 'code' )->item( 0 );
		if ( $first instanceof DOMElement ) {
			$class .= ' ' . $first->getAttribute( 'class' );
		}
		$language = preg_match( '/\blang(?:uage)?-([\w+#-]+)/', $class, $match ) ? $match[1] : '';

		$fence = str_repeat( '`', max( 3, $this->longest_run( '/`{3,}/', $code ) + 1 ) );

		return $fence . $language . "\n" . $code . "\n" . $fence;
	}

	/**
	 * Length of the longest match of a pattern.
	 *
	 * @param string $pattern Regex.
	 * @param string $text    Text.
	 * @return int 0 when nothing matches.
	 */
	protected function longest_run( string $pattern, string $text ): int {
		preg_match_all( $pattern, $text, $runs );

		return $runs[0] ? max( array_map( 'strlen', $runs[0] ) ) : 0;
	}

	/**
	 * Prefix each line as a quote.
	 *
	 * @param string $text Quoted Markdown.
	 * @return string
	 */
	protected function quote( string $text ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}

		$lines = array_map(
			static function ( string $line ): string {
				return '' === $line ? '>' : '> ' . $line;
			},
			explode( "\n", $text )
		);

		return implode( "\n", $lines );
	}

	/**
	 * Markdown list. Item content, nested lists included, is indented to the item's text column.
	 *
	 * @param DOMElement $list_element Ul or ol.
	 * @return string
	 */
	protected function list_items( DOMElement $list_element ): string {
		$ordered = 'ol' === strtolower( $list_element->tagName );
		$number  = $ordered && $list_element->hasAttribute( 'start' ) ? (int) $list_element->getAttribute( 'start' ) : 1;
		$items   = array();

		foreach ( $list_element->childNodes as $item ) {
			if ( ! $item instanceof DOMElement || 'li' !== strtolower( $item->tagName ) ) {
				continue;
			}

			$marker = $ordered ? ( $number++ ) . '.' : '-';
			$indent = str_repeat( ' ', strlen( $marker ) + 1 );
			$lines  = explode( "\n", $this->children_blocks( $item, "\n" ) );

			foreach ( $lines as $i => $line ) {
				if ( 0 === $i ) {
					$lines[ $i ] = $marker . ' ' . $line;
				} elseif ( '' !== $line ) {
					$lines[ $i ] = $indent . $line;
				}
			}

			$items[] = rtrim( implode( "\n", $lines ) );
		}

		return implode( "\n", $items );
	}

	/**
	 * Markdown table. The first row is the header.
	 *
	 * @param DOMElement $table Table.
	 * @return string
	 */
	protected function table( DOMElement $table ): string {
		$rows = array();

		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof DOMElement && in_array( strtolower( $cell->tagName ), array( 'td', 'th' ), true ) ) {
					$cells[] = str_replace( array( '|', "\n" ), array( '\|', ' ' ), $this->tidy( $this->children_inline( $cell ) ) );
				}
			}
			if ( $cells ) {
				$rows[] = $cells;
			}
		}

		if ( ! $rows ) {
			return '';
		}

		$width = max( array_map( 'count', $rows ) );
		$lines = array();
		foreach ( $rows as $i => $cells ) {
			$lines[] = '| ' . implode( ' | ', array_pad( $cells, $width, '' ) ) . ' |';
			if ( 0 === $i ) {
				$lines[] = '|' . str_repeat( ' --- |', $width );
			}
		}

		$markdown = implode( "\n", $lines );
		$caption  = $table->getElementsByTagName( 'caption' )->item( 0 );

		return null !== $caption ? $this->tidy( $this->children_inline( $caption ) ) . "\n\n" . $markdown : $markdown;
	}
}
