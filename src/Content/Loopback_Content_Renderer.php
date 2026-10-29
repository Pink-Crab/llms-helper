<?php
/**
 * Renderer that renders the content in a request of its own, as a logged out visitor.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Content_Renderer;

/**
 * Copies are often built while an editor saves, in an admin or REST request. Plugins that decide once per
 * request who is viewing, or that only filter on the front end, would then let members only content into a
 * copy. So the post's own page is requested with a one time key, as a logged out visitor, and Render_Endpoint
 * renders the content there. If that request fails, the content is rendered in this request instead.
 */
class Loopback_Content_Renderer implements Content_Renderer {

	/**
	 * Query var carrying the one time key.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'llms_helper_render';

	/**
	 * Header Render_Endpoint marks its answer with.
	 *
	 * @var string
	 */
	public const HEADER = 'X-LLMs-Helper-Render';

	/**
	 * Seconds a key lasts.
	 *
	 * @var int
	 */
	public const KEY_TTL = 120;

	/**
	 * Set the renderer used in the visitor's request, and here when that request fails.
	 *
	 * @param Full_Content_Renderer $renderer Renderer.
	 */
	public function __construct(
		protected Full_Content_Renderer $renderer
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function render( WP_Post $post ): string {
		$html = $this->fetch( $post );

		return null !== $html ? $html : $this->renderer->render( $post );
	}

	/**
	 * Render in this request, which Render_Endpoint does in the visitor's request.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function render_here( WP_Post $post ): string {
		return $this->renderer->render( $post );
	}

	/**
	 * Name of the transient holding a key's post ID.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function transient( string $key ): string {
		return self::QUERY_VAR . '_' . $key;
	}

	/**
	 * The content as rendered in a request to the post's page, or null when that request fails.
	 *
	 * @param WP_Post $post Post.
	 * @return string|null
	 */
	protected function fetch( WP_Post $post ): ?string {
		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return null;
		}

		$key = strtolower( wp_generate_password( 32, false ) );
		set_transient( self::transient( $key ), $post->ID, self::KEY_TTL );

		/**
		 * Filter the request to the post's page, for sites that cannot reach their own public URL.
		 *
		 * @param array{url: string, args: array<string, mixed>} $request URL and wp_remote_get() arguments.
		 * @param WP_Post                                       $post    Post.
		 */
		$request = (array) apply_filters(
			'llms_helper_render_request',
			array(
				'url'  => add_query_arg( self::QUERY_VAR, $key, $permalink ),
				'args' => array(
					'timeout'     => 30,
					'redirection' => 0,
					'cookies'     => array(),
					'headers'     => array( 'Cache-Control' => 'no-cache' ),
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, as core's own requests to itself use.
					'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ),
				),
			),
			$post
		);

		$response = wp_remote_get( (string) ( $request['url'] ?? '' ), (array) ( $request['args'] ?? array() ) );

		// Render_Endpoint uses the key up, unless the request never reached it.
		delete_transient( self::transient( $key ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || '1' !== wp_remote_retrieve_header( $response, self::HEADER ) ) {
			return null;
		}

		return wp_remote_retrieve_body( $response );
	}
}
