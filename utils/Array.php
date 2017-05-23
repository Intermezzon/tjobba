<?php

/*

	Array utilities

	A collection of nice-to-have helper functions for arrays, extending the
	standard library.

	The functions are added to the global scope.

*/

/**
 * Set value in an array with dot notation
 *
 * Modifies the array
 *
 * @param array $array
 * @param string $key
 * @param mixed $value
 */
function array_set(&$array, $key, $value)
{
	$keys = explode('.', $key);

	while (count($keys) > 1) {
		$key = array_shift($keys);

		// Can use isset here because we want to override whatever is there with an array
		// Doesn't matter if it's null or doesn't exist.
		if (!isset($array[$key]) || !is_array($array[$key])) {
			$array[$key] = [];
		}

		$array = &$array[$key];
	}

	$array[array_shift($keys)] = $value;
}

/**
 * Get value from an array with dot notation
 *
 * @param array $array
 * @param string $key Key in dot notation
 * @param mixed $default (null)
 * @return mixed
 */
function array_get($array, $key, $default = null)
{
	$keys = explode('.', $key);

	foreach ($keys as $key) {
		if (!array_key_exists($key, $array) || !is_array($array)) {
			return $default;
		}

		$array = $array[$key];
	}

	return $array;
}
