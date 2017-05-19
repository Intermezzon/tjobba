<?php

namespace Tjobba
{

/**
 * A router
 *
 * Maps a verb+path to a callback (the handler).
 *
 * Handlers take the path args + data as arguments.
 *
 * Register routes magically with their verbs, eg:
 *
 *   $router = new Router(['read', 'create']);
 *   $router->read('path', $handler);
 *   $router->create('path', $handler);
 *   $router->update('path', $handler);	// Throws exception
 *
 * Paths are regex strings. Sub-patterns are passed to route handlers as
 * arguments. To simplify, you can use variables instead of repeating the same
 * regex over and over, by inserting them inside { and }:
 *
 *   $router->variable('foo', '[0-9+]');
 *   $router->read('path/{foo}/baz', $handler);
 *
 *   $router->route('path/123/baz', ...)	// Will match
 *   $router->route('path/abc/baz', ...)	// Won't
 *
 * The handlers take the captured variables as arguments + the input data:
 *
 *   fooHandler($id, $data) { ... }
 *
 * Route with {@see self::route()}:
 *
 *   $router->route($verb, $path, $data)
 *
 */
class Router
{
	/** @var array Map of vars => regex */
	private $vars = [];
	/** @var array */
	private $routes = [];

	/** @var array Used when grouping routes */
	private $filterContextStack = [];


	/**
	 * Create a router
	 *
	 * @param string[] $verbs ([])
	 * @return self
	 */
	public function __construct($verbs = [])
	{
		foreach ($verbs as $verb) {
			$this->routes[$verb] = [];
		}
	}


	/*
	|--------------------------------------------------------------------------
	| Route registration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add a variable regex that can be used in a route
	 *
	 * You can just write the regex directly in the route, but using this method makes
	 * life easier.
	 *
	 * @param string $id The identifier to use in the routes
	 * @param string $regex The regex the id is replaced with
	 */
	public function variable($id, $regex)
	{
		$this->vars[$id] = $regex;
	}

	/**
	 * Magic method for registering routes
	 *
	 * @param string $name
	 * @param array $arguments
	 */
	public function __call($name, $arguments)
	{
		if (!isset($this->routes[$name])) {
			throw new \Exception('No verb ' . $name . ' registered');
		}
		if (count($arguments) != 2) {
			throw new \Exception('Wrong number of arguments');
		}

		$this->register($arguments[0], $arguments[1]);
	}

	/**
	 * Register a route
	 *
	 * Don't call this directly, go through magic methods, eg:
	 *
	 *   $router->read($path, $handler);
	 *
	 * @param string $verb
	 * @param string $path
	 * @param callable $handler
	 */
	private function register($verb, $path, $handler)
	{
		$filters = $this->collectFilters();
		$this->routes[$verb][] = new Route($verb, $this->expandVars($path), $filters[0], $filters[1], $handler);
	}

	/**
	 * Replace vars with real regex
	 *
	 * @param string $path
	 * @return string Route with vars replaced by their regexes
	 */
	public function expandVars($path)
	{
		foreach ($this->vars as $id => $regex) {
			$path = str_replace('{' . $id . '}', '(' . $regex . ')', $path);
		}
		return $path;
	}


	/*
	|--------------------------------------------------------------------------
	| Filtering
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create a filter context for a bunch of routes
	 *
	 * Callback gets this router as sole argument.
	 *
	 * Before filters has BeforeFilterInfo as argument.
	 * After filters havs AfterFilterInfo as argument.
	 *
	 * @param array $before Filters to run before routes
	 * @param array $after Filters to run after routes
	 * @param callable $callback Add your routes in this callback
	 */
	public function filter($before, $after, $callback)
	{
		$this->filterContextStack[] = [$before, $after];
		call_user_func($callback, $this);
		array_pop($this->filterContextStack);
	}

	/**
	 * Mash stack into two arrays
	 *
	 * @return array
	 */
	private function collectFilters()
	{
		$out = [[], []];
		foreach ($this->filterContextStack as $context) {
			if (is_array($context[0])) { $out[0] = array_merge($out[0], $context[0]); }
			if (is_array($context[1])) { $out[1] = array_merge($out[1], $context[1]); }
		}
		return $out;
	}


	/*
	|--------------------------------------------------------------------------
	| Routing
	|--------------------------------------------------------------------------
	*/

	/**
	 * Route with additional data
	 *
	 * Each variable in the route is passed to the handler as an argument.
	 * The data is passed directly to the handler as the last argument.
	 *
	 * Returned value is what handler returns.
	 *
	 * Returns null if route isn't found (verb is wrong, or path is wrong).
	 *
	 * @param string $verb
	 * @param string $path
	 * @param array $data
	 * @return mixed
	 */
	public function route($verb, $path, $data)
	{
		if (!isset($this->routes[$verb])) {
			return null;
		}

		foreach ($this->routes[$verb] as $route) {
			// Extract all the variables
			$variables = [];
			if (preg_match('[^' . $route->path . '$]', $path, $variables)) {
				array_shift($variables);

				$beforeInfo = new BeforeFilterInfo($verb, $path, $route->path, $variables, $data);

				// Check before filters
				// Filters are run in order, and they don't know anything about each other.
				// The first to return anything other than null aborts the sequence.
				foreach ($route->beforeFilters as $filter) {
					$result = call_user_func($filter, $beforeInfo);
					if ($result !== null) {
						return $result;
					}
				}

				$input = $variables;
				$input[] = $data;

				$result = call_user_func_array($route->handler, $input);

				$afterInfo = new AfterFilterInfo($verb, $path, $route->path, $variables, $data, $result);

				// Check after filter
				// After filters can modify the result in any way they see fit,
				// but probably shouldn't return null (collides with not finding a route at all).
				foreach ($route->afterFilters as $filter) {
					$result = call_user_func($filter, $afterInfo);
				}
				return $result;
			}
		}

		// Didn't find a route
		return null;
	}
}


/**
 * Private class used by Router
 */
class Route
{
	/** @var string */
	public $verb;
	/** @var string */
	public $path;
	/** @var callable[] */
	public $beforeFilters;
	/** @var callable[] */
	public $afterFilters;
	/** @var callable */
	public $handler;

	public function __construct($verb, $path, $beforeFilters, $afterFilters, $handler)
	{
		$this->verb = $verb;
		$this->path = $path;
		$this->beforeFilters = $beforeFilters;
		$this->afterFilters = $afterFilters;
		$this->handler = $handler;
	}
}

/**
 * The argument to before filters
 */
class BeforeFilterInfo
{
	/** @var string */
	public $verb;
	/** @var string */
	public $path;
	/** @var string */
	public $matchedPath;
	/** @var array */
	public $variables;
	/** @var array */
	public $data;

	public function __construct($verb, $path, $matchedPath, $variables, $data)
	{
		$this->verb = $verb;
		$this->path = $path;
		$this->matchedPath = $matchedPath;
		$this->variables = $variables;
		$this->data = $data;
	}
}

/**
 * The argument to after filters
 *
 * Same as BeforeFilterInfo but with the result of the action too.
 */
class AfterFilterInfo extends BeforeFilterInfo
{
	/** @var mixed */
	public $result;

	public function __construct($verb, $path, $matchedPath, $variables, $data, $result)
	{
		parent::__construct($verb, $path, $matchedPath, $variables, $data);
		$this->result = $result;
	}
}

}
