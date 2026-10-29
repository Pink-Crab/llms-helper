<?php
/**
 * Tests for Hook_Loader.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Hooks;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;

class Test_Hook_Loader extends WP_UnitTestCase {

	public function test_queues_actions_and_filters_with_their_settings(): void {
		$action = static function (): void {};
		$filter = static function ( $value ) {
			return $value;
		};

		$loader = ( new Hook_Loader() )
			->action( 'llms_helper_test_action', $action, 5, 2 )
			->filter( 'llms_helper_test_filter', $filter );

		$this->assertSame(
			array(
				array(
					'type'     => 'action',
					'hook'     => 'llms_helper_test_action',
					'callback' => $action,
					'priority' => 5,
					'args'     => 2,
				),
				array(
					'type'     => 'filter',
					'hook'     => 'llms_helper_test_filter',
					'callback' => $filter,
					'priority' => 10,
					'args'     => 1,
				),
			),
			$loader->hooks()
		);
	}

	public function test_nothing_is_added_to_wordpress_until_registered(): void {
		$callback = static function (): void {};
		( new Hook_Loader() )->action( 'llms_helper_test_action', $callback );

		$this->assertFalse( has_action( 'llms_helper_test_action', $callback ) );
	}

	public function test_register_hooks_adds_them_to_wordpress(): void {
		$action = static function (): void {};
		$filter = static function ( $value ) {
			return $value . '!';
		};

		( new Hook_Loader() )
			->action( 'llms_helper_test_action', $action, 5, 2 )
			->filter( 'llms_helper_test_filter', $filter, 20 )
			->register_hooks();

		$this->assertSame( 5, has_action( 'llms_helper_test_action', $action ) );
		$this->assertSame( 20, has_filter( 'llms_helper_test_filter', $filter ) );
		$this->assertFalse( has_filter( 'llms_helper_test_action', $filter ) );
		$this->assertSame( 'crab!', apply_filters( 'llms_helper_test_filter', 'crab' ) );
	}

	public function test_register_hooks_passes_accepted_args(): void {
		$received = array();
		$callback = static function ( ...$args ) use ( &$received ): void {
			$received = $args;
		};

		( new Hook_Loader() )->action( 'llms_helper_test_action', $callback, 10, 2 )->register_hooks();
		do_action( 'llms_helper_test_action', 'a', 'b', 'c' );

		$this->assertSame( array( 'a', 'b' ), $received );
	}

	public function test_register_hooks_empties_the_queue(): void {
		$loader = ( new Hook_Loader() )->action( 'llms_helper_test_action', static function (): void {} );
		$loader->register_hooks();

		$this->assertSame( array(), $loader->hooks() );
	}
}
