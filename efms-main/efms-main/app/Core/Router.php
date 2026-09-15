<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router
 *
 * Lightweight route registrar + dispatcher. Supports:
 *   - static & dynamic segments:  /accounts/{id}
 *   - per-route middleware:       ->middleware(AuthMiddleware::class)
 *   - grouping with shared prefix/middleware
 *
 * New modules extend the app WITHOUT touching this file — see
 * routes/web.php for how a new resource is registered.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:mixed, middleware:array, paramNames:array}>> */
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'PATCH'  => [],
        'DELETE' => [],
    ];

    private string $groupPrefix = '';
    /** @var class-string[] */
    private array $groupMiddleware = [];

    // Pointer to the last route added, so ->middleware() can attach to it.
    private ?string $lastMethod = null;
    private ?int $lastIndex = null;

    public function get(string $uri, mixed $handler): self
    {
        return $this->add('GET', $uri, $handler);
    }

    public function post(string $uri, mixed $handler): self
    {
        return $this->add('POST', $uri, $handler);
    }

    public function put(string $uri, mixed $handler): self
    {
        return $this->add('PUT', $uri, $handler);
    }

    public function patch(string $uri, mixed $handler): self
    {
        return $this->add('PATCH', $uri, $handler);
    }

    public function delete(string $uri, mixed $handler): self
    {
        return $this->add('DELETE', $uri, $handler);
    }

    /**
     * Registers standard REST-ish CRUD routes for a controller in one
     * call, e.g. $router->resource('accounts', AccountController::class).
     * This is the main lever for "extend models fast": add a Model +
     * Controller + Views, call resource(), done.
     */
    public function resource(string $uri, string $controller): self
    {
        $uri = trim($uri, '/');
        $this->get("/$uri", [$controller, 'index']);
        $this->get("/$uri/create", [$controller, 'create']);
        $this->post("/$uri", [$controller, 'store']);
        $this->get("/$uri/{id}", [$controller, 'show']);
        $this->get("/$uri/{id}/edit", [$controller, 'edit']);
        $this->put("/$uri/{id}", [$controller, 'update']);
        $this->delete("/$uri/{id}", [$controller, 'destroy']);

        return $this;
    }

    /**
     * Groups routes under a shared prefix and/or middleware stack.
     *
     *   $router->group('/admin', [AuthMiddleware::class], function ($r) {
     *       $r->resource('accounts', AccountController::class);
     *   });
     */
    public function group(string $prefix, array $middleware, \Closure $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function add(string $method, string $uri, mixed $handler): self
    {
        $uri = $this->groupPrefix . $uri;
        $uri = $uri === '' ? '/' : $uri;

        [$pattern, $paramNames] = $this->compile($uri);

        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $this->groupMiddleware,
            'paramNames' => $paramNames,
        ];

        $this->lastMethod = $method;
        $this->lastIndex = array_key_last($this->routes[$method]);

        return $this;
    }

    /** Attach extra middleware to the single route just registered. */
    public function middleware(array|string $middleware): self
    {
        if ($this->lastMethod !== null && $this->lastIndex !== null) {
            $this->routes[$this->lastMethod][$this->lastIndex]['middleware'] = array_merge(
                $this->routes[$this->lastMethod][$this->lastIndex]['middleware'],
                (array) $middleware
            );
        }

        return $this;
    }

    private function compile(string $uri): array
    {
        $paramNames = [];
        $pattern = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $uri);

        return ['#^' . $pattern . '$#', $paramNames];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['paramNames'], $matches);
                $request->setParams($params);

                return $this->runPipeline($route['middleware'], $route['handler'], $request);
            }
        }

        return Response::html('404 Not Found', 404);
    }

    private function runPipeline(array $middlewareStack, mixed $handler, Request $request): Response
    {
        $next = function (Request $request) use ($handler): Response {
            return $this->callHandler($handler, $request);
        };

        foreach (array_reverse($middlewareStack) as $middlewareClass) {
            $middleware = new $middlewareClass();
            $currentNext = $next;
            $next = fn (Request $request): Response => $middleware->handle($request, $currentNext);
        }

        return $next($request);
    }

    private function callHandler(mixed $handler, Request $request): Response
    {
        if ($handler instanceof \Closure) {
            return $handler($request);
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();

        return $controller->$action($request);
    }
}
