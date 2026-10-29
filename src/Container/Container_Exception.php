<?php
/**
 * Container exceptions.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Container;

use RuntimeException;

/**
 * Thrown when the container cannot build a class. Callers escape the values they pass in.
 */
class Container_Exception extends RuntimeException {

	/**
	 * The class does not exist and no rule maps it to one.
	 *
	 * @param string $id Class or interface name.
	 * @return self
	 */
	public static function not_found( string $id ): self {
		return new self( sprintf( '%s could not be found.', $id ), 1 );
	}

	/**
	 * The class is abstract or an interface.
	 *
	 * @param string $id Class name.
	 * @return self
	 */
	public static function not_instantiable( string $id ): self {
		return new self( sprintf( '%s cannot be instantiated.', $id ), 2 );
	}

	/**
	 * The class depends on itself through its constructor.
	 *
	 * @param string $id Class name.
	 * @return self
	 */
	public static function circular( string $id ): self {
		return new self( sprintf( 'Circular dependency while building %s.', $id ), 3 );
	}

	/**
	 * A constructor parameter has no type, default, rule value or null.
	 *
	 * @param string $id        Class name.
	 * @param string $parameter Parameter name.
	 * @return self
	 */
	public static function unresolvable( string $id, string $parameter ): self {
		return new self( sprintf( 'Cannot resolve $%s for %s.', $parameter, $id ), 4 );
	}
}
