<?php

/**
 * Проверка живости сервиса для мониторинга.
 *
 * Без ключа отвечает коротко: {"status":"ok"}.
 * С ключом (?key=HEALTH_KEY) добавляет детали: версия PHP, БД, запись в storage.
 */

require __DIR__ . '/../config/config.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$db = false;
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    new \PDO($dsn, DB_USER, DB_PASS, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_TIMEOUT => 3,
    ]);
    $db = true;
} catch (\Throwable $e) {
    error_log('Health DB: ' . $e->getMessage());
}

$storageDir = dirname(__DIR__) . '/storage';
$storage = is_dir($storageDir) && is_writable($storageDir);

$ok = $db && $storage;
http_response_code($ok ? 200 : 503);

$payload = [
    'status' => $ok ? 'ok' : 'fail',
    'time'   => date('c'),
];

$expected = defined('HEALTH_KEY') ? (string) HEALTH_KEY : '';
$given    = (string) ($_GET['key'] ?? '');
if ($expected !== '' && hash_equals($expected, $given)) {
    $payload['details'] = [
        'php'      => PHP_VERSION,
        'database' => $db,
        'storage'  => $storage,
        'disk_free_mb' => (int) round(@disk_free_space($storageDir) / 1048576),
    ];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
