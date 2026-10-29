<?php
/**
 * Registration loop.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper;

use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Container\Container;

/**
 * Registration loop: builds each controller through the container and registers its hooks.
 */
class App {

	/**
	 * Set the container and hook loader.
	 *
	 * @param Container   $container DI container.
	 * @param Hook_Loader $loader    Hook loader.
	 */
	public function __construct(
		protected Container $container,
		protected Hook_Loader $loader
	) {}

	/**
	 * Build the controllers and register their hooks.
	 *
	 * @param array<class-string> $controllers Controller class names.
	 * @return void
	 */
	public function boot( array $controllers ): void {
		/**
		 * Filter the controller classes booted.
		 *
		 * @param array<class-string> $controllers Controller class names.
		 */
		$controllers = (array) apply_filters( 'llms_helper_controllers', $controllers );

		foreach ( $controllers as $controller ) {
			$instance = $this->container->get( (string) $controller );
			if ( $instance instanceof Hookable ) {
				$instance->register( $this->loader );
			}
		}

		$this->loader->register_hooks();
	}
}
