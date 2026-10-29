<?php
/**
 * Activation and deactivation.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper;

use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Content\Index_Builder;

/**
 * Queues a build on activation, and clears queued work and the site root llms.txt on deactivation.
 */
class Lifecycle {

	/**
	 * Set the dependencies.
	 *
	 * @param Scheduler     $scheduler Background jobs.
	 * @param Index_Builder $index     Writes llms.txt.
	 */
	public function __construct(
		protected Scheduler $scheduler,
		protected Index_Builder $index
	) {}

	/**
	 * Queue a build.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->scheduler->queue_build();
	}

	/**
	 * Stop queued work and remove the site root llms.txt this plugin wrote.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->scheduler->clear();
		$this->index->remove_root_file();
	}
}
