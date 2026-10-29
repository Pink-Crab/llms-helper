<?php
/**
 * Tests for App.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\App;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Container\Container;

class Fixture_Dependency {}

class Fixture_Controller implements Hookable {
	public function __construct( public Fixture_Dependency $dependency ) {}

	public function register( Hook_Loader $loader ): void {
		$loader->action( 'llms_helper_test_app', array( $this, 'callback' ) );
	}

	public function callback(): void {}
}

class Fixture_Not_Hookable {
	public static int $built = 0;

	public function __construct() {
		++self::$built;
	}
}

class Test_App extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Fixture_Not_Hookable::$built = 0;
	}

	private function app( Container $container ): App {
		return new App( $container, $container->get( Hook_Loader::class ) );
	}

	public function test_controllers_are_built_through_the_container_and_registered(): void {
		$container = new Container( array( '*' => array( 'shared' => true ) ) );

		$this->app( $container )->boot( array( Fixture_Controller::class ) );

		$controller = $container->get( Fixture_Controller::class );
		$this->assertSame( $container->get( Fixture_Dependency::class ), $controller->dependency );
		$this->assertSame( 10, has_action( 'llms_helper_test_app', array( $controller, 'callback' ) ) );
	}

	public function test_classes_that_are_not_hookable_are_built_but_add_no_hooks(): void {
		$container = new Container( array( '*' => array( 'shared' => true ) ) );

		$this->app( $container )->boot( array( Fixture_Not_Hookable::class ) );

		$this->assertSame( 1, Fixture_Not_Hookable::$built );
		$this->assertSame( array(), $container->get( Hook_Loader::class )->hooks() );
	}

	public function test_controller_list_can_be_filtered(): void {
		add_filter(
			'llms_helper_controllers',
			function ( array $controllers ): array {
				$this->assertSame( array( Fixture_Not_Hookable::class ), $controllers );
				return array( Fixture_Controller::class );
			}
		);
		$container = new Container( array( '*' => array( 'shared' => true ) ) );

		$this->app( $container )->boot( array( Fixture_Not_Hookable::class ) );

		$this->assertSame( 0, Fixture_Not_Hookable::$built );
		$this->assertSame( 10, has_action( 'llms_helper_test_app', array( $container->get( Fixture_Controller::class ), 'callback' ) ) );
	}

	public function test_boot_registers_the_queued_hooks(): void {
		$container = new Container( array( '*' => array( 'shared' => true ) ) );
		$callback  = static function (): void {};
		$container->get( Hook_Loader::class )->action( 'llms_helper_test_queued', $callback );

		$this->app( $container )->boot( array() );

		$this->assertSame( 10, has_action( 'llms_helper_test_queued', $callback ) );
	}
}
