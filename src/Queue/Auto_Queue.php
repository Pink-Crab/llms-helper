<?php
/**
 * Queue that picks Action Scheduler or WP-Cron.
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
 * Uses Action Scheduler when it is ready, otherwise WP-Cron. Chosen on each call, because Action Scheduler
 * sets itself up on plugins_loaded, after this plugin boots.
 */
class Auto_Queue implements Queue {

	/**
	 * Set the drivers.
	 *
	 * @param WP_Cron_Queue          $wp_cron          WP-Cron driver.
	 * @param Action_Scheduler_Queue $action_scheduler Action Scheduler driver.
	 */
	public function __construct(
		protected WP_Cron_Queue $wp_cron,
		protected Action_Scheduler_Queue $action_scheduler
	) {}

	/**
	 * The driver to use now.
	 *
	 * @return Queue
	 */
	public function driver(): Queue {
		/**
		 * Filter whether jobs go to Action Scheduler when it is loaded and ready.
		 *
		 * @param bool $use Whether Action Scheduler is used.
		 */
		$use = $this->action_scheduler_ready() && (bool) apply_filters( 'llms_helper_use_action_scheduler', true );

		return $use ? $this->action_scheduler : $this->wp_cron;
	}

	/**
	 * Whether Action Scheduler is loaded and ready.
	 *
	 * @return bool
	 */
	protected function action_scheduler_ready(): bool {
		return Action_Scheduler_Queue::available();
	}

	/**
	 * {@inheritDoc}
	 */
	public function schedule( string $hook, int $timestamp, array $args = array() ): void {
		$this->driver()->schedule( $hook, $timestamp, $args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_scheduled( string $hook, ?array $args = null ): bool {
		return $this->driver()->is_scheduled( $hook, $args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear( string $hook ): void {
		$this->driver()->clear( $hook );
	}

	/**
	 * {@inheritDoc}
	 */
	public function unschedule( string $hook, array $args ): void {
		$this->driver()->unschedule( $hook, $args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function next_due( string $hook ): ?int {
		return $this->driver()->next_due( $hook );
	}
}
