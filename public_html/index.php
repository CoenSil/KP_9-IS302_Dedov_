<?php

session_start(); 

require_once '../config/db.php';
require_once '../app/Core/Router.php';

$router = new Router();

$router->add('/', 'HomeController', 'index');
$router->add('/login', 'AuthController', 'login');
$router->add('/register', 'AuthController', 'register');
$router->add('/profile', 'ProfileController', 'index');
$router->add('/profile/edit', 'ProfileController', 'edit');
$router->add('/logout', 'ProfileController', 'logout');

$router->dispatch($_SERVER['REQUEST_URI']);