<?php

namespace SiteHealth\Troubleshooting;

class Types {

	/**
	 * Check, and convert, a value to a given type.
	 *
	 * Where this is used to validate filterable values form WordPress Core,
	 * `$type` should _always_ be defined as the type originally passed by core.
	 *
	 * @param mixed $value The value you wish to type match.
	 * @param string $type The type the value must be returned as, should match the type originally passed by WordPress Core if applicable.
	 * @param callable $callback Optional. A callback to run on the value to convert it to the expected type.
	 *
	 * @return mixed $value The value type matched to the type provided.
	 */
	public static function ensure( $value, $type, $callback = null ) {
		// If the type matches, or the type can be `mixed`, immediately return it.
		if ( $type === \gettype( $value ) || 'mixed' === $type ) {
			return $value;
		}

		if ( null !== $callback ) {
			// If a callback is provided, run it on the value.
			$value = \call_user_func( $callback, $value );
		} else {
			// If no callback is provided, use the built-in type casting.
			\settype( $value, $type );
		}

		// Return the converted value.
		return $value;
	}

}
