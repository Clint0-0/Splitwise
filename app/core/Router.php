<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tiny router. Routes: METHOD + pattern with {name} parameters.
 * Middleware per route: 'auth', 'guest', 'admin'. Every POST is CSRF-checked.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $allowed = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $this->runMiddleware($route['middleware'], $request);
            $params = array_map('intval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
            [$class, $action] = $route['handler'];
            (new $class($request))->$action(...$params);
            return;
        }
        throw new HttpException($allowed ? 405 : 404, $allowed ? 'Method not allowed.' : '');
    }

    private function runMiddleware(array $middleware, Request $request): void
    {
        if ($request->method === 'POST' && !Session::verifyCsrf($request->post('_csrf'))) {
            throw new HttpException(419);
        }
        foreach ($middleware as $name) {
            match ($name) {
                'auth'  => Auth::user() ?? throw new RedirectException(url('/login')),
                'guest' => Auth::user() === null ?: throw new RedirectException(url('/dashboard')),
                'admin' => Auth::isAdmin() ?: throw new HttpException(403),
            };
        }
    }
}
