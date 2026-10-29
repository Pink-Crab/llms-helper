<?php
/**
 * Markdown copy formatter.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use WP_Term;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Formatter;
use PinkCrab\LLMs_Helper\Interfaces\Content_Renderer;
use PinkCrab\LLMs_Helper\Interfaces\Markdown_Converter;
use PinkCrab\LLMs_Helper\Interfaces\Description_Source;

/**
 * Copy contents: YAML front matter, the title, then the rendered content converted to Markdown.
 */
class Markdown_Formatter implements Copy_Formatter {

	/**
	 * Post meta holding Markdown written for the copy in place of the converted content.
	 *
	 * @var string
	 */
	public const META_MARKDOWN = '_llms_helper_markdown';

	/**
	 * Set the renderer, converter and description source.
	 *
	 * @param Content_Renderer   $renderer     Renders the post content to HTML.
	 * @param Markdown_Converter $converter    HTML to Markdown converter.
	 * @param Description_Source $descriptions Meta descriptions.
	 */
	public function __construct(
		protected Content_Renderer $renderer,
		protected Markdown_Converter $converter,
		protected Description_Source $descriptions
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function format( WP_Post $post ): string {
		return $this->format_front_matter( $this->front_matter( $post ) )
			. "\n# " . $this->title( $post ) . "\n\n"
			. $this->body( $post );
	}

	/**
	 * The post's own Markdown when it has one, else its rendered content converted.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function body( WP_Post $post ): string {
		$markdown = trim( (string) get_post_meta( $post->ID, self::META_MARKDOWN, true ) );
		if ( '' !== $markdown ) {
			return $markdown . "\n";
		}

		return $this->converter->convert( $this->renderer->render( $post ), (string) get_permalink( $post ) );
	}

	/**
	 * Front matter values.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public function front_matter( WP_Post $post ): array {
		$data = array(
			'title'     => $this->title( $post ),
			'url'       => (string) get_permalink( $post ),
			'type'      => $post->post_type,
			'published' => (string) get_post_time( 'c', true, $post ),
			'modified'  => (string) get_post_modified_time( 'c', true, $post ),
		);

		$author = post_type_supports( $post->post_type, 'author' )
			? Text::plain( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) )
			: '';
		if ( '' !== $author ) {
			$data['author'] = $author;
		}

		$description = $this->descriptions->post( $post );
		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		if ( '' !== trim( $post->post_excerpt ) ) {
			$data['excerpt'] = Text::plain( $post->post_excerpt );
		}

		$image = get_the_post_thumbnail_url( $post, 'full' );
		if ( is_string( $image ) && '' !== $image ) {
			$data['featured_image'] = $image;
		}

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			$terms = $taxonomy->public ? get_the_terms( $post, $taxonomy->name ) : false;
			if ( is_array( $terms ) && array() !== $terms ) {
				$data[ $taxonomy->name ] = array_values(
					array_map(
						static function ( WP_Term $term ): string {
							return Text::plain( $term->name );
						},
						$terms
					)
				);
			}
		}

		/**
		 * Filter the front matter written at the top of each copy.
		 *
		 * @param array<string, mixed> $data Front matter.
		 * @param WP_Post              $post Post.
		 */
		return (array) apply_filters( 'llms_helper_front_matter', $data, $post );
	}

	/**
	 * Plain title, or the slug when the post has no title.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function title( WP_Post $post ): string {
		$title = Text::plain( get_the_title( $post ) );

		return '' !== $title ? $title : $post->post_name;
	}

	/**
	 * YAML front matter. Values are JSON encoded, which YAML reads as quoted strings and lists.
	 *
	 * @param array<string, mixed> $data Front matter values.
	 * @return string
	 */
	public function format_front_matter( array $data ): string {
		$lines = array( '---' );
		foreach ( $data as $key => $value ) {
			$lines[] = sanitize_key( (string) $key ) . ': ' . wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$lines[] = '---';

		return implode( "\n", $lines ) . "\n";
	}
}
