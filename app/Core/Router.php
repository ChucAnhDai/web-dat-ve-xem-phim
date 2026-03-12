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
        $this->routes['get'][$path] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
        ];
    }

    public function post(string $path, $callback, array $middlewares = []): void
    {
        $this->routes['post'][$path] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
        ];
    }

    public function resolve()
    {
        $path = $this->request->getPath();
        $method = $this->request->method();
        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            $this->response->error('Not Found', ['route' => ['Route not found.']], 404);
            return null;
        }

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
}
