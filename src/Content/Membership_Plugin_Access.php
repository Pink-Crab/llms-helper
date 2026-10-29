<?php
/**
 * Content restrictions of the active membership plugins.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Access_Check;

/**
 * A post is restricted when any active membership plugin keeps a logged out visitor from it: Paid Memberships Pro,
 * Members, Content Control, Ultimate Member, Restrict Content, LifterLMS or WooCommerce Memberships.
 * Copies are public files, so a restricted post must not get one. Each plugin's own check runs as user 0,
 * since copies are built while an editor saves or from cron. The plugins' functions are only called once
 * active() has found them.
 */
class Membership_Plugin_Access implements Access_Check {

	/**
	 * Plugins checked, by key.
	 *
	 * @var list<string>
	 */
	public const PLUGINS = array( 'pmpro', 'members', 'content_control', 'ultimate_member', 'restrict_content', 'lifterlms', 'woocommerce_memberships' );

	/**
	 * {@inheritDoc}
	 */
	public function restricted( WP_Post $post ): bool {
		$user_id = get_current_user_id();
		wp_set_current_user( 0 );

		try {
			foreach ( self::PLUGINS as $plugin ) {
				if ( $this->active( $plugin ) && $this->check( $plugin, $post ) ) {
					return true;
				}
			}

			return false;
		} finally {
			wp_set_current_user( $user_id );
		}
	}

	/**
	 * Whether a membership plugin is active.
	 *
	 * @param string $plugin Plugin key.
	 * @return bool
	 */
	protected function active( string $plugin ): bool {
		switch ( $plugin ) {
			case 'pmpro':
				return function_exists( 'pmpro_has_membership_access' );
			case 'members':
				return function_exists( 'members_can_user_view_post' );
			case 'content_control':
				return function_exists( '\ContentControl\content_is_restricted' );
			case 'ultimate_member':
				return function_exists( 'UM' );
			case 'restrict_content':
				return function_exists( 'rcp_user_can_access' );
			case 'lifterlms':
				return function_exists( 'llms_is_post_restricted_by_membership' );
			case 'woocommerce_memberships':
				return function_exists( 'wc_memberships_is_post_content_restricted' );
			default:
				return false;
		}
	}

	/**
	 * Run one plugin's check, as the visitor.
	 *
	 * @param string  $plugin Plugin key.
	 * @param WP_Post $post   Post.
	 * @return bool
	 */
	protected function check( string $plugin, WP_Post $post ): bool {
		switch ( $plugin ) {
			case 'pmpro':
				return ! pmpro_has_membership_access( $post->ID );
			case 'members':
				return members_is_private_blog() || ! members_can_user_view_post( 0, $post->ID );
			case 'content_control':
				return \ContentControl\content_is_restricted( $post->ID );
			case 'ultimate_member':
				// Skip its per request cache, which may hold the editor's answer.
				return $this->ultimate_member_site_closed( $post ) || (bool) UM()->access()->is_restricted( $post->ID, true, true );
			case 'restrict_content':
				return ! rcp_user_can_access( 0, $post->ID );
			case 'lifterlms':
				return $this->lifterlms( $post );
			case 'woocommerce_memberships':
				return 'product' === $post->post_type
					? wc_memberships_is_product_viewing_restricted( $post->ID )
					: wc_memberships_is_post_content_restricted( $post->ID );
			default:
				return false;
		}
	}

	/**
	 * Ultimate Member's site for logged in users only, which still lets visitors reach the front page when
	 * allowed, and the pages listed as its redirect and exclusions.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function ultimate_member_site_closed( WP_Post $post ): bool {
		$options = UM()->options();
		if ( 2 !== (int) $options->get( 'accessible' ) ) {
			return false;
		}

		if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID ) {
			return ! $options->get( 'home_page_accessible' );
		}

		$open = array_merge( array( $options->get( 'access_redirect' ) ), (array) $options->get( 'access_exclude_uris' ) );
		$open = array_map(
			static function ( $url ): string {
				return untrailingslashit( trim( (string) $url ) );
			},
			$open
		);

		return ! in_array( untrailingslashit( (string) get_permalink( $post ) ), $open, true );
	}

	/**
	 * LifterLMS: a site wide or post membership, or a lesson that is not free. Its llms_page_restricted()
	 * only applies these on the post's own page, so they are checked directly.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function lifterlms( WP_Post $post ): bool {
		if ( llms_is_post_restricted_by_sitewide_membership( $post->ID ) || llms_is_post_restricted_by_membership( $post->ID ) ) {
			return true;
		}

		return 'lesson' === $post->post_type && ! ( new \LLMS_Lesson( $post->ID ) )->is_free();
	}
}
