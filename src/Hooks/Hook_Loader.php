<?php
/**
 * Hook loader.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Hooks;

/**
 * Collects actions and filters, then registers them with WordPress in one go.
 *
 * @phpstan-type Hook array{type: 'action'|'filter', hook: string, callback: callable, priority: int, args: int}
 */
class Hook_Loader {

	/**
	 * Hooks waiting to be registered.
	 *
	 * @var list<Hook>
	 */
	protected array $hooks = array();

	/**
	 * Queue an action.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted arguments.
	 * @return self
	 */
	public function action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): self {
		return $this->add( 'action', $hook, $callback, $priority, $args );
	}

	/**
	 * Queue a filter.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted arguments.
	 * @return self
	 */
	public function filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): self {
		return $this->add( 'filter', $hook, $callback, $priority, $args );
	}

	/**
	 * Hooks waiting to be registered.
	 *
	 * @return list<Hook>
	 */
	public function hooks(): array {
		return $this->hooks;
	}

	/**
	 * Register every queued hook with WordPress and empty the queue.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		foreach ( $this->hooks as $hook ) {
			if ( 'action' === $hook['type'] ) {
				add_action( $hook['hook'], $hook['callback'], $hook['priority'], $hook['args'] );
			} else {
				add_filter( $hook['hook'], $hook['callback'], $hook['priority'], $hook['args'] );
			}
		}

		$this->hooks = array();
	}

	/**
	 * Queue a hook.
	 *
	 * @param 'action'|'filter' $type     Hook type.
	 * @param string            $hook     Hook name.
	 * @param callable          $callback Callback.
	 * @param int               $priority Priority.
	 * @param int               $args     Accepted arguments.
	 * @return self
	 */
	protected function add( string $type, string $hook, callable $callback, int $priority, int $args ): self {
		$this->hooks[] = array(
			'type'     => $type,
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);
		return $this;
	}
}
