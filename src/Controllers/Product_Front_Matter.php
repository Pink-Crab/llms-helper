<?php
/**
 * WooCommerce product details in copy front matter.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Controllers;

use WP_Post;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;

/**
 * Adds a product's price, currency, SKU and stock status to its copy's front matter, as a logged out visitor
 * would be shown them.
 */
class Product_Front_Matter implements Hookable {

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->filter( 'llms_helper_front_matter', array( $this, 'add' ), 10, 2 );
	}

	/**
	 * Add the product's details.
	 *
	 * @param array<string, mixed> $data Front matter.
	 * @param WP_Post              $post Post.
	 * @return array<string, mixed>
	 */
	public function add( array $data, WP_Post $post ): array {
		if ( 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return $data;
		}

		$user_id = get_current_user_id();
		wp_set_current_user( 0 );

		try {
			$product = wc_get_product( $post->ID );

			return $product ? array_merge( $data, $this->details( $product ) ) : $data;
		} finally {
			wp_set_current_user( $user_id );
		}
	}

	/**
	 * The product's details, leaving out empty ones.
	 *
	 * @param mixed $product WooCommerce's WC_Product.
	 * @return array<string, string>
	 */
	protected function details( $product ): array {
		$details = array( 'price' => (string) $product->get_price() );

		if ( $product->is_type( 'variable' ) ) {
			$details['price_min'] = (string) $product->get_variation_price( 'min' );
			$details['price_max'] = (string) $product->get_variation_price( 'max' );
		} elseif ( $product->is_on_sale() ) {
			$details['regular_price'] = (string) $product->get_regular_price();
		}

		$details['currency']     = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		$details['sku']          = (string) $product->get_sku();
		$details['stock_status'] = (string) $product->get_stock_status();

		return array_filter(
			$details,
			static function ( string $value ): bool {
				return '' !== $value;
			}
		);
	}
}
