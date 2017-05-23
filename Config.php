<?php

require 'ArrayHelper.php';

namespace Tjobba
{

/**
 * A simple container of a config tree, accessed by string keys in dot notation
 *
 * @todo Remove this? Is just a thin wrapper around an array, and should be implemented in App instead?
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
		ArrayHelper::set($this->data, $key, $value);
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
		return ArrayHelper::get($this->data, $key, $default);
	}
}

}
