<?php
/**
 * Settings set directly by a test.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use PinkCrab\LLMs_Helper\Interfaces\Settings;

class Fake_Settings implements Settings {

	/**
	 * @param list<string> $post_types
	 */
	public function __construct(
		public array $post_types = array( 'post', 'page' ),
		public bool $redirect = true,
		public string $summary = '',
		public string $notes = '',
		public string $missing_copy = 'page',
		public bool $index_llms_txt = false
	) {}

	public function post_types(): array {
		return $this->post_types;
	}

	public function includes_type( string $post_type ): bool {
		return in_array( $post_type, $this->post_types, true );
	}

	public function redirect(): bool {
		return $this->redirect;
	}

	public function summary(): string {
		return $this->summary;
	}

	public function notes(): string {
		return $this->notes;
	}

	public function missing_copy(): string {
		return $this->missing_copy;
	}

	public function index_llms_txt(): bool {
		return $this->index_llms_txt;
	}
}
