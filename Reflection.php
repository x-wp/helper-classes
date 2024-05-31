<?php
/**
 * Reflection class file.
 *
 * @package eXtended WordPress
 */

namespace XWP\Helper\Classes;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Reflector;

/**
 * Reflection utilities.
 */
class Reflection {
    /**
     * Get a reflector for the target.
     *
     * @param  mixed $target The target to get a reflector for.
     * @return ReflectionClass|ReflectionMethod|ReflectionFunction
     *
     * @throws \InvalidArgumentException If the target is invalid.
     */
    public static function get_reflector( mixed $target ): Reflector {
        return match ( true ) {
            $target instanceof Reflector        => $target,
            static::is_valid_class( $target )    => new ReflectionClass( $target ),
            static::is_valid_method( $target )   => new ReflectionMethod( ...$target ),
            static::is_valid_function( $target ) => new ReflectionFunction( $target ),
            default => throw new \InvalidArgumentException( 'Invalid target' ),
        };
    }

    /**
     * Is the target callable.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_callable( mixed $target ): bool {
        return static::is_valid_method( $target ) || static::is_valid_function( $target );
    }

    /**
     * Is the target a valid class.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_valid_class( mixed $target ): bool {
        return \is_object( $target ) || \class_exists( $target );
    }

    /**
     * Is the target a valid method.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_valid_method( mixed $target ): bool {
        return \is_array( $target ) && \is_callable( $target );
    }

    /**
     * Is the target a valid function.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_valid_function( mixed $target ): bool {
        return \is_string( $target ) && ( \function_exists( $target ) || \is_callable( $target ) );
    }

    /**
     * Check if a class implements an interface.
     *
     * @param  string|object $thing    The class to check.
     * @param  string        $iname    The interface to check for.
     * @param  bool          $autoload Whether to allow this function to load the class automatically through the __autoload() magic method.
     * @return bool
     */
    public static function class_implements( string|object $thing, string $iname, bool $autoload = true ): bool {
        $cname = \is_object( $thing ) ? $thing::class : $thing;

        return \class_exists( $cname ) && \in_array( $iname, \class_implements( $thing, $autoload ), true );
    }

    /**
     * Get decorators for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<T>
     */
    public static function get_attributes(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
	): array {
        return static::get_reflector( $target )
            ->getAttributes( $decorator, $flags );
    }

    /**
     * Get decorators for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<T>
     */
    public static function get_decorators(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
    ): array {
        return \array_map(
            static fn( $att ) => $att->newInstance(),
            static::get_attributes( $target, $decorator, $flags ),
        );
    }

    /**
     * Get decorators for a target class, and its parent classes.
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<T>
     */
    public static function get_decorators_deep(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
    ): array {
        $decorators = array();

        while ( $target ) {
            $decorators = \array_merge(
                $decorators,
                static::get_decorators( $target, $decorator, $flags ),
            );

            $target = $target instanceof ReflectionClass
                ? $target->getParentClass()
                : \get_parent_class( $target );
        }

        return $decorators;
    }

    /**
     * Get a **SINGLE** attribute for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @param  int             $index     The index of the decorator to get.
     * @return T|null
     */
    public static function get_attribute(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
        int $index = 0,
    ): ?ReflectionAttribute {
        return static::get_attributes( $target, $decorator, $flags )[ $index ] ?? null;
    }

    /**
     * Get a **SINGLE** decorator for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @param  int             $index     The index of the decorator to get.
     * @return T|null
     */
    public static function get_decorator(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
        int $index = 0,
    ): ?object {
        return static::get_attribute( $target, $decorator, $flags, $index )
            ?->newInstance()
            ?? null;
    }

    /**
     * Get all the traits used by a class.
     *
     * @param  string|object $target Class or object to get the traits for.
     * @param  bool          $autoload        Whether to allow this function to load the class automatically through the __autoload() magic method.
     * @return array                          Array of traits.
     */
	public static function class_uses_deep( string|object $target, bool $autoload = true ) {
		$traits = array();

		do {
			$traits = \array_merge( \class_uses( $target, $autoload ), $traits );
            $target = \get_parent_class( $target );
		} while ( $target );

		foreach ( $traits as $trait ) {
			$traits = \array_merge( \class_uses( $trait, $autoload ), $traits );
		}

		return \array_values( \array_unique( $traits ) );
	}
}
