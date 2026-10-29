<?php
/**
 * Queue on WP-Cron.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Queue;

use PinkCrab\LLMs_Helper\Interfaces\Queue;

/**
 * Queue on WP-Cron.
 */
class WP_Cron_Queue implements Queue {

	/**
	 * {@inheritDoc}
	 */
	public function schedule( string $hook, int $timestamp, array $args = array() ): void {
		if ( ! $this->is_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( $timestamp, $hook, $args );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_scheduled( string $hook, ?array $args = null ): bool {
		if ( null !== $args ) {
			return false !== wp_next_scheduled( $hook, $args );
		}

		foreach ( (array) _get_cron_array() as $events ) {
			if ( is_array( $events ) && isset( $events[ $hook ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear( string $hook ): void {
		wp_unschedule_hook( $hook );
	}

	/**
	 * {@inheritDoc}
	 */
	public function unschedule( string $hook, array $args ): void {
		wp_clear_scheduled_hook( $hook, $args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function next_due( string $hook ): ?int {
		$due = null;
		foreach ( (array) _get_cron_array() as $timestamp => $events ) {
			if ( is_array( $events ) && isset( $events[ $hook ] ) ) {
				$due = null === $due ? (int) $timestamp : min( $due, (int) $timestamp );
			}
		}

		return $due;
	}
}
