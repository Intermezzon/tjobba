<?php

/**
 * Simple autoloader
 *
 * Resolves from full class names or namespace roots.
 *
 * Namespaces are tested in the order they were added, so more specific
 * namespaces must go before more general, if they share their starts.
 *
 * Full class names are resolved very quickly, but their use is limited.
 */
class Autoloader
{
	/** @var array */
	private $namespaces = [];
	/** @var array */
	private $classes = [];

	/**
	 * Add a namespace
	 *
	 * @param string $namespace
	 * @param string $path Root directory
	 */
	public function addNamespace($namespace, $directory)
	{
		// Force / in the end of directory
		$this->namespaces[$namespace] = rtrim($directory, '/') . '/';
	}

	/**
	 * Add a fully qualified class name directly
	 *
	 * Requested class must match exactly.
	 *
	 * @param string $class
	 * @param string $path Full file path
	 */
	public function addClass($class, $path)
	{
		// Remove initial backslash in classname, it isn't needed
		$this->classes[ltrim($class, '\\')] = $path;
	}

	/**
	 * Add this Autoloader to the autoloader queue
	 */
	public function activate()
	{
		spl_autoload_register([$this, 'autoload']);
	}

	/**
	 * Remove this autoloader from the autoloader queue
	 */
	public function deactivate()
	{
		spl_autoload_unregister([$this, 'autoload']);
	}


	/**
	 * Try to load a class
	 *
	 * @param string $className
	 */
	private function autoload($className)
	{
		if (isset($this->classes[$className])) {
			require_once $this->classes[$className];
			return;
		}

		foreach ($this->namespaces as $namespace => $directory) {
			if (strpos($className, $namespace) === 0) {
				$className = str_replace('\\', '/', substr($className, strlen($namespace) + 1));
				require_once $directory . $className . '.php';
				return;
			}
		}
	}
}
