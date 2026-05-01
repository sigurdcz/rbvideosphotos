<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, string $controller, string $action): void
    {
        $this->routes[] = ['GET', $path, $controller, $action];
    }

    public function post(string $path, string $controller, string $action): void
    {
        $this->routes[] = ['POST', $path, $controller, $action];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as [$routeMethod, $routePath, $controllerClass, $action]) {
            if ($routeMethod !== $method) {
                continue;
            }
            $pattern = $this->buildPattern($routePath);
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->call($controllerClass, $action, $params);
                return;
            }
        }

        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function call(string $controllerClass, string $action, array $params): void
    {
        $fqcn = 'App\\Controllers\\' . $controllerClass;
        if (!class_exists($fqcn)) {
            throw new \RuntimeException("Controller {$fqcn} not found.");
        }
        $controller = new $fqcn();
        if (!method_exists($controller, $action)) {
            throw new \RuntimeException("Action {$action} not found in {$fqcn}.");
        }
        $controller->$action($params);
    }
}
