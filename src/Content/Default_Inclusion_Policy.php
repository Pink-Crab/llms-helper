<?php
/**
 * Default inclusion policy.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Interfaces\Access_Check;
use PinkCrab\LLMs_Helper\Interfaces\Noindex_Check;
use PinkCrab\LLMs_Helper\Interfaces\Inclusion_Policy;

/**
 * Published, public, not password protected, of an included type, not excluded, not noindex, open to logged out
 * visitors, with pretty permalinks.
 */
class Default_Inclusion_Policy implements Inclusion_Policy {

	/**
	 * Post meta set when a post is excluded.
	 *
	 * @var string
	 */
	public const META_EXCLUDE = '_llms_helper_exclude';

	/**
	 * Set the dependencies.
	 *
	 * @param Settings      $settings Settings.
	 * @param Noindex_Check $noindex  Whether a post is marked noindex.
	 * @param Access_Check  $access   Whether a visitor is kept from a post.
	 */
	public function __construct(
		protected Settings $settings,
		protected Noindex_Check $noindex,
		protected Access_Check $access
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function includes( WP_Post $post ): bool {
		$included = 'publish' === $post->post_status
			&& '' === $post->post_password
			&& $this->settings->includes_type( $post->post_type )
			&& is_post_type_viewable( $post->post_type )
			&& ! get_post_meta( $post->ID, self::META_EXCLUDE, true )
			&& ! in_array( $post->ID, $this->noindex_shop_page_ids(), true )
			&& ! $this->hidden_product( $post )
			&& ! $this->coming_soon( $post )
			&& '' !== (string) get_option( 'permalink_structure' )
			&& ! $this->noindex->noindex( $post )
			&& ! $this->access->restricted( $post );

		/**
		 * Filter whether a post gets a Markdown copy.
		 *
		 * @param bool    $included Whether the post is included.
		 * @param WP_Post $post     Post.
		 */
		return (bool) apply_filters( 'llms_helper_include_post', $included, $post );
	}

	/**
	 * A WooCommerce product hidden from the shop or from search results.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function hidden_product( WP_Post $post ): bool {
		return 'product' === $post->post_type
			&& taxonomy_exists( 'product_visibility' )
			&& has_term( array( 'exclude-from-search', 'exclude-from-catalog' ), 'product_visibility', $post );
	}

	/**
	 * WooCommerce's coming soon mode: the whole site, or only its store pages and products.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function coming_soon( WP_Post $post ): bool {
		if ( ! function_exists( 'wc_get_page_id' ) || 'yes' !== get_option( 'woocommerce_coming_soon' ) ) {
			return false;
		}

		if ( 'yes' !== get_option( 'woocommerce_store_pages_only' ) || 'product' === $post->post_type ) {
			return true;
		}

		// The store pages WooCommerce's WCAdminHelper::is_current_page_store_page() checks.
		$store_pages = array(
			'shop'        => wc_get_page_id( 'shop' ),
			'cart'        => wc_get_page_id( 'cart' ),
			'checkout'    => wc_get_page_id( 'checkout' ),
			'terms'       => function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : 0,
			'coming_soon' => wc_get_page_id( 'coming_soon' ),
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook.
		$store_pages = (array) apply_filters( 'woocommerce_store_pages', $store_pages );

		return in_array( $post->ID, array_map( 'intval', $store_pages ), true );
	}

	/**
	 * WooCommerce's cart, checkout and my account pages, which WooCommerce marks noindex.
	 *
	 * @return list<int> Empty without WooCommerce.
	 */
	protected function noindex_shop_page_ids(): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return array();
		}

		return array(
			(int) wc_get_page_id( 'cart' ),
			(int) wc_get_page_id( 'checkout' ),
			(int) wc_get_page_id( 'myaccount' ),
		);
	}
}
