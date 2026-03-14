<?php

namespace App\Core;

class Router
{
    protected array $routes = [];
    public Request $request;
    public Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function get(string $path, $callback, array $middlewares = []): void
    {
        $this->register('get', $path, $callback, $middlewares);
    }

    public function post(string $path, $callback, array $middlewares = []): void
    {
        $this->register('post', $path, $callback, $middlewares);
    }

    public function put(string $path, $callback, array $middlewares = []): void
    {
        $this->register('put', $path, $callback, $middlewares);
    }

    public function delete(string $path, $callback, array $middlewares = []): void
    {
        $this->register('delete', $path, $callback, $middlewares);
    }

    public function resolve()
    {
        $path = $this->request->getPath();
        $method = $this->request->method();
        $routeMatch = $this->matchRegisteredRoute($method, $path);
        $route = $routeMatch['route'] ?? null;

        if ($route === null) {
            $this->response->error('Not Found', ['route' => ['Route not found.']], 404);
            return null;
        }

        $this->request->setRouteParams($routeMatch['params'] ?? []);

        foreach ($route['middlewares'] as $middleware) {
            if (is_string($middleware)) {
                $middleware = new $middleware();
            }

            if (method_exists($middleware, 'handle')) {
                $result = $middleware->handle($this->request, $this->response);
                if ($result === false) {
                    return null;
                }
            }
        }

        $callback = $route['callback'];
        if (is_array($callback)) {
            $callback[0] = new $callback[0]();
        }

        return call_user_func($callback, $this->request, $this->response);
    }

    private function register(string $method, string $path, $callback, array $middlewares = []): void
    {
        $this->routes[$method][] = [
            'path' => $path,
            'callback' => $callback,
            'middlewares' => $middlewares,
        ];
    }

    private function matchRegisteredRoute(string $method, string $path): ?array
    {
        $routes = $this->routes[$method] ?? [];
        foreach ($routes as $route) {
            $params = $this->matchPath($route['path'], $path);
            if ($params === null) {
                continue;
            }

            return [
                'route' => $route,
                'params' => $params,
            ];
        }

        return null;
    }

    private function matchPath(string $routePath, string $requestPath): ?array
    {
        if ($routePath === $requestPath) {
            return [];
        }

        $routeSegments = $this->splitPath($routePath);
        $requestSegments = $this->splitPath($requestPath);

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];
        foreach ($routeSegments as $index => $routeSegment) {
            $requestSegment = $requestSegments[$index];

            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $routeSegment, $matches)) {
                $params[$matches[1]] = urldecode($requestSegment);
                continue;
            }

            if ($routeSegment !== $requestSegment) {
                return null;
            }
        }

        return $params;
    }

    private function splitPath(string $path): array
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? [] : explode('/', $trimmed);
    }
}
