<?php
/**
 * Autowiring DI container.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds classes from their constructor types, with Dice-style rules keyed by class or interface name
 * ('*' applies to all):
 * - shared:           reuse one instance.
 * - instance_of:      build this class when the key is asked for.
 * - construct_params: constructor values by parameter name.
 *
 * @phpstan-type Rule array{shared?: bool, instance_of?: class-string, construct_params?: array<string, mixed>}
 */
class Container {

	/**
	 * Rules by class name.
	 *
	 * @var array<string, Rule>
	 */
	protected array $rules = array();

	/**
	 * Shared instances by class name.
	 *
	 * @var array<string, object>
	 */
	protected array $instances = array();

	/**
	 * Classes being built, to catch circular dependencies.
	 *
	 * @var array<string, true>
	 */
	protected array $building = array();

	/**
	 * Set the rules and register the container as its own instance.
	 *
	 * @param array<string, Rule> $rules Initial rules.
	 */
	public function __construct( array $rules = array() ) {
		$this->add_rules( $rules );
		$this->instances[ self::class ] = $this;
	}

	/**
	 * Add or merge a rule.
	 *
	 * @param string $id   Class name, or '*'.
	 * @param Rule   $rule Rule.
	 * @return self
	 */
	public function add_rule( string $id, array $rule ): self {
		$this->rules[ $id ] = array_merge( $this->rules[ $id ] ?? array(), $rule );
		return $this;
	}

	/**
	 * Add or merge several rules.
	 *
	 * @param array<string, Rule> $rules Rules by class name.
	 * @return self
	 */
	public function add_rules( array $rules ): self {
		foreach ( $rules as $id => $rule ) {
			$this->add_rule( $id, $rule );
		}
		return $this;
	}

	/**
	 * The rule for a class, merged over the '*' rule.
	 *
	 * @param string $id Class name.
	 * @return Rule
	 */
	public function rule( string $id ): array {
		return array_merge( $this->rules['*'] ?? array(), $this->rules[ $id ] ?? array() );
	}

	/**
	 * Whether the container can provide this id.
	 *
	 * @param string $id Class or interface name.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] ) || isset( $this->rules[ $id ]['instance_of'] ) || class_exists( $id );
	}

	/**
	 * Get an instance, reusing it when its rule is shared.
	 *
	 * @template T of object
	 * @param class-string<T>|string $id Class or interface name.
	 * @return ($id is class-string<T> ? T : object)
	 * @throws Container_Exception When the class cannot be built.
	 */
	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		$rule     = $this->rule( $id );
		$instance = isset( $rule['instance_of'] ) ? $this->get( $rule['instance_of'] ) : $this->make( $id );

		if ( ! empty( $rule['shared'] ) ) {
			$this->instances[ $id ] = $instance;
		}

		return $instance;
	}

	/**
	 * Build a new instance, ignoring shared instances of the class itself.
	 *
	 * @template T of object
	 * @param class-string<T>|string $id   Class or interface name.
	 * @param array<string, mixed>   $args Constructor values by parameter name, over the rule's.
	 * @return ($id is class-string<T> ? T : object)
	 * @throws Container_Exception When the class cannot be built.
	 */
	public function make( string $id, array $args = array() ): object {
		$rule = $this->rule( $id );
		if ( isset( $rule['instance_of'] ) ) {
			return $this->make( $rule['instance_of'], $args );
		}

		if ( ! class_exists( $id ) ) {
			throw Container_Exception::not_found( esc_html( $id ) );
		}

		if ( isset( $this->building[ $id ] ) ) {
			throw Container_Exception::circular( esc_html( $id ) );
		}

		$reflection = new ReflectionClass( $id );
		if ( ! $reflection->isInstantiable() ) {
			throw Container_Exception::not_instantiable( esc_html( $id ) );
		}

		$constructor = $reflection->getConstructor();
		if ( null === $constructor ) {
			return $reflection->newInstance();
		}

		$params = array_merge( $rule['construct_params'] ?? array(), $args );
		$values = array();

		$this->building[ $id ] = true;
		try {
			foreach ( $constructor->getParameters() as $parameter ) {
				if ( $parameter->isVariadic() ) {
					break;
				}
				$values[] = $this->resolve( $id, $parameter, $params );
			}
		} finally {
			unset( $this->building[ $id ] );
		}

		return $reflection->newInstanceArgs( $values );
	}

	/**
	 * Value for one constructor parameter.
	 *
	 * @param string               $id        Class being built.
	 * @param ReflectionParameter  $parameter Parameter.
	 * @param array<string, mixed> $params    Values by parameter name.
	 * @return mixed
	 * @throws Container_Exception When nothing can fill the parameter.
	 */
	protected function resolve( string $id, ReflectionParameter $parameter, array $params ) {
		$name = $parameter->getName();
		if ( array_key_exists( $name, $params ) ) {
			return $params[ $name ];
		}

		$type = $parameter->getType();
		if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
			return $this->get( $type->getName() );
		}

		if ( $parameter->isDefaultValueAvailable() ) {
			return $parameter->getDefaultValue();
		}

		if ( $parameter->allowsNull() ) {
			return null;
		}

		throw Container_Exception::unresolvable( esc_html( $id ), esc_html( $name ) );
	}
}
