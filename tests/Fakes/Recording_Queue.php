<?php
/**
 * Queue that records jobs in memory.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Fakes;

use PinkCrab\LLMs_Helper\Interfaces\Queue;

class Recording_Queue implements Queue {

	/**
	 * @var list<array{hook: string, timestamp: int, args: list<mixed>}>
	 */
	public array $jobs = array();

	public function schedule( string $hook, int $timestamp, array $args = array() ): void {
		if ( ! $this->is_scheduled( $hook, $args ) ) {
			$this->jobs[] = array(
				'hook'      => $hook,
				'timestamp' => $timestamp,
				'args'      => $args,
			);
		}
	}

	public function is_scheduled( string $hook, ?array $args = null ): bool {
		foreach ( $this->jobs as $job ) {
			if ( $job['hook'] === $hook && ( null === $args || $job['args'] === $args ) ) {
				return true;
			}
		}
		return false;
	}

	public function clear( string $hook ): void {
		$this->jobs = array_values(
			array_filter(
				$this->jobs,
				static function ( array $job ) use ( $hook ): bool {
					return $job['hook'] !== $hook;
				}
			)
		);
	}

	public function unschedule( string $hook, array $args ): void {
		$this->jobs = array_values(
			array_filter(
				$this->jobs,
				static function ( array $job ) use ( $hook, $args ): bool {
					return $job['hook'] !== $hook || $job['args'] !== $args;
				}
			)
		);
	}

	public function next_due( string $hook ): ?int {
		$due = array_column(
			array_filter(
				$this->jobs,
				static function ( array $job ) use ( $hook ): bool {
					return $job['hook'] === $hook;
				}
			),
			'timestamp'
		);

		return array() === $due ? null : min( $due );
	}
}
