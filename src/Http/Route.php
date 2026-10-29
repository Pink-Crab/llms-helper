<?php
/**
 * How to answer a request.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Http;

/**
 * Nothing, a 301, a file or contents sent from PHP, the page a copy URL belongs to, or a bare status.
 */
final class Route {

	public const NONE          = 'none';
	public const REDIRECT      = 'redirect';
	public const SEND_FILE     = 'send_file';
	public const SEND_CONTENTS = 'send_contents';
	public const RENDER_PAGE   = 'render_page';
	public const STATUS        = 'status';

	/**
	 * Whether the response asks search engines not to index it.
	 *
	 * @var bool
	 */
	private bool $noindex = false;

	/**
	 * Set the route.
	 *
	 * @param string $kind         One of the kind constants.
	 * @param string $target       Redirect URL, file path, contents or page URI.
	 * @param string $content_type Media type of sent output.
	 * @param bool   $with_body    Whether sent output includes its body.
	 * @param int    $status       HTTP status of a status route.
	 */
	private function __construct(
		private string $kind,
		private string $target = '',
		private string $content_type = '',
		private bool $with_body = true,
		private int $status = 200
	) {}

	/**
	 * Leave the request to WordPress.
	 *
	 * @return self
	 */
	public static function none(): self {
		return new self( self::NONE );
	}

	/**
	 * 301 to a URL.
	 *
	 * @param string $url URL.
	 * @return self
	 */
	public static function redirect( string $url ): self {
		return new self( self::REDIRECT, $url );
	}

	/**
	 * Send a file on local disk.
	 *
	 * @param string $file         Path on disk.
	 * @param string $content_type Media type.
	 * @param bool   $with_body    False for HEAD requests.
	 * @return self
	 */
	public static function send_file( string $file, string $content_type, bool $with_body ): self {
		return new self( self::SEND_FILE, $file, $content_type, $with_body );
	}

	/**
	 * Send contents read from the store.
	 *
	 * @param string $contents     Contents.
	 * @param string $content_type Media type.
	 * @param bool   $with_body    False for HEAD requests.
	 * @return self
	 */
	public static function send_contents( string $contents, string $content_type, bool $with_body ): self {
		return new self( self::SEND_CONTENTS, $contents, $content_type, $with_body );
	}

	/**
	 * Render a page at the requested URL.
	 *
	 * @param string $uri Page request URI.
	 * @return self
	 */
	public static function render_page( string $uri ): self {
		return new self( self::RENDER_PAGE, $uri );
	}

	/**
	 * Answer with a bare HTTP status.
	 *
	 * @param int $status HTTP status.
	 * @return self
	 */
	public static function status( int $status ): self {
		return new self( self::STATUS, '', 'text/plain', true, $status );
	}

	/**
	 * One of the kind constants.
	 *
	 * @return string
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * Redirect URL, file path, contents or page URI.
	 *
	 * @return string
	 */
	public function target(): string {
		return $this->target;
	}

	/**
	 * Media type of sent output.
	 *
	 * @return string
	 */
	public function content_type(): string {
		return $this->content_type;
	}

	/**
	 * Whether sent output includes its body.
	 *
	 * @return bool
	 */
	public function with_body(): bool {
		return $this->with_body;
	}

	/**
	 * HTTP status of a status route.
	 *
	 * @return int
	 */
	public function status_code(): int {
		return $this->status;
	}

	/**
	 * This route, with a header asking search engines not to index the response.
	 *
	 * @return self
	 */
	public function with_noindex(): self {
		$route          = clone $this;
		$route->noindex = true;

		return $route;
	}

	/**
	 * Whether the response asks search engines not to index it.
	 *
	 * @return bool
	 */
	public function noindex(): bool {
		return $this->noindex;
	}
}
