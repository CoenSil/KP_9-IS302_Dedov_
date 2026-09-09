<?php

declare(strict_types=1);

session_start(); 

header('Content-Type: text/html; charset=utf-8');

$phpVersion = phpversion();
$dbStatus = 'Не подключена (требуется настройка config/db.php)';


$isLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

$configFile = __DIR__ . '/../db.php';
if (file_exists($configFile)) {
    $dbConfig = require $configFile;
    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['db']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $dbStatus = '✅ Успешное подключение к MySQL (PDO)!';
    } catch (PDOException $e) {
        $dbStatus = '❌ Ошибка подключения к MySQL: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Курсовой проект — Стенд готов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 40px; background: #f4f6f8; }
        .card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); max-width: 600px; }
        h1 { color: #1e293b; margin-top: 0; font-size: 20px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: bold; background: #e2e8f0; }
        .ok { color: #15803d; }
        .auth-status {
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .auth-status.logged-in {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }
        .auth-status.logged-out {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        .btn-logout {
            background: #dc2626;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-logout:hover {
            background: #b91c1c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Курсовой проект: Стенд инициализирован</h1>
        
    
        <div class="auth-status <?= $isLoggedIn ? 'logged-in' : 'logged-out' ?>">
            <?php if ($isLoggedIn): ?>
                <strong>✅ Вы авторизованы</strong><br>
                👤 <strong><?= htmlspecialchars($username) ?></strong> 
                (роль: <?= htmlspecialchars($role) ?>)<br>
                <a href="logout.php" class="btn-logout mt-2">Выйти</a>
            <?php else: ?>
                <strong>❌ Вы не авторизованы</strong><br>
                <a href="/app/Controllers/login.php">Войти</a>
                или 
                <a href="/app/Controllers/register.php" >Зарегистрироваться</a>
            <?php endif; ?>
        </div>
        
        <p><strong>Версия PHP на хостинге:</strong> <span class="badge"><?= htmlspecialchars($phpVersion) ?></span></p>
        <p><strong>Статус СУБД:</strong> <?= $dbStatus ?></p>
        <hr>
        <p><em>Профессиональный модуль ПМ.09 / МДК.09.01</em></p>
    </div>
</body>
</html>