<?php
/**
 * Generator for llms.txt, listing this plugin's copies.
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
use PinkCrab\LLMs_Helper\Interfaces\Index_Generator;
use PinkCrab\LLMs_Helper\Interfaces\Description_Source;

/**
 * Builds llms.txt from this plugin's copies: the site name, summary, notes, then one section per post type,
 * each link noted with the post's meta description or excerpt.
 */
class Copy_Index_Generator implements Index_Generator {

	/**
	 * Rows per query when listing copies.
	 *
	 * @var int
	 */
	public const PAGE_SIZE = 1000;

	/**
	 * Set the dependencies.
	 *
	 * @param Settings           $settings     Settings.
	 * @param Url_Map            $urls         URL mapping.
	 * @param Description_Source $descriptions Meta descriptions.
	 */
	public function __construct(
		protected Settings $settings,
		protected Url_Map $urls,
		protected Description_Source $descriptions
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function generate(): string {
		$summary = $this->settings->summary();
		if ( '' === $summary ) {
			$summary = $this->descriptions->site();
		}
		if ( '' === $summary ) {
			$summary = Text::plain( (string) get_bloginfo( 'description' ) );
		}

		$lines = array( '# ' . Text::plain( (string) get_bloginfo( 'name' ) ), '' );

		if ( '' !== $summary ) {
			array_push( $lines, '> ' . $summary, '' );
		}

		if ( '' !== $this->settings->notes() ) {
			array_push( $lines, $this->settings->notes(), '' );
		}

		foreach ( $this->settings->post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );
			$links  = null !== $object ? $this->links( $post_type ) : array();
			if ( null === $object || array() === $links ) {
				continue;
			}

			array_push( $lines, '## ' . Text::plain( (string) $object->labels->name ), '', ...$links );
			$lines[] = '';
		}

		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * List lines for one post type, read straight from the tables so large sites do not load every post.
	 *
	 * @param string $post_type Post type.
	 * @return list<string>
	 */
	protected function links( string $post_type ): array {
		global $wpdb;

		$order  = is_post_type_hierarchical( $post_type ) ? 'p.menu_order ASC, p.post_title ASC' : 'p.post_date DESC';
		$lines  = array();
		$offset = 0;

		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order is one of two fixed strings.
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.post_title, p.post_name, p.post_excerpt, pm.meta_value AS path, pd.meta_value AS description
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
					LEFT JOIN {$wpdb->postmeta} pd ON pd.post_id = p.ID AND pd.meta_key = %s
					WHERE p.post_type = %s AND p.post_status = 'publish'
					ORDER BY {$order}, p.ID ASC
					LIMIT %d OFFSET %d",
					Copy_Builder::META_FILE,
					Copy_Builder::META_DESCRIPTION,
					$post_type,
					self::PAGE_SIZE,
					$offset
				)
			);
			// phpcs:enable

			foreach ( $rows as $row ) {
				$title   = Text::link_text( (string) $row->post_title );
				$line    = '- [' . ( '' !== $title ? $title : (string) $row->post_name ) . '](' . $this->urls->url_for_path( (string) $row->path ) . ')';
				$note    = Text::plain( (string) ( $row->description ?? '' ) );
				$note    = '' !== $note ? $note : Text::plain( (string) $row->post_excerpt );
				$lines[] = '' !== $note ? $line . ': ' . $note : $line;
			}

			$offset += self::PAGE_SIZE;
			$fetched = count( $rows );
		} while ( self::PAGE_SIZE === $fetched );

		return $lines;
	}
}
