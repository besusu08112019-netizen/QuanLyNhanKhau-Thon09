<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function __construct(private Request $request)
    {
    }

    public function get(string $path, callable|array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->add('POST', $path, $handler); }
    public function put(string $path, callable|array $handler): void { $this->add('PUT', $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [$method, '/' . trim($path, '/'), $handler];
    }

    public function dispatch(): void
    {
        foreach ($this->routes as [$method, $path, $handler]) {
            if ($method !== $this->request->method()) {
                continue;
            }
            $params = $this->match($path, $this->request->path());
            if ($params === null) {
                continue;
            }
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $instance = new $class($this->request);
                $instance->$action(...array_values($params));
                return;
            }
            $handler($this->request, ...array_values($params));
            return;
        }
        Response::error('API không tồn tại', 404);
    }

    private function match(string $route, string $path): ?array
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route);
        if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
            return null;
        }
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
