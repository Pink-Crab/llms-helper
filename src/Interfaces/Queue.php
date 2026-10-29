<?php
/**
 * Background job queue interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Interfaces;

/**
 * Background job queue.
 */
interface Queue {

	/**
	 * Run a hook once at a time with arguments, unless the same hook and arguments are already queued.
	 *
	 * @param string      $hook      Hook fired when the job runs.
	 * @param int         $timestamp Unix time to run at.
	 * @param list<mixed> $args      Arguments passed to the hook, in order.
	 * @return void
	 */
	public function schedule( string $hook, int $timestamp, array $args = array() ): void;

	/**
	 * Whether a hook is queued.
	 *
	 * @param string           $hook Hook.
	 * @param list<mixed>|null $args Arguments to match, or null for any.
	 * @return bool
	 */
	public function is_scheduled( string $hook, ?array $args = null ): bool;

	/**
	 * Remove every queued job for a hook, whatever its arguments.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function clear( string $hook ): void;

	/**
	 * Remove the queued job for a hook with these arguments.
	 *
	 * @param string      $hook Hook.
	 * @param list<mixed> $args Arguments.
	 * @return void
	 */
	public function unschedule( string $hook, array $args ): void;

	/**
	 * When the earliest queued job for a hook is due.
	 *
	 * @param string $hook Hook.
	 * @return int|null Unix time, or null when nothing waits, including while a job is running.
	 */
	public function next_due( string $hook ): ?int;
}
