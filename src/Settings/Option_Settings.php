<?php
/**
 * Settings stored in a WordPress option.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Settings;

use PinkCrab\LLMs_Helper\Interfaces\Settings;

/**
 * Reads the llms_helper_settings option over the defaults.
 *
 * @phpstan-type Values array{post_types: list<string>, redirect: bool, summary: string, notes: string, missing_copy: 'page'|'404'|'410', index_llms_txt: bool}
 */
class Option_Settings implements Settings {

	/**
	 * Option holding the settings.
	 *
	 * @var string
	 */
	public const OPTION = 'llms_helper_settings';

	/**
	 * Allowed values for missing_copy.
	 *
	 * @var list<string>
	 */
	public const MISSING_COPY = array( 'page', '404', '410' );

	/**
	 * Default settings.
	 *
	 * @return Values
	 */
	public static function defaults(): array {
		return array(
			'post_types'     => array( 'post', 'page' ),
			'redirect'       => true,
			'summary'        => '',
			'notes'          => '',
			'missing_copy'   => 'page',
			'index_llms_txt' => false,
		);
	}

	/**
	 * Saved settings over the defaults.
	 *
	 * @return Values
	 */
	public function all(): array {
		return self::parse( get_option( self::OPTION, array() ) );
	}

	/**
	 * Cast a stored option value over the defaults.
	 *
	 * @param mixed $saved Option value.
	 * @return Values
	 */
	public static function parse( $saved ): array {
		$saved    = is_array( $saved ) ? $saved : array();
		$defaults = self::defaults();
		$missing  = (string) ( $saved['missing_copy'] ?? $defaults['missing_copy'] );

		return array(
			'post_types'     => array_values( array_map( 'strval', (array) ( $saved['post_types'] ?? $defaults['post_types'] ) ) ),
			'redirect'       => (bool) ( $saved['redirect'] ?? $defaults['redirect'] ),
			'summary'        => (string) ( $saved['summary'] ?? $defaults['summary'] ),
			'notes'          => (string) ( $saved['notes'] ?? $defaults['notes'] ),
			'missing_copy'   => in_array( $missing, self::MISSING_COPY, true ) ? $missing : $defaults['missing_copy'],
			'index_llms_txt' => (bool) ( $saved['index_llms_txt'] ?? $defaults['index_llms_txt'] ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_types(): array {
		return $this->all()['post_types'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function includes_type( string $post_type ): bool {
		return in_array( $post_type, $this->post_types(), true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function redirect(): bool {
		return $this->all()['redirect'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return $this->all()['summary'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function notes(): string {
		return $this->all()['notes'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function missing_copy(): string {
		return $this->all()['missing_copy'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function index_llms_txt(): bool {
		return $this->all()['index_llms_txt'];
	}
}
