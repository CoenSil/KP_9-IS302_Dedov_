<?php

class Router {
    private $routes = [];


    public function add($path, $controller, $action) {
        $this->routes[$path] = [
            'controller' => $controller,
            'action' => $action
        ];
    }


    public function dispatch($uri) {
        $path = parse_url($uri, PHP_URL_PATH);
        
        if (array_key_exists($path, $this->routes)) {
            $controller = $this->routes[$path]['controller'];
            $action = $this->routes[$path]['action'];
            
            require_once "../app/Controllers/$controller.php";
            $controllerInstance = new $controller();
            $controllerInstance->$action();
        } else {
            http_response_code(404);
            echo "404 - Страница не найдена";
        }
    }
}