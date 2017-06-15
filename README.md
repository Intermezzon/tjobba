# tjobba
A PHP framework with several standalone classes


## \Tjobba\Router\Tree
The Router\Tree is a router where you build your routes using subpaths and closures. 
The advantage is that the closures are only called if the subpath match which makes it SUPER FAST and easy to follow.
It also makes it very easy to delegate specific paths to some kind of controller/action-pattern

```php
$router = new \Tjobba\Router\Tree(['create', 'read', 'update', 'delete']);
$router->path('blog', function($router) {
	$router->path('list', 'read', function ($router) {
		return "List of all posts";
	});
	$router->path('tags', 'read', function ($router) {
		return "List of all tags";
	});
	$router->pathVariable('/^([0-9]+)$/', function ($router, $postId) {
		return "Post " . $postId;
	});
});

// Or pass it on to some kind of controller
$router->path('docs', ['Controller\\Docs', 'route']);

// Execute the route
$verb = $_SERVER['REQUEST_METHOD'] == 'GET' ? 'read' : 'create';
$path = $_SERVER['DOCUMENT_URI'];
echo $router->route($verb, $path);
```

You may also use filters to execute stuff before or after the routes.
```php
function authorize($info) {
	// Authorize user somehow
	$user = getUser();
	if (!$user) {
		return 'You do not have access to this area.';
	}
}

$router = new \Tjobba\Router\Tree(['create', 'read', 'update', 'delete']);
$router->filter(['authorize'], null, function($router) {
	$router->path('restrictedarea', function ($router) {
		return "Warning: Resticted area. Authorized personnel only.";
	});
});

```
