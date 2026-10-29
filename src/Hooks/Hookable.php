<?php
/**
 * Hookable interface.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Hooks;

/**
 * A controller that adds hooks during the registration loop.
 */
interface Hookable {

	/**
	 * Add hooks to the loader.
	 *
	 * @param Hook_Loader $loader Hook loader.
	 * @return void
	 */
	public function register( Hook_Loader $loader ): void;
}
