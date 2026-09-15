<?php

require_once __DIR__ . '/../Core/Auth.php';

class HomeController
{
    public function index(): void
    {
        global $pdo;

        $user = Auth::currentUser($pdo);

        require __DIR__ . '/../../views/home/index.php';
    }
}
