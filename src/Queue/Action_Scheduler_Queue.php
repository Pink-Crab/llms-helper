<?php
/**
 * Queue on Action Scheduler.
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
 * Queue on Action Scheduler. Only usable once Action Scheduler has initialised (see Auto_Queue).
 */
class Action_Scheduler_Queue implements Queue {

	/**
	 * Action Scheduler group for this plugin's jobs.
	 *
	 * @var string
	 */
	public const GROUP = 'llms-helper';

	/**
	 * Whether Action Scheduler is loaded and its data store is ready.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( 'ActionScheduler', false )
			&& function_exists( 'as_has_scheduled_action' )
			&& \ActionScheduler::is_initialized();
	}

	/**
	 * {@inheritDoc}
	 */
	public function schedule( string $hook, int $timestamp, array $args = array() ): void {
		if ( ! $this->is_scheduled( $hook, $args ) ) {
			as_schedule_single_action( $timestamp, $hook, $args, self::GROUP );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_scheduled( string $hook, ?array $args = null ): bool {
		return as_has_scheduled_action( $hook, $args, self::GROUP );
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear( string $hook ): void {
		// With a group and no args Action Scheduler only cancels jobs whose args are empty, so no group here.
		as_unschedule_all_actions( $hook );
	}

	/**
	 * {@inheritDoc}
	 */
	public function unschedule( string $hook, array $args ): void {
		as_unschedule_action( $hook, $args, self::GROUP );
	}

	/**
	 * {@inheritDoc}
	 */
	public function next_due( string $hook ): ?int {
		// True while one is running, false when none waits.
		$next = as_next_scheduled_action( $hook, null, self::GROUP );

		return is_int( $next ) ? $next : null;
	}
}
