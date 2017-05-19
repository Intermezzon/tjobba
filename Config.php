<?php

namespace Tjobba
{

/**
 * A simple container of a config tree, accessed by string keys in dot notation
 *
 *   $config = new Config();
 *   $config->set('app.foo.bar', true);
 *   $isBar = $config->get('app.foo.bar');
 *
 * Use {@see self::merge()} to mass-populate. Good for loading up with
 * default data or from an external config file!
 */
class Config 
{
	/** @var array */
	private $data = [];

	/**
	 * Create a new config
	 *
	 * @param array|IConfig $config ([])
	 */
	public function __construct($config = [])
	{
		$this->merge($config);
	}

	/**
	 * Set a value
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		$keys = explode('.', $key);

		/** @todo Move this into array helper */
		$data = &$this->data;
		while (count($keys) > 1) {
			$key = array_shift($keys);

			// Can use isset here because we want to override whatever is there with an array
			// Doesn't matter if it's null or doesn't exist.
			if (!isset($data[$key]) || !is_array($data[$key])) {
				$data[$key] = [];
			}

			$data = &$data[$key];
		}

		$data[array_shift($keys)] = $value;
	}

	/**
	 * Merge with another config or data
	 *
	 * @param array|IConfig
	 * @return array Dict of changed values
	 */
	public function merge($config)
	{

		if (is_array($config)) {
			$this->data = array_replace_recursive($this->data, $config);
		} else if ($config instanceof Config) {
			$this->data = array_replace_recursive($this->data, $config->data);
		} else {
			throw new \Exception('Unrecognized input');
		}
	}

	/**
	 * Get a config value
	 *
	 * @param string $key
	 * @param mixed $default (null)
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		// If config key is marked as needed, and it's empty, throw an exception
		// Fara: man vill kolla vad den är först, innan man sätter den

		$keys = explode('.', $key);
		$data = $this->data;

		foreach ($keys as $key) {
			if (!array_key_exists($key, $data) || !is_array($data)) {
				return $default;
			}

			$data = $data[$key];
		}

		return $data;
	}
}

}
