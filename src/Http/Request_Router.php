<?php
/**
 * Routes copy and llms.txt requests.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Http;

use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;

/**
 * Decides how to answer a request for a copy or llms.txt. A saved file is redirected to when redirect mode
 * is on and the store has a public URL, otherwise sent from PHP. A copy URL with no copy follows the
 * missing copy setting.
 */
class Request_Router {

	/**
	 * Set the dependencies.
	 *
	 * @param Copy_Store $store    Where copies are kept.
	 * @param Url_Map    $urls     URL mapping.
	 * @param Settings   $settings Settings.
	 */
	public function __construct(
		protected Copy_Store $store,
		protected Url_Map $urls,
		protected Settings $settings
	) {}

	/**
	 * Route a request.
	 *
	 * @param array<string, mixed> $server Server values, as $_SERVER.
	 * @return Route
	 */
	public function route( array $server ): Route {
		$method = strtoupper( is_string( $server['REQUEST_METHOD'] ?? null ) ? $server['REQUEST_METHOD'] : 'GET' );
		$uri    = is_string( $server['REQUEST_URI'] ?? null ) ? $server['REQUEST_URI'] : '';

		if ( '' === $uri || ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return Route::none();
		}

		$parts      = explode( '?', $uri, 2 );
		$raw_path   = $parts[0];
		$query      = $parts[1] ?? '';
		$index_path = $this->urls->index_path();
		$is_copy    = $this->urls->is_copy_request( $raw_path );

		if ( ! $is_copy && ! str_ends_with( $raw_path, basename( $index_path ) ) ) {
			return Route::none();
		}

		$path     = rawurldecode( $raw_path );
		$is_index = $index_path === $path;

		if ( ! $is_copy && ! $is_index ) {
			return Route::none();
		}

		if ( $this->store->exists( $path ) ) {
			$route = $this->serve( $path, $is_index ? 'text/plain' : 'text/markdown', 'HEAD' !== $method );

			// llms.txt is for agents, not search results, unless the site lets search engines index it.
			return $is_index && ! $this->settings->index_llms_txt() ? $route->with_noindex() : $route;
		}

		if ( ! $is_copy ) {
			return Route::none();
		}

		switch ( $this->settings->missing_copy() ) {
			case '404':
				return Route::status( 404 );
			case '410':
				return Route::status( 410 );
			default:
				return Route::render_page( $this->urls->page_uri( $raw_path ) . ( '' !== $query ? '?' . $query : '' ) );
		}
	}

	/**
	 * Redirect to a saved file, or send it from PHP.
	 *
	 * @param string $path         Decoded path.
	 * @param string $content_type Media type.
	 * @param bool   $with_body    False for HEAD requests.
	 * @return Route
	 */
	protected function serve( string $path, string $content_type, bool $with_body ): Route {
		$url = $this->settings->redirect() ? $this->store->public_url( $path ) : '';
		if ( '' !== $url ) {
			return Route::redirect( $url );
		}

		$file = $this->store->local_file( $path );

		return '' !== $file
			? Route::send_file( $file, $content_type, $with_body )
			: Route::send_contents( $this->store->read( $path ), $content_type, $with_body );
	}
}
