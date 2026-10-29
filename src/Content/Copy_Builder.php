<?php
/**
 * Builds and removes the copy of a post.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Interfaces\Cache_Purger;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Formatter;
use PinkCrab\LLMs_Helper\Interfaces\Inclusion_Policy;
use PinkCrab\LLMs_Helper\Interfaces\Description_Source;

/**
 * Writes a post's copy to the store, tracks its path in post meta, and removes it when the post no longer qualifies.
 */
class Copy_Builder {

	/**
	 * Post meta holding the path of the post's copy.
	 *
	 * @var string
	 */
	public const META_FILE = '_llms_helper_file';

	/**
	 * Post meta holding what the copy was built from: the post's modified time and the plugin version.
	 *
	 * @var string
	 */
	public const META_BUILT = '_llms_helper_built';

	/**
	 * Post meta holding the post's meta description when its copy was built, for llms.txt.
	 *
	 * @var string
	 */
	public const META_DESCRIPTION = '_llms_helper_description';

	/**
	 * Set the dependencies.
	 *
	 * @param Copy_Formatter     $formatter    Produces the copy's contents.
	 * @param Copy_Store         $store        Where copies are kept.
	 * @param Url_Map            $urls         URL mapping.
	 * @param Inclusion_Policy   $policy       Which posts get a copy.
	 * @param Settings           $settings     Settings.
	 * @param Scheduler          $scheduler    Background jobs.
	 * @param Cache_Purger       $purger       Clears cached responses.
	 * @param Description_Source $descriptions Meta descriptions.
	 */
	public function __construct(
		protected Copy_Formatter $formatter,
		protected Copy_Store $store,
		protected Url_Map $urls,
		protected Inclusion_Policy $policy,
		protected Settings $settings,
		protected Scheduler $scheduler,
		protected Cache_Purger $purger,
		protected Description_Source $descriptions
	) {}

	/**
	 * Whether saves of this post need handling: its type is included, or it has an old copy to remove.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public function tracks( WP_Post $post ): bool {
		return $this->settings->includes_type( $post->post_type ) || '' !== $this->copy_path( $post->ID );
	}

	/**
	 * Path of the post's copy.
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty when it has none.
	 */
	public function copy_path( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_FILE, true );
	}

	/**
	 * Write the post's copy, or remove it when the post no longer qualifies.
	 *
	 * @param int  $post_id      Post ID.
	 * @param bool $only_changed Skip the write when the copy is already up to date.
	 * @return bool Whether a copy was written.
	 */
	public function build( int $post_id, bool $only_changed = false ): bool {
		$post = get_post( $post_id );
		$path = $post instanceof WP_Post && $this->policy->includes( $post )
			? $this->urls->copy_path( (string) get_permalink( $post ) )
			: '';

		if ( '' === $path || ! $post instanceof WP_Post ) {
			$this->remove( $post_id );
			return false;
		}

		if ( $only_changed && $this->is_current( $post, $path ) ) {
			return false;
		}

		if ( ! $this->store->write( $path, $this->formatter->format( $post ) ) ) {
			return false;
		}

		// The permalink moved: drop the copy at the old path.
		$old = $this->copy_path( $post_id );
		if ( '' !== $old && $old !== $path ) {
			$this->store->delete( $old );
			$this->purge( $old );
		}

		update_post_meta( $post_id, self::META_FILE, $path );
		update_post_meta( $post_id, self::META_BUILT, $this->built_from( $post ) );

		$description = $this->descriptions->post( $post );
		if ( '' !== $description ) {
			update_post_meta( $post_id, self::META_DESCRIPTION, $description );
		} else {
			delete_post_meta( $post_id, self::META_DESCRIPTION );
		}

		$this->purge( $path );
		$this->scheduler->queue_index();

		return true;
	}

	/**
	 * Whether the post's copy is at this path, exists, and was built from the post as it is now.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $path Copy path for the current permalink.
	 * @return bool
	 */
	public function is_current( WP_Post $post, string $path ): bool {
		return $path === $this->copy_path( $post->ID )
			&& $this->built_from( $post ) === get_post_meta( $post->ID, self::META_BUILT, true )
			&& $this->store->exists( $path );
	}

	/**
	 * What a copy is built from: the post's modified time and the plugin version.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	protected function built_from( WP_Post $post ): string {
		return $post->post_modified_gmt . '|' . PINKCRAB_LLMS_HELPER_VERSION;
	}

	/**
	 * Remove the post's copy.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function remove( int $post_id ): void {
		$old = $this->copy_path( $post_id );
		if ( '' === $old ) {
			return;
		}

		$this->store->delete( $old );
		delete_post_meta( $post_id, self::META_FILE );
		delete_post_meta( $post_id, self::META_BUILT );
		delete_post_meta( $post_id, self::META_DESCRIPTION );
		$this->purge( $old );
		$this->scheduler->queue_index();
	}

	/**
	 * Purge the copy URL and, when the store has one, the store's public URL.
	 *
	 * @param string $path Copy path.
	 * @return void
	 */
	protected function purge( string $path ): void {
		$this->purger->purge( array_values( array_filter( array( $this->urls->url_for_path( $path ), $this->store->public_url( $path ) ) ) ) );
	}
}
