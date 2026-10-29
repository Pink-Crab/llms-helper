<?php
/**
 * Runs the queued background jobs.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Controllers;

use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Content\Builder;
use PinkCrab\LLMs_Helper\Content\Index_Builder;

/**
 * Hooks the llms.txt build, the batched copy build and post list builds onto their queued jobs.
 */
class Cron_Events implements Hookable {

	/**
	 * Set the builders.
	 *
	 * @param Index_Builder $index   Writes llms.txt.
	 * @param Builder       $builder Builds the selected post types in batches.
	 */
	public function __construct(
		protected Index_Builder $index,
		protected Builder $builder
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( Scheduler::HOOK_INDEX, array( $this->index, 'build' ) );
		$loader->action( Scheduler::HOOK_BATCH, array( $this->builder, 'batch' ) );
		$loader->action( Scheduler::HOOK_RETRY, array( $this->builder, 'retry' ), 10, 2 );
		$loader->action( Scheduler::HOOK_POSTS, array( $this->builder, 'posts' ) );
	}
}
