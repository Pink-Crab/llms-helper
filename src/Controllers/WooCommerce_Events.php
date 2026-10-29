<?php
/**
 * Keeps product copies in step with WooCommerce saves.
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
use PinkCrab\LLMs_Helper\Content\Copy_Builder;

/**
 * WooCommerce writes a product's terms, visibility and meta after wp_insert_post, and skips it when only
 * those change, so the copy is built again once WooCommerce has finished saving.
 */
class WooCommerce_Events implements Hookable {

	/**
	 * Set the copy builder.
	 *
	 * @param Copy_Builder $copies Copy builder.
	 */
	public function __construct(
		protected Copy_Builder $copies
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( 'woocommerce_new_product', array( $this, 'product_saved' ) );
		$loader->action( 'woocommerce_update_product', array( $this, 'product_saved' ) );
	}

	/**
	 * Build or remove the product's copy.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function product_saved( int $product_id ): void {
		$post = get_post( $product_id );
		if ( $post instanceof WP_Post && $this->copies->tracks( $post ) ) {
			$this->copies->build( $product_id );
		}
	}
}
