<?php
/**
 * Tests for Container.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Container;

use PHPUnit\Framework\TestCase;
use PinkCrab\LLMs_Helper\Container\Container;
use PinkCrab\LLMs_Helper\Container\Container_Exception;

interface Fixture_Interface {}

class Fixture_Leaf {}

class Fixture_Impl implements Fixture_Interface {}

class Fixture_Needs_Leaf {
	public function __construct( public Fixture_Leaf $leaf ) {}
}

class Fixture_Needs_Interface {
	public function __construct( public Fixture_Interface $dependency ) {}
}

class Fixture_Scalars {
	public function __construct( public Fixture_Leaf $leaf, public string $name, public int $count = 3, public ?string $note = null ) {}
}

class Fixture_Unresolvable {
	public function __construct( public string $name ) {}
}

class Fixture_Variadic {
	public array $rest;
	public function __construct( public Fixture_Leaf $leaf, string ...$rest ) {
		$this->rest = $rest;
	}
}

class Fixture_Circular_A {
	public function __construct( public Fixture_Circular_B $b ) {}
}

class Fixture_Circular_B {
	public function __construct( public Fixture_Circular_A $a ) {}
}

abstract class Fixture_Abstract {}

class Test_Container extends TestCase {

	public function test_builds_a_class_without_a_constructor(): void {
		$this->assertInstanceOf( Fixture_Leaf::class, ( new Container() )->get( Fixture_Leaf::class ) );
	}

	public function test_autowires_class_dependencies(): void {
		$instance = ( new Container() )->get( Fixture_Needs_Leaf::class );

		$this->assertInstanceOf( Fixture_Leaf::class, $instance->leaf );
	}

	public function test_classes_are_not_shared_by_default(): void {
		$container = new Container();

		$this->assertNotSame( $container->get( Fixture_Leaf::class ), $container->get( Fixture_Leaf::class ) );
	}

	public function test_shared_rule_reuses_one_instance(): void {
		$container = new Container( array( Fixture_Leaf::class => array( 'shared' => true ) ) );

		$this->assertSame( $container->get( Fixture_Leaf::class ), $container->get( Fixture_Leaf::class ) );
		$this->assertSame( $container->get( Fixture_Leaf::class ), $container->get( Fixture_Needs_Leaf::class )->leaf );
	}

	public function test_wildcard_rule_applies_to_every_class(): void {
		$container = new Container( array( '*' => array( 'shared' => true ) ) );

		$this->assertSame( $container->get( Fixture_Leaf::class ), $container->get( Fixture_Leaf::class ) );
		$this->assertSame( $container->get( Fixture_Needs_Leaf::class ), $container->get( Fixture_Needs_Leaf::class ) );
	}

	public function test_class_rule_overrides_wildcard_rule(): void {
		$container = new Container(
			array(
				'*'                 => array( 'shared' => true ),
				Fixture_Leaf::class => array( 'shared' => false ),
			)
		);

		$this->assertNotSame( $container->get( Fixture_Leaf::class ), $container->get( Fixture_Leaf::class ) );
	}

	public function test_instance_of_rule_swaps_an_interface_for_a_class(): void {
		$container = new Container( array( Fixture_Interface::class => array( 'instance_of' => Fixture_Impl::class ) ) );

		$this->assertInstanceOf( Fixture_Impl::class, $container->get( Fixture_Interface::class ) );
		$this->assertInstanceOf( Fixture_Impl::class, $container->get( Fixture_Needs_Interface::class )->dependency );
		$this->assertInstanceOf( Fixture_Impl::class, $container->make( Fixture_Interface::class ) );
	}

	public function test_shared_instance_of_rule_reuses_one_instance(): void {
		$container = new Container(
			array(
				Fixture_Interface::class => array(
					'instance_of' => Fixture_Impl::class,
					'shared'      => true,
				),
			)
		);

		$this->assertSame( $container->get( Fixture_Interface::class ), $container->get( Fixture_Interface::class ) );
	}

	public function test_construct_params_fill_parameters_by_name(): void {
		$container = new Container(
			array(
				Fixture_Scalars::class => array(
					'construct_params' => array(
						'name'  => 'crab',
						'count' => 7,
					),
				),
			)
		);

		$instance = $container->get( Fixture_Scalars::class );

		$this->assertSame( 'crab', $instance->name );
		$this->assertSame( 7, $instance->count );
		$this->assertNull( $instance->note );
	}

	public function test_defaults_are_used_when_no_value_is_given(): void {
		$instance = ( new Container() )->make( Fixture_Scalars::class, array( 'name' => 'crab' ) );

		$this->assertSame( 3, $instance->count );
		$this->assertNull( $instance->note );
	}

	public function test_make_args_override_construct_params(): void {
		$container = new Container( array( Fixture_Scalars::class => array( 'construct_params' => array( 'name' => 'rule' ) ) ) );

		$this->assertSame( 'arg', $container->make( Fixture_Scalars::class, array( 'name' => 'arg' ) )->name );
	}

	public function test_make_ignores_a_shared_instance_of_the_class(): void {
		$container = new Container( array( Fixture_Leaf::class => array( 'shared' => true ) ) );

		$this->assertNotSame( $container->get( Fixture_Leaf::class ), $container->make( Fixture_Leaf::class ) );
	}

	public function test_variadic_parameters_are_left_empty(): void {
		$this->assertSame( array(), ( new Container() )->get( Fixture_Variadic::class )->rest );
	}

	public function test_add_rule_merges_with_an_existing_rule(): void {
		$container = new Container( array( Fixture_Leaf::class => array( 'shared' => true ) ) );
		$container->add_rule( Fixture_Leaf::class, array( 'construct_params' => array( 'a' => 1 ) ) );

		$this->assertSame(
			array(
				'shared'           => true,
				'construct_params' => array( 'a' => 1 ),
			),
			$container->rule( Fixture_Leaf::class )
		);
	}

	public function test_rule_merges_the_wildcard_rule(): void {
		$container = new Container( array( '*' => array( 'shared' => true ) ) );

		$this->assertSame( array( 'shared' => true ), $container->rule( Fixture_Leaf::class ) );
	}

	public function test_container_resolves_itself(): void {
		$container = new Container();

		$this->assertSame( $container, $container->get( Container::class ) );
	}

	public function test_has(): void {
		$container = new Container( array( Fixture_Interface::class => array( 'instance_of' => Fixture_Impl::class ) ) );

		$this->assertTrue( $container->has( Fixture_Leaf::class ) );
		$this->assertTrue( $container->has( Fixture_Interface::class ) );
		$this->assertTrue( $container->has( Container::class ) );
		$this->assertFalse( $container->has( 'Not\\A\\Class' ) );
		$this->assertFalse( ( new Container() )->has( Fixture_Interface::class ) );
	}

	public function test_missing_class_throws(): void {
		$this->expectException( Container_Exception::class );
		$this->expectExceptionCode( 1 );

		( new Container() )->get( 'Not\\A\\Class' );
	}

	public function test_abstract_class_throws(): void {
		$this->expectException( Container_Exception::class );
		$this->expectExceptionCode( 2 );

		( new Container() )->get( Fixture_Abstract::class );
	}

	public function test_circular_dependency_throws(): void {
		$this->expectException( Container_Exception::class );
		$this->expectExceptionCode( 3 );

		( new Container() )->get( Fixture_Circular_A::class );
	}

	public function test_failed_build_does_not_leave_the_class_marked_as_building(): void {
		$container = new Container();

		try {
			$container->get( Fixture_Unresolvable::class );
		} catch ( Container_Exception $e ) {
			$this->assertSame( 4, $e->getCode() );
		}

		$this->assertSame( 'crab', $container->make( Fixture_Unresolvable::class, array( 'name' => 'crab' ) )->name );
	}

	public function test_unresolvable_parameter_throws(): void {
		$this->expectException( Container_Exception::class );
		$this->expectExceptionCode( 4 );
		$this->expectExceptionMessage( 'Cannot resolve $name for ' . Fixture_Unresolvable::class . '.' );

		( new Container() )->get( Fixture_Unresolvable::class );
	}
}
