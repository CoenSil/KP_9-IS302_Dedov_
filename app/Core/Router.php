<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $route, string $controller, string $action): void
    {
        $this->routes[$route] = [$controller, $action];
    }

    public function dispatch(string $route): void
    {
        if (!isset($this->routes[$route])) {
            http_response_code(404);
            echo '404. Страница не найдена.';
            return;
        }

        [$controllerName, $action] = $this->routes[$route];
        $class = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($class) || !method_exists($class, $action)) {
            http_response_code(500);
            echo 'Ошибка маршрутизации.';
            return;
        }

        $controller = new $class();
        $controller->$action();
    }
}
