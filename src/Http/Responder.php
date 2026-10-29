<?php
/**
 * Carries out a route.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Http;

/**
 * Sends the response a route describes. Sending headers and exiting cannot run inside the test suite.
 *
 * @infection-ignore-all
 */
class Responder {

	/**
	 * Carry out a route.
	 *
	 * @param Route $route Route.
	 * @return void
	 */
	public function respond( Route $route ): void {
		switch ( $route->kind() ) {
			case Route::REDIRECT:
				header( 'Location: ' . $route->target(), true, 301 );
				header( 'X-Redirect-By: llms-helper' );
				$this->terminate();
				break;

			case Route::SEND_FILE:
				$this->send_headers( $route, (int) filesize( $route->target() ), (int) filemtime( $route->target() ) );
				if ( $route->with_body() ) {
					readfile( $route->target() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				$this->terminate();
				break;

			case Route::SEND_CONTENTS:
				$this->send_headers( $route, strlen( $route->target() ), 0 );
				if ( $route->with_body() ) {
					echo $route->target(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text or Markdown file contents.
				}
				$this->terminate();
				break;

			case Route::STATUS:
				status_header( $route->status_code() );
				header( 'Content-Type: text/plain; charset=UTF-8' );
				echo esc_html( get_status_header_desc( $route->status_code() ) );
				$this->terminate();
				break;

			case Route::RENDER_PAGE:
				// WordPress parses this URI next, so the page renders at the requested URL.
				$_SERVER['REQUEST_URI'] = $route->target();
				break;
		}
	}

	/**
	 * Headers for sent output.
	 *
	 * @param Route $route         Send route.
	 * @param int   $length        Body length in bytes.
	 * @param int   $last_modified Unix time, 0 to leave it out.
	 * @return void
	 */
	protected function send_headers( Route $route, int $length, int $last_modified ): void {
		header( 'Content-Type: ' . $route->content_type() . '; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: ' . $length );
		if ( $route->noindex() ) {
			header( 'X-Robots-Tag: noindex' );
		}

		if ( $last_modified > 0 ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		}
	}

	/**
	 * End the request.
	 *
	 * @return void
	 */
	protected function terminate(): void {
		exit;
	}
}
