<?php

namespace Tjobba\Router
{

/**
 * A router using a tree of closures to find the right route
 *
 *   $router = new \Tjobba\Router\Tree(['read', 'create']);
 *   $router->path('path', function($router) {
 *       router->path('subpath1', 'read', function ($router) { return "This is route /path/subpath1"; });
 *       router->path('subpath2', ['read', 'create'], function ($router) { return "This is route /path/subpath2"; });
 *   });
 *
 * Paths can also have variables defined by regex patterns. Sub-patterns are passed to route handlers as
 * arguments.
 *
 *   $router = new \Tjobba\Router\Tree(['read', 'create']);
 *   $router->path('path', function($router) {
 *       router->pathVariable('/^([a-zA-Z0-9]+)$/', function ($router, $arg1) { 
 *           router->path('subpath2', ['read', 'create'], function ($router, $arg1) { return "This is route /path/" . $arg1 . "/subpath2"; });
 *       });
 *   });
 *
 * Filters can be run before or after the action. They are run in the order they are registered.
 * If any before-filter returns a value (not-null) processing is aborted and that value is the final result.
 * After-filters are run in a chain, each filter passing the result on to the next, and finally out the end.
 *
 * Filters can be nested!
 *
 * Route with {@see self::route()}:
 *
 *   $router->route($verb, $path, $data)
 *
 */
class Tree
{
	/** @var array Used when grouping routes */
	private $filterContextStack = [];
	/** @var array Verbs used by router */
	private $verbs = [];
	/** @var array Temporary possible subpaths */
	private $subpaths = [];
	/** @var array Temporary possible subpatterns */
	private $subpatterns = [];

	/**
	 * Create a router
	 *
	 * @param string[] $verbs ([])
	 * @return self
	 */
	public function __construct($verbs = [])
	{
		$this->verbs = $verbs;
	}


	/*
	|--------------------------------------------------------------------------
	| Route registration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add a possible path to the route
	 *
	 * @param string $subpath exact path
	 * @param string|array $verbs (optional)
	 * @param callable $callback Callback executed if path match
	 */
	public function path($subpath)
	{
		$args = func_get_args();
		$callback = array_pop($args);
		$verbs = count($args) == 2 ? (is_array($args[1]) ? $args[1] : [$args[1]]) : false;
		$filters = $this->collectFilters();

		$this->subpaths[$subpath] = new Route($callback, $verbs, $filters[0], $filters[1]);
	}

	/**
	 * Add a variable path to the route
	 *
	 * @param string $pattern Regexp pattern
	 * @param string|array $verbs (optional)
	 * @param callable $callback Callback executed if path match
	 */
	public function pathVariable($pattern)
	{
		$args = func_get_args();
		$callback = array_pop($args);
		$verbs = count($args) == 2 ? (is_array($args[1]) ? $args[1] : [$args[1]]) : false;
		$filters = $this->collectFilters();

		$this->subpatterns[] = new Route($callback, $verbs, $filters[0], $filters[1], $pattern);
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
	 * Before filters have BeforeFilterInfo as argument.
	 * After filters have AfterFilterInfo as argument.
	 *
	 * @param callable[] $before Filters to run before routes
	 * @param callable[] $after Filters to run after routes
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
	 * @param string $verb
	 * @param string $path
	 * @param array $data
	 * @return mixed
	 */
	public function route($verb, $path, $data)
	{
		$fullpath = explode('/', trim($path, '/'));
		$variables = [$this];

		foreach ($fullpath as $subpath) {

			if (isset($this->subpaths[$subpath])) {
				// Check for subpaths
				$route = $this->subpaths[$subpath];

				if ($result = $this->callRoute($route, $verb, $variables, $data)) {
					return $result;
				}
			} else {
				// Check for patterns
				foreach ($this->subpatterns as $route) {
					$match = [];
					if (preg_match($route->pattern, $subpath, $match)) {
						array_shift($match);
						$variables = array_merge($variables, $match);

						if ($result = $this->callRoute($route, $verb, $variables, $data)) {
							return $result;
						}
						continue 2;
					}
				}

			}

		}

		// No matches
		throw new \Exception("No route", 404);
	}

	private function callRoute($route, $verb, $variables, $data)
	{
		$this->subpaths = [];
		$this->subpatterns = [];
		if ($route->verbs === false || in_array($verb, $route->verbs)) {

			$beforeInfo = new BeforeFilterInfo($verb, null, null, $variables, $data);

			// Check before filters
			// Filters are run in order, and they don't know anything about each other.
			// The first to return anything other than null aborts the sequence.
			foreach ($route->beforeFilters as $filter) {
				$result = call_user_func($filter, $beforeInfo);
				if ($result !== null) {
					return $result;
				}
			}

			$result = call_user_func_array($route->callback, array_merge($variables, [$data]));

			$afterInfo = new AfterFilterInfo($verb, null, null, $variables, $data, $result);

			// Check after filter
			// After filters can modify the result in any way they see fit,
			// but probably shouldn't return null (collides with not finding a route at all).
			foreach ($route->afterFilters as $filter) {
				$result = call_user_func($filter, $afterInfo);
			}
			return $result;

		}
	}

}

/**
 * Private class used by Router
 */
class Route
{
	/** @var callable */
	public $callback;
	/** @var string[] */
	public $verbs;
	/** @var string */
	public $pattern = null;
	/** @var callable[] */
	public $beforeFilters;
	/** @var callable[] */
	public $afterFilters;

	public function __construct($callback, $verbs, $beforeFilters, $afterFilters, $pattern = null)
	{
		$this->callback = $callback;
		$this->verbs = $verbs;
		$this->beforeFilters = $beforeFilters;
		$this->afterFilters = $afterFilters;
		$this->pattern = $pattern;
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
