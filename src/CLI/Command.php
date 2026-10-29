<?php
/**
 * WP-CLI commands.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\CLI;

use PinkCrab\LLMs_Helper\Content\Builder;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Content\Index_Builder;

/**
 * Builds Markdown copies and llms.txt.
 */
class Command {

	/**
	 * Set the builders.
	 *
	 * @param Builder       $builder Builds the selected post types.
	 * @param Index_Builder $index   Writes llms.txt.
	 * @param Copy_Builder  $copies  Builds one post's copy.
	 */
	public function __construct(
		protected Builder $builder,
		protected Index_Builder $index,
		protected Copy_Builder $copies
	) {}

	/**
	 * Build the copies of the selected post types, then llms.txt. With post IDs, build only those posts.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Leave out to build every post of the selected post types.
	 *
	 * ## EXAMPLES
	 *
	 *     wp llms-helper build
	 *     wp llms-helper build 12 34
	 *
	 * @param list<string> $args Post IDs.
	 * @return void
	 */
	public function build( array $args = array() ): void {
		foreach ( $args as $id ) {
			$written = $this->copies->build( (int) $id );
			\WP_CLI::log( sprintf( $written ? 'Post %d: copy written.' : 'Post %d: no copy.', (int) $id ) );
		}
		if ( array() !== $args ) {
			return;
		}

		$built = $this->builder->all(
			static function ( int $after, int $built ): void {
				\WP_CLI::log( sprintf( 'Up to post %d: %d copies built.', $after, $built ) );
			}
		);

		\WP_CLI::success( sprintf( '%d copies built, llms.txt built.', $built ) );
	}

	/**
	 * Build llms.txt only.
	 *
	 * ## EXAMPLES
	 *
	 *     wp llms-helper index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->index->build();

		\WP_CLI::success( 'llms.txt built.' );
	}
}
