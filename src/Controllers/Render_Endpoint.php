<?php
/**
 * Answers Loopback_Content_Renderer's requests.
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
use PinkCrab\LLMs_Helper\Content\Loopback_Content_Renderer;

/**
 * On a post's own page, requested with a valid one time key, sends just the rendered content. The request
 * carries no cookies, so every plugin renders it for a logged out visitor on the front end.
 */
class Render_Endpoint implements Hookable {

	/**
	 * Set the renderer.
	 *
	 * @param Loopback_Content_Renderer $renderer Renderer.
	 */
	public function __construct(
		protected Loopback_Content_Renderer $renderer
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
	}

	/**
	 * Send the content when this is a render request.
	 *
	 * @infection-ignore-all Sends headers and exits, which cannot run inside the test suite.
	 * @return void
	 */
	public function maybe_render(): void {
		$post = $this->requested_post();
		if ( null === $post ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( Loopback_Content_Renderer::HEADER . ': 1' );
		header( 'X-Robots-Tag: noindex' );
		echo $this->render( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The post content, as the_content gives it.
		exit;
	}

	/**
	 * The post this request renders: its key must be valid, and this must be that post's own page. A key works once.
	 *
	 * @return WP_Post|null
	 */
	public function requested_post(): ?WP_Post {
		$key = isset( $_GET[ Loopback_Content_Renderer::QUERY_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ Loopback_Content_Renderer::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- The key is the check.
		if ( '' === $key ) {
			return null;
		}

		$transient = Loopback_Content_Renderer::transient( $key );
		$post_id   = (int) get_transient( $transient );
		delete_transient( $transient );

		if ( 0 === $post_id || ! is_singular() || get_queried_object_id() !== $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Render the post inside the main query's loop, as a theme's template does.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function render( WP_Post $post ): string {
		if ( have_posts() ) {
			the_post();
		}

		return $this->renderer->render_here( $post );
	}
}
